<?php

namespace rosas\dam\elements\db;

use Craft;
use craft\helpers\Json;

class ContentQuery {

    public function removeElementId($elementId) {
        $db = Craft::$app->getDb();
        $db->createCommand()
        ->update('{{content}}',  [
            $col_name  => null
        ],
        '"elementId" = :elementId',
        [
            ":elementId" => intval($elementId)
        ])
        ->execute();

        
    }

    public function updateElementID($elementId, $assetId) {
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
                return $success;
            }
        }

        return $success;
    
    }
}

