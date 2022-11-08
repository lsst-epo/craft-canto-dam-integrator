<?php

namespace rosas\dam\gql\resolvers;

use Craft;
use rosas\dam\db\AssetMetadata;
use craft\elements\db\ElementQuery;
use craft\gql\resolvers\elements\Asset as AssetResolver;
use rosas\dam\elements\Asset as AssetElement;
use rosas\dam\elements\db\DAMAssetQuery;
use GraphQL\Type\Definition\ResolveInfo;
use craft\helpers\Gql as GqlHelper;

class DAMAssetResolver extends AssetResolver {

    /**
     * @inheritdoc
     */
    public static function resolve($source, array $arguments, $context, ResolveInfo $resolveInfo)
    {
        $query = self::prepareElementQuery($source, $arguments, $context, $resolveInfo);
        $value = $query instanceof ElementQuery ? $query->all() : $query;
        return GqlHelper::applyDirectives($source, $resolveInfo, $value);
    }

    /**
     *  Based on craft\gql\resolvers\elements\Asset;
     */
    public static function prepareQuery($source, array $arguments, $fieldName = null)
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

