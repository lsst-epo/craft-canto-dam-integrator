<?php

namespace rosas\dam\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use craft\helpers\Json;
use craft\records\Asset as AssetRecord;
use craft\records\Element as ElementRecord;
use rosas\dam\services\Assets;
use rosas\dam\db\AssetMetadata;
use rosas\dam\models\Constants;
use rosas\dam\fields\DAMAsset;
use craft\helpers\ElementHelper;

// New imports
use rosas\dam\elements\db\AssetQuery;
use rosas\dam\elements\db\ContentQuery;

class AssetSyncController extends Controller {

    const ALLOW_ANONYMOUS_NEVER = 0;
    const ALLOW_ANONYMOUS_LIVE = 1;
    const ALLOW_ANONYMOUS_OFFLINE = 2;

    public $enableCsrfValidation = false;

    /**
     * @var int|bool|int[]|string[] Whether this controller’s actions can be accessed anonymously.
     *
     * This can be set to any of the following:
     *
     * - `false` or `self::ALLOW_ANONYMOUS_NEVER` (default) – indicates that all controller actions should never be
     *   accessed anonymously
     * - `true` or `self::ALLOW_ANONYMOUS_LIVE` – indicates that all controller actions can be accessed anonymously when
     *    the system is live
     * - `self::ALLOW_ANONYMOUS_OFFLINE` – indicates that all controller actions can be accessed anonymously when the
     *    system is offline
     * - `self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE` – indicates that all controller actions can be
     *    accessed anonymously when the system is live or offline
     * - An array of action IDs (e.g. `['save-guest-entry', 'edit-guest-entry']`) – indicates that the listed action IDs
     *   can be accessed anonymously when the system is live
     * - An array of action ID/bitwise pairs (e.g. `['save-guest-entry' => self::ALLOW_ANONYMOUS_OFFLINE]` – indicates
     *   that the listed action IDs can be accessed anonymously per the bitwise int assigned to it.
     */
    public $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetRemoval() {
        Craft::info("DAM Asset upload removal triggered!", "UDAMI");
        $elementId = $this->request->getBodyParam('elementId');
	    $fieldId = $this->request->getBodyParam('fieldId');
        $statusResponse = "";
        $messagesResponse = [];
        
	    try {
            // First, get assetId from the elementId
            $assetId = AssetQuery::getAssetIdByElementId($elementId, $fieldId);

            if($assetId != null) {
                // Then, remove asset data
                $this->actionAssetDeleteWebhook($assetId, $elementId, $fieldId);

                $field = Craft::$app->fields->getFieldByHandle("damAsset");
                $col_name = ElementHelper::fieldColumnFromField($field);

                $success = ContentQuery::removeElementId($elementId);

                if($success) {
                    $statusResponse = "success";
                } else {
                    $statusResponse = "error";
                    array_push($messagesResponse, "There was an error removing the element ID from the the parent entry!");
                }
            } else {
                $statusResponse = "error";
                array_push($messagesResponse, "No asset found for that element and/or field ID!");
            }
           
        } catch (\Exception $e) {
            $statusResponse = "error";
            array_push($messagesResponse, "An unknown error occurred while attempting to remove the DAM asset!");
        }
        return Json::encode([
            "status" => $statusResponse,
            "messages" => $messagesResponse
        ]);

    }

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetUpload() {
        Craft::info("DAM Asset upload triggered!", "UDAMI");
        $damId = $this->request->getBodyParam('cantoId');
        $fieldId = $this->request->getBodyParam('fieldId');
	    $elementId = $this->request->getBodyParam('elementId');
        $success = false;
        $response = [
            "canto_id_from_ui" => null,
            "field_id_from_ui" => null,
            "element_id_from_ui" => null,
            "asset_thumbnail" => null
        ];

        $assetsService = new Assets();
	    $res = $assetsService->saveDamAsset($damId, $elementId, $fieldId);
        $assetId = AssetQuery::getAssetIdByElementId($elementId, $fieldId);

        if($assetId != null) {
            $damFieldService = new DAMAsset();
            $metadata = $damFieldService->getAssetMetadataByAssetId($assetId);

            // Craft appends a random guid to the end of custom fields, this makes
            // getting the correct column name tricky, hence this query to first retrieve the column name
            $field = Craft::$app->fields->getFieldByHandle("damAsset");
            $col_name = ElementHelper::fieldColumnFromField($field);

            if(count($metadata) > 0) {
                $success = ContentQuery::updateElementID($elementId, $assetId);
            }
            if($success) {
                $response = [
                    "canto_id_from_ui" => $damId,
                    "field_id_from_ui" => $fieldId,
                    "element_id_from_ui" => $elementId,
                    "asset_thumbnail" => $metadata["thumbnailUrl"]
                ];
            }
            
        }
        return Json::encode($response);
    }

    /**
     * CREATE webhook controller
     */
    public function actionAssetCreateWebhook() {
        Craft::info("'Create' webhook triggered!", "Universal DAM Integrator");
        $damId = $this->request->getBodyParam('id');
        $assetsService = new Assets();
        $res = $assetsService->saveDamAsset($damId);
        return Json::encode($res);
    }

    /**
     * DELETE webhook controller
     */
    public function actionAssetDeleteWebhook($assetId = null, $elementId = null, $fieldId = null) {
        Craft::info("'Delete' webhook triggered!", "Universal DAM Integrator");
        try {
            $damId = $this->request->getBodyParam('id');
            $ids = ($assetId == null) ? AssetQuery::getAssetIdByDamId($damId) : [$assetId];
            $statusResponse = "error";
            $messagesResponse = [];
            if ($ids == null || count($ids) == 0) {
                array_push($messagesResponse, "No assets found with those IDs!");
            }
            foreach($ids as $id) {
                // Deleting the element record cascades to the assets record which cascades to the assetMetadata record
                $element = ElementRecord::findOne($id);
                $element->delete();
                $statusResponse = "success";
            }
        } catch (\Exception $e) {
            $statusResponse = "error";
            array_push($messagesResponse, "An unknown error occurred while attempting to remove the DAM asset!");
        }
        
        Craft::info("'Delete' webhook successful!", "Universal DAM Integrator");
        return Json::encode([
            "status" => $statusResponse,
            "messages" => $messagesResponse
        ]);
    }

    /**
     * UPDATE webhook controller
     */
    public function actionAssetUpdateWebhook() {
        $damId = $this->request->getBodyParam('id');
        $assetsService = new Assets();
        $ids = AssetQuery::getAssetIdByDamId($damId);

        if($ids != null && is_array($ids) && count($ids) > 0) {
            $assetMetadata = $assetsService->getAssetMetadata($damId);

            if($assetMetadata != null) {
                foreach($ids as $id) { // Temporary code! There shouldn't be multiple craft asset records for a single DAM ID, but during dev testing there is
                    AssetMetadata::upsert($id, $assetMetadata);
                }
            } else {
                Craft::warning("Asset update failed! No Metadata found!", "Universal DAM Integrator");
                return false;
            }
            Craft::info("'Update' webhook successful!", "Universal DAM Integrator");
            return true;
        } else { // The asset record doesn't exist for some reason, so create it
            $this->actionAssetCreateWebhook();
        }
    }
    
}
