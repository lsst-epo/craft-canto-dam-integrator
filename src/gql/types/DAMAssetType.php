<?php

namespace rosas\dam\gql\types;

use Craft;
use rosas\dam\gql\interfaces\DAMAssetInterface;
use rosas\dam\fields\DAMAsset;
use craft\gql\base\ObjectType;
use craft\helpers\Json;
use GraphQL\Type\Definition\ResolveInfo;
use rosas\dam\db\AssetMetadata;

/**
 *
 */

/**
 *
 */
class DAMAssetType extends ObjectType {
    /**
     * @inheritdoc
     */
    public function __construct(array $config) {
        $config['interfaces'] = [
            DAMAssetInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed {
        if(isset($source[$resolveInfo->fieldName])) {
            return $source[$resolveInfo->fieldName];
        } else if($resolveInfo->fieldName == "damMetadata"){
	        $metadata = $this->getAssetMetadataByAssetId($source->id);
            return $metadata;
        } else {
            try {
                $resolvedValue = $source[$resolveInfo->fieldName];
                return $resolvedValue;
            } catch (Exception $e) {
                return null;
            }
        }

    }

    /**
     * @param $assetId
     * @return array
     */
    /**
     * @param $assetId
     * @return array
     */
    public static function getAssetMetadataByAssetId($assetId) {
        $rows = AssetMetadata::find()
                                ->where(['"assetId"' => $assetId])
                                ->all();

        $res = [];
        $currentId = 0;
        foreach($rows as $row) {
		    $metadataRow = [];
            $metadataRow["metadataKey"] = $row["dam_meta_key"];
            $metadataRow["metadataValue"] = $row["dam_meta_value"];
            array_push($res, $metadataRow);
        }
        return $res;
    }
}
