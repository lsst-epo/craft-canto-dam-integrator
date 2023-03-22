<?php

namespace lsst\dam\db;

use Craft;
use craft\db\ActiveRecord;
use craft\helpers\Json;
use yii\db\Exception;

/**
 *
 */
class AssetMetadata extends ActiveRecord {
    
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return "{{universaldamintegrator_asset_metadata}}";
    }

    /**
     * @param $id
     * @param $assetMetadata
     * @return void
     * @throws Exception
     */
    public static function upsert($id, $assetMetadata): void
    {
        $db = Craft::$app->getDb();
        foreach(\lsst\dam\models\Constants::ASSET_METADATA_FIELDS as $key => $value) {
            $metaVal = "";
            if(array_key_exists($value[0], $assetMetadata)) {
                $metaVal = $assetMetadata[$value[0]];
                if(count($value) > 1) {
                    $metaVal = $metaVal[$value[1]];
                }
                if ($key == "tags") {
                    $metaVal = Json::encode($metaVal);
                } else {
                    $metaVal = ($metaVal == null) ? "" : $metaVal;
                }
            }

            $rows = self::find() // check if the record alread exists
                ->where(['dam_meta_key' => $key, 'assetId' => $id])
                ->all();

            if($rows == null || count($rows) == 0) { // insert
                $db->createCommand()
                    ->insert('{{%universaldamintegrator_asset_metadata}}',  [
                        'assetId' => $id,
                        'dam_meta_key' => $key,
                        'dam_meta_value' => $metaVal
                    ])
                    ->execute();
            } else { //update
                $db->createCommand()
                    ->update('{{%universaldamintegrator_asset_metadata}}',  [
                        'dam_meta_value' => $metaVal
                    ],
                    '"assetId" = :assetId AND dam_meta_key = :dam_key',
                    [
                        ":assetId" => intval($id),
                        ":dam_key" => $key
                    ])
                    ->execute();
            }

	    }

	    // Lastly, insert the backend organization metadata
        foreach($assetMetadata["epo_etc"] as $key => $value) {
            $db->createCommand()
            ->insert('{{%universaldamintegrator_asset_metadata}}',  [
                'assetId' => $id,
                'dam_meta_key' => $key,
                'dam_meta_value' => $value
            ])
            ->execute();
        }

    }

}
