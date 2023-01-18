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
    public array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetRemoval() {
        Craft::info("DAM Asset upload removal triggered!", "UDAMI");
        $elementId = $this->request->getBodyParam('elementId');
	$fieldId = $this->request->getBodyParam('fieldId');
        // Update the damAsset field with the newly uploaded asset
        $db = Craft::$app->getDb();
	try {
	    // First, get assetId from the elementId
            //$damFieldService = new DAMAsset();
	    //$assetId = $damFieldService->getDamAssetId(intval($elementId));
	    $assetId = $this->_getAssetIdByElementId($elementId, $fieldId);

	    // Then, remove asset data
	    $this->actionAssetDeleteWebhook($assetId, $elementId, $fieldId);

	    $field = Craft::$app->fields->getFieldByHandle("damAsset");
	    $col_name = ElementHelper::fieldColumnFromField($field);

            $db->createCommand()
            ->update('{{content}}',  [
                $col_name  => null
            ],
            '"elementId" = :elementId',
            [
                ":elementId" => intval($elementId)
            ])
            ->execute();

            return Json::encode([
                "status" => "success"
            ]);
    
        } catch (\Exception $e) {
            return Json::encode([
                "status" => "error"
            ]);
        }

        
    }

    /**
     * DAM Asset upload controller
     */
    public function actionDamAssetUpload() {
        Craft::info("DAM Asset upload triggered!", "UDAMI");
        $damId = $this->request->getBodyParam('cantoId');
        $fieldId = $this->request->getBodyParam('fieldId');
	$elementId = $this->request->getBodyParam('elementId');

        $assetsService = new Assets();
	$res = $assetsService->saveDamAsset($damId, $elementId, $fieldId);
	Craft::info("about to log res", "lasorda");
	Craft::info(Json::encode($res), "lasorda");

	//$assetQueryRes = $this->_getAssetIdByDamId($damId);
	//$damFieldService = new DAMAsset();
	//$assetId = $damFieldService->getDamAssetId(intval($elementId));
	$assetId = $this->_getAssetIdByElementId($elementId, $fieldId);
        Craft::info("about to log assetId after calling getAssetIdByElementId", "schneez");
        Craft::info($assetId, "schneez");

	//Craft::info("about to log assetId", "lasorda");
	//Craft::info($assetId, "lasorda");
	if($assetId != null) {
            $db = Craft::$app->getDb();

            $assetId = $assetId; // in the rare chance that multiple IDs are returned, grabbed the first one
            Craft::info("logging assetId now that i've selected an index", "lasorda");
	    Craft::info($assetId, "lasorda");
	    $damFieldService = new DAMAsset();
	    $metadata = $damFieldService->getAssetMetadataByAssetId($assetId);
	    Craft::info("about to log metadata", "lasorda");
	    Craft::info(Json::encode($metadata), "lasorda");

            // Craft appends a random guid to the end of custom fields, this makes
            // getting the correct column name tricky, hence this query to first retrieve the column name
            $field = Craft::$app->fields->getFieldByHandle("damAsset");
            $col_name = ElementHelper::fieldColumnFromField($field);

            if(count($metadata) > 0) {
                // Update the damAsset field with the newly uploaded asset
                $test = $db->createCommand()
                ->update('{{content}}',  [
                    $col_name => $metadata["assetId"]
                ],
                '"elementId" = :elementId',
                [
                    ":elementId" => intval($elementId)
                ])
                ->execute();
    
            }
    
            return Json::encode([
                "canto_id_from_ui" => $damId,
                "field_id_from_ui" => $fieldId,
                "element_id_from_ui" => $elementId,
                "asset_thumbnail" => $metadata["thumbnailUrl"]
            ]);
        }
        return Json::encode([
            "canto_id_from_ui" => null,
            "field_id_from_ui" => null,
            "element_id_from_ui" => null,
            "asset_thumbnail" => null
        ]);
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
        $damId = $this->request->getBodyParam('id');
        $ids = ($assetId == null) ? $this->_getAssetIdByDamId($damId) : [$assetId];

        foreach($ids as $id) {
            // Deleting the element record cascades to the assets record which cascades to the assetMetadata record
            $element = ElementRecord::findOne($id);
            $element->delete();
        }
        Craft::info("'Delete' webhook successful!", "Universal DAM Integrator");
        return true;
    }

    /**
     * UPDATE webhook controller
     */
    public function actionAssetUpdateWebhook() {
        $damId = $this->request->getBodyParam('id');
        $assetsService = new Assets();
        $ids = $this->_getAssetIdByDamId($damId);

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

    private static function _getAssetIdByElementId($elementId, $fieldId = null) {
    	$assetId = null;
        $elementRows = AssetMetadata::find()
            ->where(['dam_meta_value' => $elementId, 'dam_meta_key' => 'elementId'])
            ->all();

        if(count($elementRows) > 1) { // multiple DAM assets are associated with this entry, so perform another query based on fieldId if it is not null
            $assetIds = [];
            foreach($elementRows as $row) {
                array_push($assetIds, $row["assetId"]);
            }

            // Now perform another query that narrows down the search based on field ID
            $assetIdRow = AssetMetadata::find()
                ->where(['assetId' => $assetIds, 'dam_meta_key' => 'fieldId', 'dam_meta_value' => $fieldId])
                ->one();
            if($assetIdRow != null) {
                $assetId = $assetIdRow["assetId"];
            }
        } else if (count($elementRows) == 1) {
            $assetId = $elementRows[0]["assetId"];
        }

        return $assetId;
    }

    private static function _getAssetIdByDamId($damId) {
        $rows = AssetMetadata::find()
        ->where(['dam_meta_value' => $damId, 'dam_meta_key' => 'damId'])
        ->all();

        $ids = [];
        foreach($rows as $row) {
            array_push($ids, str_replace('"', '', $row['assetId']));
        }
        return $ids;
    }

}
