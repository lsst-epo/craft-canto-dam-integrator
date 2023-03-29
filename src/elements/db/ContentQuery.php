<?php

namespace rosas\dam\elements\db;

use Craft;
use craft\helpers\Json;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

class ContentQuery {

    public static function removeElementId($elementId, $col_name) {
        $db = Craft::$app->getDb();
        $result = $db->createCommand()
        ->update('{{content}}',  [
            $col_name  => null
        ],
        '"elementId" = :elementId',
        [
            ":elementId" => intval($elementId)
        ])
        ->execute();

        return ($result > 0);
    }

    public static function updateElementID($elementId, $assetId, $col_name) {
        // Update the damAsset field with the newly uploaded asset
        $db = Craft::$app->getDb();
        $success = false;
        if($elementId != null) {
            try {
                $db->createCommand()
                ->update('{{content}}',  [
                    $col_name => $assetId
                ],
                '"elementId" = :elementId',
                [
                    ":elementId" => intval($elementId)
                ])
                ->execute();
                
                $success = true;
            } catch (\Exception $e) {
                Craft::info($e->getMessage(), "UDAMI");
                Craft::info($e->getTraceAsString(), "UDAMI");
                return $success;
            }
        }

        return $success;
    
    }

    public static function getDamAssetIdByElementId($elementId, $col_name) {
        return (new Query())
                        ->select([$col_name])
                        ->from([Table::CONTENT])
                        ->where(Db::parseParam('elementId', $elementId))
                        ->column();        
    }
    
}

