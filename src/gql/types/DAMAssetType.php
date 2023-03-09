<?php

namespace rosas\dam\gql\types;

use Craft;
use rosas\dam\gql\interfaces\DAMAssetInterface;
use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use rosas\dam\db\AssetMetadata;

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
            return $this->getAssetMetadataByAssetId($source->id);
        } else {
            try {
                return $source[$resolveInfo->fieldName];
            } catch (Exception $e) {
                return null;
            }
        }

    }

    /**
     * @param $assetId
     * @return array
     */
    public static function getAssetMetadataByAssetId($assetId): array
    {
        $rows = AssetMetadata::find()
                                ->where(['"assetId"' => $assetId])
                                ->all();

        $res = [];
        $currentId = 0;
        foreach($rows as $row) {
		    $metadataRow = [];
            $metadataRow["metadataKey"] = $row["dam_meta_key"];
            $metadataRow["metadataValue"] = $row["dam_meta_value"];
            $res[] = $metadataRow;
        }
        return $res;
    }
}
