<?php

namespace lsst\dam\gql\interfaces;

use Craft;
use GraphQL\Type\Definition\Type;
use craft\gql\interfaces\elements\Asset as AssetInterface;
use craft\gql\GqlEntityRegistry;
use craft\services\Gql;
use GraphQL\Type\Definition\InterfaceType;
use lsst\dam\gql\types\generators\DAMAssetGenerator;
use lsst\dam\models\Metadata;

/**
 *
 */
class DAMAssetInterface extends AssetInterface {

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        $name = "DAMAssetInterface";
        if ($type = GqlEntityRegistry::getEntity($name)) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity($name, new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all DAM assets.',
            'resolveType' => function($value) {
                return GqlEntityRegistry::getEntity(DAMAssetGenerator::getName());
            }
        ]));

        DAMAssetGenerator::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return DAMAssetGenerator::class;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'DAMAssetInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Gql::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), self::getConditionalFields(), [
            'dam_meta_key' => [
                'name' => 'dam_meta_key',
                'type' => Type::string(),
                'description' => 'Gets the key from the dam metadata table.'
            ],
            'dam_meta_value' => [
                'name' => 'dam_meta_value',
                'type' => Type::string(),
                'description' => 'Gets the value from the dam metadata table.',
            ],
            'damMetadata' => [
                'name' => 'damMetadata',
                'type' => Type::listOf(GqlEntityRegistry::getEntity(Metadata::getType())),
                'description' => 'Gets the key-value from the dam metadata table.',
            ],
            'assetId' => [
                'name' => 'assetId',
                'type' => Type::int(),
                'description' => 'Asset ID associated to metadata',
            ]
        ]), self::getName());
    }
}