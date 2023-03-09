<?php

namespace rosas\dam\gql\interfaces;

use Craft;
use GraphQL\Type\Definition\Type;
use craft\gql\interfaces\elements\Asset as AssetInterface;
use craft\gql\TypeManager;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InterfaceType;
use rosas\dam\gql\types\generators\DAMAssetGenerator;
use rosas\dam\models\Metadata;

/**
 *
 */
class DAMAssetInterface extends AssetInterface {

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::class)) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::class, new InterfaceType([
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
        return TypeManager::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), self::getConditionalFields(), [
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