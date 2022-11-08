<?php

namespace rosas\dam\elements\db;

use Craft;
// use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQuery;
use craft\db\Table;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\ArrayHelper;

use rosas\dam\models\Metadata;

class DAMAssetQuery extends ElementQuery {

     /**
     * @var mixed The height (in pixels) that the resulting assets must have.
     * ---
     * ```php{4}
     * // fetch images that are at least 500 pixels high
     * $images = \craft\elements\Asset::find()
     *     ->kind('image')
     *     ->height('>= 500')
     *     ->all();
     * ```
     * ```twig{4}
     * {# fetch images that are at least 500 pixes high #}
     * {% set logos = craft.assets()
     *   .kind('image')
     *   .height('>= 500')
     *   .all() %}
     * ```
     * @used-by height()
     */
    public $height;

    /**
     * @var mixed The size (in bytes) that the resulting assets must have.
     * @used-by size()
     */
    public $size;

    /**
     * @var mixed The Date Modified that the resulting assets must have.
     * @used-by dateModified()
     */
    public $dateModified;

    /**
     * @var bool Whether the query should search the subfolders of [[folderId]].
     * @used-by includeSubfolders()
     */
    public $includeSubfolders = false;

    /**
     * @var string|array|null The asset transform indexes that should be eager-loaded, if they exist
     * ---
     * ```php{4}
     * // fetch images with their 'thumb' transforms preloaded
     * $images = \craft\elements\Asset::find()
     *     ->kind('image')
     *     ->withTransforms(['thumb'])
     *     ->all();
     * ```
     * ```twig{4}
     * {# fetch images with their 'thumb' transforms preloaded #}
     * {% set logos = craft.assets()
     *   .kind('image')
     *   .withTransforms(['thumb'])
     *   .all() %}
     * ```
     * @used-by withTransforms()
     */
    public $withTransforms;

    /**
     * @var string|string[]|null The file kind(s) that the resulting assets must be.
     *
     * Supported file kinds:
     * - access
     * - audio
     * - compressed
     * - excel
     * - flash
     * - html
     * - illustrator
     * - image
     * - javascript
     * - json
     * - pdf
     * - photoshop
     * - php
     * - powerpoint
     * - text
     * - video
     * - word
     * - xml
     * - unknown
     *
     * ---
     *
     * ```php
     * // fetch only images
     * $logos = \craft\elements\Asset::find()
     *     ->kind('image')
     *     ->all();
     * ```
     * ```twig
     * {# fetch only images #}
     * {% set logos = craft.assets()
     *   .kind('image')
     *   .all() %}
     * ```
     * @used-by kind()
     */
    public $kind;

    /**
     * @var mixed The width (in pixels) that the resulting assets must have.
     * ---
     * ```php{4}
     * // fetch images that are at least 500 pixels wide
     * $images = \craft\elements\Asset::find()
     *     ->kind('image')
     *     ->width('>= 500')
     *     ->all();
     * ```
     * ```twig{4}
     * {# fetch images that are at least 500 pixes wide #}
     * {% set logos = craft.assets()
     *   .kind('image')
     *   .width('>= 500')
     *   .all() %}
     * ```
     * @used-by width()
     */
    public $width;

    
    /**
     * @var int|int[]|string|null The volume ID(s) that the resulting assets must be in.
     * ---
     * ```php
     * // fetch assets in the Logos volume
     * $logos = \craft\elements\Asset::find()
     *     ->volume('logos')
     *     ->all();
     * ```
     * ```twig
     * {# fetch assets in the Logos volume #}
     * {% set logos = craft.assets()
     *   .volume('logos')
     *   .all() %}
     * ```
     * @used-by volume()
     * @used-by volumeId()
     */
    public $volumeId;

    /**
     * @var int|int[]|null The asset folder ID(s) that the resulting assets must be in.
     * @used-by folderId()
     */
    public $folderId;

    /**
     * @var int|null The user ID that the resulting assets must have been uploaded by.
     * @used-by uploader()
     * @since 3.4.0
     */
    public $uploaderId;

    /**
     * @var string|string[]|null The filename(s) that the resulting assets must have.
     * @used-by filename()
     */
    public $filename;

    /**
     * Container for holding key-value
     */
    public $assetMetadata = [];

    public $assetId;

    /**
     * @var bool
     * @see _supportsUploaderParam()
     */
    private static $_supportsUploaderParam;

    public function __construct(string $elementType, array $config = [], $assetId = null) {

        $this->assetId = $assetId;
        parent::__construct($elementType, $config);
    }
  
    public function populate($rows) {
        return parent::populate($this->normalizeMetadata($rows));
    }

    public function normalizeMetadata($rows) {
        $normalizedRows = [];

        $prevId = null;
        $currArr = null;
        foreach ($rows as $row) {
            if($row['id'] != $prevId) {
                if($currArr != null && $prevId != null) {
                    array_push($normalizedRows, $currArr);
                }
                $prevId = $row['id'];
                $currArr = $row;
                if(isset($row["dam_meta_key"]) && isset($row["dam_meta_value"])) {
                    $meta = new Metadata([]);
                    $meta->metadataKey = $row["dam_meta_key"];
                    $meta->metadataValue = $row["dam_meta_value"];
                    $currArr["assetId"] = $row["assetId"]; // To-do: Come up with a better workflow so the UI doesn't have to sort this out
                    $currArr['damMetadata'] = [$meta];
                }
            } else {
                if(isset($row["dam_meta_key"]) && isset($row["dam_meta_value"])) {
                    $meta = new Metadata([]);
                    $meta->metadataKey = $row["dam_meta_key"];
                    $meta->metadataValue = $row["dam_meta_value"];
                    $currArr["assetId"] = $row["assetId"]; // To-do: Come up with a better workflow so the UI doesn't have to sort this out
                    array_push($currArr["damMetadata"], $meta);
                }
            }
        }
        return $normalizedRows;
    }

