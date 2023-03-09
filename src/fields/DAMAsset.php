<?php

namespace rosas\dam\fields;

use Craft;
use craft\fields\Assets as AssetField;
use craft\base\ElementInterface;
use craft\helpers\Json;
use rosas\dam\db\AssetMetadata;
use rosas\dam\services\Assets as AssetService;
use craft\gql\arguments\elements\Asset as AssetArguments;
use rosas\dam\gql\interfaces\DAMAssetInterface as AssetInterface;
use rosas\dam\gql\resolvers\DAMAssetResolver as AssetResolver;
use craft\helpers\Gql as GqlHelper;
use craft\services\Gql as GqlService;
use GraphQL\Type\Definition\Type;
use craft\helpers\ElementHelper;
use rosas\dam\elements\db\ContentQuery;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;

/**
 *
 *
 * @property-read Type|array $contentGqlType
 */
class DAMAsset extends AssetField {

     /**
     * @inheritdoc
     */
    protected string $settingsTemplate = 'universal-dam-integrator/dam-asset-settings';

    /**
     * @inheritdoc
     */
    protected string $inputTemplate = 'universal-dam-integrator/dam-asset';

    /**
     * @inheritdoc
     */
    protected ?string $inputJsClass = 'Craft.DamAssetSelectInput';

    /**
     * @return string
     */
    public static function displayName(): string {
        return Craft::t('app', 'DAMAsset');
    }

    /**
     * @return bool
     */
    public static function hasContentColumn(): bool {
        return true; // Extended class sets this to false
    }

    // Pulled from \craft\fields\Assets

    /**
     * @return Type|array
     */
    public function getContentGqlType(): Type|array {
        return [
            'name' => $this->handle,
            'type' => Type::nonNull(Type::listOf(AssetInterface::getType())),
            'args' => AssetArguments::getArguments(),
            'resolve' => AssetResolver::class . '::resolve',
            'complexity' => GqlHelper::relatedArgumentComplexity(GqlService::GRAPHQL_COMPLEXITY_EAGER_LOAD),
        ];
    }

    /**
     * @param mixed $value
     * @param ElementInterface|null $element
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getInputHtml(mixed $value, ?\craft\base\ElementInterface $element = null): string {
        // Get our id and namespace
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);
        $metadata = [];

        $assetService = new AssetService();
        $authToken = $assetService->getAuthToken();

        // Render the input template
        $templateVals =             [
            'name' => $this->handle,
            'value' => $value,
            'fieldId' => $this->id,
            'elementId' => $element->id,
            'id' => $id,
            'element' => Json::encode($element),
            'namespacedId' => $namespacedId,
            'accessToken' => $authToken
        ];

        try {
            if(isset($element->damAsset)) {
                if($element->damAsset != null) {
                    $assetId = $this->getDamAssetId($element->id);
                    if($assetId != null && is_array($assetId) && count($assetId) > 0) {
                        $assetId = $assetId[0];
                        if($assetId != [] && $assetId != "[]" && is_int(intval($assetId))) { // value will likely come back as string, but may come back as "[]"
                            $metadata = $this->getAssetMetadataByAssetId($assetId);
                            $templateVals['assetId'] = $assetId;
                        } 
                    }
                }
            }
        } catch(Exception $e) {
            Craft::info($e->getMessage(), "error");
        }

        if(array_key_exists("thumbnailUrl", $metadata)) {
            $templateVals['thumbnailUrl'] = $metadata["thumbnailUrl"];
        }

        return Craft::$app->getView()->renderTemplate($this->inputTemplate, $templateVals);
    }

    /**
     * @param $elementId
     * @return array
     */
    /**
     * @param $elementId
     * @return array
     */
    public static function getDamAssetId($elementId): array
    {
        $field = Craft::$app->fields->getFieldByHandle("damAsset");
	    $col_name = ElementHelper::fieldColumnFromField($field);
        return ContentQuery::getDamAssetIdByElementId($elementId, $col_name);
    }

    /**
     * @param $assetId
     * @return array
     */
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
            if($currentId != intval(str_replace('"', '', $row['assetId']))) {
                $currentId = intval(str_replace('"', '', $row['assetId']));
                $res["assetId"] = $currentId;
                $res[$row["dam_meta_key"]] = $row["dam_meta_value"];
            } else {
                if($currentId != 0) {
                    $res[$row["dam_meta_key"]] = $row["dam_meta_value"];
                }
            }
        }
        return $res;
    }
}
