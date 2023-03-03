<?php

namespace rosas\dam\gql\types\generators;

use Craft;
use rosas\dam\gql\interfaces\DAMAssetInterface;
use rosas\dam\gql\types\DAMAssetType;

use rosas\dam\elements\Asset as AssetElement;
use craft\helpers\Gql as GqlHelper;

use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use craft\gql\TypeManager;
use craft\gql\base\ObjectType;

/**
 *
 */

/**
 *
 */
class DAMAssetGenerator implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $gqlTypes = [];
        $damAssetFields = DAMAssetInterface::getFieldDefinitions();
        $damAssetArgs = [];
        $typeName = self::getName();
        $damAssetType = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new DAMAssetType([
                'name' => $typeName,
                'args' => function () use ($damAssetArgs) {
                    return $damAssetArgs;
                },
                'fields' => function () use ($damAssetFields) {
                    return $damAssetFields;
                },
                'description' => 'This entity has all the enhanced asset fields',
            ]));

        $gqlTypes[$typeName] = $damAssetType;
        TypeLoader::registerType($typeName, function () use ($damAssetType) {
            return $damAssetType;
        });

        return $gqlTypes;
    }

    /**
     * @param $context
     * @return string
     */
    /**
     * @param $context
     * @return string
     */
    public static function getName($context = null): string
    {
        return 'DAMAssetType';
    }

    /**
     * @param $context
     * @return ObjectType
     */
    /**
     * @param $context
     * @return ObjectType
     */
    public static function generateType($context): ObjectType
    {
        /** @var Volume $volume */
        $typeName = AssetElement::gqlTypeNameByContext($context);
        $contentFieldGqlTypes = self::getContentFields($context);

        $assetFields = TypeManager::prepareFieldDefinitions(array_merge(DAMAssetInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);

        return GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new Asset([
            'name' => $typeName,
            'fields' => function() use ($assetFields) {
                return $assetFields;
            },
        ]));

    }
}