<?php

namespace rosas\dam\elements\db;

use Craft;
use rosas\dam\db\AssetMetadata;

/**
 *
 */
class AssetQuery {

    public const DAM_META_VALUE = "dam_meta_value";
    public const DAM_META_KEY = "dam_meta_key";
    public const ASSET_ID = "assetId";
    public const ELEMENT_ID = "elementId";
    public const FIELD_ID = "fieldId";
    public const DAM_ID = "damId";

    /**
     * @param $elementId_p
     * @param $fieldId
     * @return array
     */
    /**
     * @param $elementId_p
     * @param $fieldId
     * @return array
     */
    public static function getAssetIdsForElementId($elementId_p, $fieldId = null): array
    {
        $elementRows = AssetMetadata::find()
                                    ->where([self::DAM_META_VALUE => $elementId_p, self::DAM_META_KEY => self::ELEMENT_ID])
                                    ->all();
        $ids = [];

        foreach($elementRows as $row) {
            $ids[] = $row[self::ASSET_ID];
            Craft::info("asset ID : " . strval($row[self::ASSET_ID]), "schnooz");
        }

        return $ids;
    }

    /**
     * @param $elementId_p
     * @param $fieldId
     * @return mixed|null
     */
    /**
     * @param $elementId_p
     * @param $fieldId
     * @return mixed|null
     */
    public static function getAssetIdByElementId($elementId_p, $fieldId = null): mixed
    {
    	$assetId = null;
        $elementRows = AssetMetadata::find()
                                    ->where([self::DAM_META_VALUE => $elementId_p, self::DAM_META_KEY => self::ELEMENT_ID])
                                    ->all();

        if(count($elementRows) > 1) { // multiple DAM assets are associated with this entry, so perform another query based on fieldId if it is not null            
            $assetIds = [];
            foreach($elementRows as $row) {
                $assetIds[] = $row[self::ASSET_ID];
            }

            // Now perform another query that narrows down the search based on field ID
            $assetIdRow = AssetMetadata::find()
                                        ->where([self::ASSET_ID => $assetIds, self::DAM_META_KEY => self::FIELD_ID, self::DAM_META_VALUE => $fieldId])
                                        ->one();
            if($assetIdRow != null) {
                $assetId = $assetIdRow[self::ASSET_ID];
            }
        } else if (count($elementRows) == 1) {
            $assetId = $elementRows[0][self::ASSET_ID];
        }

        return $assetId;
    }

    /**
     * @param $damId
     * @return array
     */
    /**
     * @param $damId
     * @return array
     */
    public static function getAssetIdByDamId($damId): array
    {
        $rows = AssetMetadata::find()
        ->where([self::DAM_META_VALUE => $damId, self::DAM_META_KEY => self::DAM_ID])
        ->all();

        $ids = [];
        foreach($rows as $row) {
            $ids[] = str_replace('"', '', $row[self::ASSET_ID]);
        }
        return $ids;
    }

}