<?php

namespace rosas\dam\gql\queries;

use \craft\gql\base\Query;
use \rosas\dam\gql\interfaces\DAMAssetInterface;
use \rosas\dam\gql\resolvers\DAMAssetResolver;
use craft\helpers\Gql as GqlHelper;
use craft\gql\arguments\elements\Asset as AssetArguments;
use GraphQL\Type\Definition\Type;

class DAMAssetQuery extends Query {

    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQueryEntries()) {
            return [];
        }

        return [
                'type' => Type::listOf(DAMAssetInterface::getType()),
                'args' => AssetArguments::getArguments(),
                'resolve' => DAMAssetResolver::class . '::resolve',
                'description' => 'This query is used to query for DAM assets.',
                'complexity' => GqlHelper::relatedArgumentComplexity(),
        ];
    }
}