    /**
     * Returns whether the `uploader` param is supported yet.
     *
     * @return bool
     * @todo remove after next beakpoint
     */
    private static function _supportsUploaderParam(): bool
    {
        if (self::$_supportsUploaderParam !== null) {
            return self::$_supportsUploaderParam;
        }

        $schemaVersion = Craft::$app->getInstalledSchemaVersion();
        return self::$_supportsUploaderParam = version_compare($schemaVersion, '3.4.5', '>=');
    }
    
    /**
     * Normalizes the volumeId param to an array of IDs or null
     */
    private function _normalizeVolumeId()
    {
        if ($this->volumeId === ':empty:') {
            return;
        }

        if (empty($this->volumeId)) {
            $this->volumeId = is_array($this->volumeId) ? [] : null;
        } else if (is_numeric($this->volumeId)) {
            $this->volumeId = [$this->volumeId];
        } else if (!is_array($this->volumeId) || !ArrayHelper::isNumeric($this->volumeId)) {
            $this->volumeId = (new Query())
                ->select(['id'])
                ->from([Table::VOLUMES])
                ->where(Db::parseParam('id', $this->volumeId))
                ->column();
        }
    }

    /**
     * Copied over from craft\elements\db\AssetQuery and modified
     */
    protected function beforePrepare(): bool
    {
        $this->_normalizeVolumeId();

        // See if 'volume' was set to an invalid handle
        if ($this->volumeId === []) {
            return false;
        }

        $this->joinElementTable('assets');
        $this->subQuery->innerJoin(['volumeFolders' => Table::VOLUMEFOLDERS], '[[volumeFolders.id]] = [[assets.folderId]]');
        $this->query->innerJoin(['volumeFolders' => Table::VOLUMEFOLDERS], '[[volumeFolders.id]] = [[assets.folderId]]');

        // Join to custom universaldamintegrator_asset_metadata table
        $this->query->leftJoin(['asset_metadata' => 'universaldamintegrator_asset_metadata'], '[[asset_metadata.assetId]] = [[assets.id]]');
        

        $this->query->select([
            'asset_metadata.assetId',
            'asset_metadata.dam_meta_key',
            'asset_metadata.dam_meta_value',
            'assets.volumeId',
            'assets.folderId',
            'assets.filename',
            'assets.kind',
            'assets.width',
            'assets.height',
            'assets.size',
            'assets.focalPoint',
            'assets.keptFile',
            'assets.dateModified',
            'volumeFolders.path AS folderPath',
        ]);

        if (self::_supportsUploaderParam()) {
            $this->query->addSelect('assets.uploaderId');
        }

        if ($this->volumeId) {
            if ($this->volumeId === ':empty:') {
                $this->subQuery->andWhere(['assets.volumeId' => null]);
            } else {
                $this->subQuery->andWhere(['assets.volumeId' => $this->volumeId]);
            }
        }

        if ($this->folderId) {
            $folderCondition = Db::parseParam('assets.folderId', $this->folderId);
            if (is_numeric($this->folderId) && $this->includeSubfolders) {
                $assetsService = Craft::$app->getAssets();
                $descendants = $assetsService->getAllDescendantFolders($assetsService->getFolderById($this->folderId));
                $folderCondition = ['or', $folderCondition, ['in', 'assets.folderId', array_keys($descendants)]];
            }
            $this->subQuery->andWhere($folderCondition);
        }

        if (self::_supportsUploaderParam() && $this->uploaderId) {
            $this->subQuery->andWhere(['uploaderId' => $this->uploaderId]);
        }

        if ($this->filename) {
            $this->subQuery->andWhere(Db::parseParam('assets.filename', $this->filename));
        }

        if ($this->kind) {
            $kindCondition = ['or', Db::parseParam('assets.kind', $this->kind)];
            $kinds = Assets::getFileKinds();
            foreach ((array)$this->kind as $kind) {
                if (isset($kinds[$kind])) {
                    foreach ($kinds[$kind]['extensions'] as $extension) {
                        $kindCondition[] = ['like', 'assets.filename', "%.{$extension}", false];
                    }
                }
            }
            $this->subQuery->andWhere($kindCondition);
        }

        if ($this->width) {
            $this->subQuery->andWhere(Db::parseParam('assets.width', $this->width));
        }

        if ($this->height) {
            $this->subQuery->andWhere(Db::parseParam('assets.height', $this->height));
        }

        if ($this->size) {
            $this->subQuery->andWhere(Db::parseParam('assets.size', $this->size));
        }

        if ($this->dateModified) {
            $this->subQuery->andWhere(Db::parseDateParam('assets.dateModified', $this->dateModified));
        }

        return parent::beforePrepare();
    }

}
