<?php

namespace rosas\dam\elements\db;

use Craft;
use rosas\dam\db\AssetMetadata;


class AssetQuery {

    const DAM_META_VALUE = "dam_meta_value";
    const DAM_META_KEY = "dam_meta_key";
    const ASSET_ID = "assetId";
    const ELEMENT_ID = "elementId";
    const FIELD_ID = "fieldId";
    const DAM_ID = "damId";

    public static function getAssetIdsForElementId($elementId_p, $fieldId = null) {
        $elementRows = AssetMetadata::find()
                                    ->where([self::DAM_META_VALUE => $elementId_p, self::DAM_META_KEY => self::ELEMENT_ID])
                                    ->all();
        $ids = [];

        foreach($elementRows as $row) {
            array_push($ids, $row[self::ASSET_ID]);
            Craft::info("asset ID : " . strval($row[self::ASSET_ID]), "schnooz");
        }

        return $ids;
    }

    public static function getAssetIdByElementId($elementId_p, $fieldId = null) {
    	$assetId = null;
        $elementRows = AssetMetadata::find()
                                    ->where([self::DAM_META_VALUE => $elementId_p, self::DAM_META_KEY => self::ELEMENT_ID])
                                    ->all();

        if(count($elementRows) > 1) { // multiple DAM assets are associated with this entry, so perform another query based on fieldId if it is not null            
            $assetIds = [];
            foreach($elementRows as $row) {
                array_push($assetIds, $row[self::ASSET_ID]);
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

    public static function getAssetIdByDamId($damId) {
        $rows = AssetMetadata::find()
        ->where([self::DAM_META_VALUE => $damId, self::DAM_META_KEY => self::DAM_ID])
        ->all();

        $ids = [];
        foreach($rows as $row) {
            array_push($ids, str_replace('"', '', $row[self::ASSET_ID]));
        }
        return $ids;
    }

}