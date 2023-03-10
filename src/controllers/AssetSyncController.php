<?php

namespace lsst\dam\controllers;

use Craft;
use craft\web\Controller;;
use craft\helpers\Json;
use craft\records\Element as ElementRecord;
use lsst\dam\services\Assets;
use lsst\dam\db\AssetMetadata;
use lsst\dam\fields\DAMAsset;
use craft\helpers\ElementHelper;
use lsst\dam\elements\db\AssetQuery;
use lsst\dam\elements\db\ContentQuery;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 *
 */
class AssetSyncController extends Controller {

    public const ALLOW_ANONYMOUS_NEVER = 0;
    public const ALLOW_ANONYMOUS_LIVE = 1;
    public const ALLOW_ANONYMOUS_OFFLINE = 2;

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
    public array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetRemoval($elementId_p = null, $fieldId_p = null): string
    {
        Craft::info("DAM Asset delete triggered!", "UDAMI");
        $elementId = ($elementId_p == null) ? $this->request->getBodyParam('elementId') : $elementId_p;
	    $fieldId = ($fieldId_p == null) ? $this->request->getBodyParam('fieldId') : $fieldId_p;
        $statusResponse = "";
        $messagesResponse = [];
        
	    try {
            // First, get assetId from the elementId
            $assetIds = AssetQuery::getAssetIdsForElementId($elementId, $fieldId);

            if($assetIds != null && count($assetIds) > 0) {
                foreach($assetIds as $id) {
                    // Then, remove asset data
                    $this->actionAssetDeleteWebhook($id, $elementId, $fieldId);

                    $field = Craft::$app->fields->getFieldByHandle("damAsset");
                    $col_name = ElementHelper::fieldColumnFromField($field);
                    $success = ContentQuery::removeElementId($elementId, $col_name);

                    if($success) {
                        $statusResponse = "success";
                    } else {
                        $statusResponse = "error";
                        $messagesResponse[] = "There was an error removing the element ID from the the parent entry!";
                    }
                }
            } else {
                $statusResponse = "error";
                $messagesResponse[] = "No asset found for that element and/or field ID!";
            }
           
        } catch (\Exception $e) {
            $statusResponse = "error";
            $messagesResponse[] = "An unknown error occurred while attempting to remove the DAM asset!";
            Craft::info($e->getMessage(), "UDAMI");
            Craft::info($e->getTraceAsString(), "UDAMI");
        }
        return Json::encode([
            "status" => $statusResponse,
            "messages" => $messagesResponse
        ]);

    }

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetUpload(): string
    {
        Craft::info("DAM Asset upload triggered!", "UDAMI");
        $damId = $this->request->getBodyParam('cantoId');
        $fieldId = $this->request->getBodyParam('fieldId');
	    $elementId = $this->request->getBodyParam('elementId');

        // First, remove any asset that may be lingering on the element
        $this->actionDamAssetRemoval($elementId, $fieldId);
        
        $success = false;
        $response = [
            "canto_id_from_ui" => null,
            "field_id_from_ui" => null,
            "element_id_from_ui" => null,
            "asset_thumbnail" => null
        ];

        $assetsService = new Assets();
        try {
            $res = $assetsService->saveDamAsset($damId, $elementId, $fieldId);
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), "UDAMI");
            return $e->getMessage();
        }
        $assetId = AssetQuery::getAssetIdByElementId($elementId, $fieldId);

        if($assetId != null) {
            $damFieldService = new DAMAsset();
            $metadata = $damFieldService->getAssetMetadataByAssetId($assetId);

            // Craft appends a random guid to the end of custom fields, this makes
            // getting the correct column name tricky, hence this query to first retrieve the column name
            $field = Craft::$app->fields->getFieldByHandle("damAsset");
            $col_name = ElementHelper::fieldColumnFromField($field);

            if(count($metadata) > 0) {
                $success = ContentQuery::updateElementID($elementId, $assetId, $col_name);
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
    public function actionAssetCreateWebhook(): string
    {
        Craft::info("'Create' webhook triggered!", "UDAMI");
        $damId = $this->request->getBodyParam('id');
        $assetsService = new Assets();
        try {
            $res = $assetsService->saveDamAsset($damId);
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), "UDAMI");
            return Json::encode([
                "status" => "error",
                "message" => "An error occurred while attempting to save the new asset from the 'create' webhook"
            ]);
        }
        return Json::encode($res);
    }

    /**
     * DELETE webhook controller
     */
    public function actionAssetDeleteWebhook($assetId = null, $elementId = null, $fieldId = null): string
    {
        Craft::info("'Delete' webhook triggered!", "UDAMI");
        try {
            $damId = $this->request->getBodyParam('id');
            $ids = ($assetId == null) ? AssetQuery::getAssetIdByDamId($damId) : [$assetId];
            $statusResponse = "error";
            $messagesResponse = [];
            if ($ids == null || count($ids) == 0) {
                $messagesResponse[] = "No assets found with those IDs!";
            }
            foreach($ids as $id) {
                // Deleting the element record cascades to the assets record which cascades to the assetMetadata record
                $element = ElementRecord::findOne($id);
                try {
                    $element->delete();
                } catch (StaleObjectException $e) {
                    $statusResponse = "error";
                    $messagesResponse[] = "An error occurred within the DB transaction - likely due to stale DB objects!";
                    $messagesResponse[] = $e->getMessage();
                } catch (\Throwable $e) {
                    $statusResponse = "error";
                    $messagesResponse[] = "An error occurred within the DB transaction!";
                    $messagesResponse[] = $e->getMessage();
                }
                $statusResponse = "success";
            }
        } catch (\Exception $e) {
            $statusResponse = "error";
            $messagesResponse[] = "An unknown error occurred while attempting to remove the DAM asset!";
            $messagesResponse[] = $e->getMessage();
        }
        
        Craft::info("'Delete' webhook successful!", "UDAMI");
        return Json::encode([
            "status" => $statusResponse,
            "messages" => $messagesResponse
        ]);
    }

    /**
     * UPDATE webhook controller
     */
    public function actionAssetUpdateWebhook(): bool
    {
        $damId = $this->request->getBodyParam('id');
        $assetsService = new Assets();
        $ids = AssetQuery::getAssetIdByDamId($damId);

        if($ids != null && is_array($ids) && count($ids) > 0) {
            $assetMetadata = $assetsService->getAssetMetadata($damId);

            if($assetMetadata != null) {
                foreach($ids as $id) { // Temporary code! There shouldn't be multiple craft asset records for a single DAM ID, but during dev testing there is
                    try {
                        AssetMetadata::upsert($id, $assetMetadata);
                    } catch (Exception $e) {

                    }
                }
            } else {
                Craft::warning("Asset update failed! No Metadata found!", "UDAMI");
                return false;
            }
            Craft::info("'Update' webhook successful!", "UDAMI");
            return true;
        } else { // The asset record doesn't exist for some reason, so create it
            $this->actionAssetCreateWebhook();
        }
    }

}
