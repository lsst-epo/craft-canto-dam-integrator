<?php

namespace rosas\dam\models;

use Craft;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;
use craft\gql\TypeManager;

/**
 *
 */

/**
 *
 */
class Metadata extends ObjectType {

    public $metadataKey;

    public $metadataValue;

    public $description = "Key/value metadata for a DAM asset";

    /**
     * @inheritdoc
     */
    public function __construct($config)
    {
        $config = array_merge($config, [
            'name' => self::getName(),
            'description' => $this->description,
            'fields' => self::getFieldDefinition(),
        ]);
        parent::__construct($config);
    }

    /**
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'DAMMetadata';
    }

    /**
     * Returns a singleton instance to ensure one type per schema.
     *
     * @return Metadata
     */
    public static function getType(): Metadata
    {
        return GqlEntityRegistry::getEntity(self::getName()) ?: GqlEntityRegistry::createEntity(self::getName(), new self([]));
    }

        /**
     * Define fields for this type.
     *
     * @return array
     */
    public static function getFieldDefinition(): array
    {
        $fields = [
            'metadataKey' => [
                'name' => 'metadataKey',
                'type' => Type::string(),
            ],
            'metadataValue' => [
                'name' => 'metadataValue',
                'type' => Type::string(),
            ]
        ];

        return TypeManager::prepareFieldDefinitions($fields, self::getName());
    }

}