<?php

namespace rosas\dam\elements\db;

use Craft;
use rosas\dam\db\AssetMetadata;


class AssetQuery {

    private static string $DAM_META_VALUE = "dam_meta_value";
    private static string $DAM_META_KEY = "dam_meta_key";
    private static string $ASSET_ID = "assetId";
    private static string $ELEMENT_ID = "elementId";
    private static string $FIELD_ID = "fieldId";

    public static function getAssetIdByElementId($elementId, $fieldId = null) {
    	$assetId = null;
        $elementRows = AssetMetadata::find()
                                    ->where([$DAM_META_VALUE => $elementId, $DAM_META_KEY => $ELEMENT_ID])
                                    ->all();

        if(count($elementRows) > 1) { // multiple DAM assets are associated with this entry, so perform another query based on fieldId if it is not null
            $assetIds = [];
            foreach($elementRows as $row) {
                array_push($assetIds, $row[$ASSET_ID]);
            }

            // Now perform another query that narrows down the search based on field ID
            $assetIdRow = AssetMetadata::find()
                                        ->where([$ASSET_ID => $assetIds, $DAM_META_KEY => $FIELD_ID, $DAM_META_VALUE => $fieldId])
                                        ->one();
            if($assetIdRow != null) {
                $assetId = $assetIdRow[$ASSET_ID];
            }
        } else if (count($elementRows) == 1) {
            $assetId = $elementRows[0][$ASSET_ID];
        } 

        return $assetId;
    }

    public static function getAssetIdByDamId($damId) {
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