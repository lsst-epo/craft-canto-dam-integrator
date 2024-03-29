<?php

namespace lsst\dam\gql\resolvers;

use Craft;
use lsst\dam\db\AssetMetadata;
use craft\elements\db\ElementQuery;
use craft\gql\resolvers\elements\Asset as AssetResolver;
use lsst\dam\elements\Asset as AssetElement;
use GraphQL\Type\Definition\ResolveInfo;
use craft\helpers\Gql as GqlHelper;

/**
 *
 */
class DAMAssetResolver extends AssetResolver {

    /**
     * @inheritdoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $query = self::prepareElementQuery($source, $arguments, $context, $resolveInfo);
        $value = $query instanceof ElementQuery ? $query->all() : $query;
        return GqlHelper::applyDirectives($source, $resolveInfo, $value);
    }

    /**
     *  Based on craft\gql\resolvers\elements\Asset;
     */
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = AssetElement::find(); // From this plugin's overriden Asset class
        } else { // If not, get the prepared element query
            $elementRow = AssetMetadata::find()
                ->where(["dam_meta_value" => $source->id, "dam_meta_key" => 'elementId'])
                ->one();
            if($elementRow != null) {
                $query = craft\elements\Asset::find($elementRow->assetId);
                $query->id = $elementRow->assetId;
            } else {
                $query = $source->$fieldName;
            }
        }

        // If it's preloaded, it's preloaded.
        if (is_array($query)) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        return $query;
    }

}

