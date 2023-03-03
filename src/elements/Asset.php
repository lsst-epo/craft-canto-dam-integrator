<?php

namespace rosas\dam\elements;

use \Datetime;
use Craft;
use craft\records\Asset as AssetRecord;
use craft\base\ElementInterface;
use craft\base\Element;
use craft\elements\Asset as AssetElement;
use rosas\dam\DamPlugin;
use rosas\dam\elements\db\DAMAssetQuery;
use craft\elements\db\ElementQueryInterface;

/**
 *
 */

/**
 *
 */
class Asset extends Element {
//class Asset extends AssetElement {

    /**
     * @var string|null dam_meta_key
     */
    public $dam_meta_key;

    public $assetId;

    public $asset_id;

    public $dam_meta_value;

    public $damMetadata;

    public $thumbnailUrl;

    /**
     * Validation scenario that should be used when the asset is only getting *moved*; not renamed.
     *
     * @since 3.7.1
     */
    const SCENARIO_REPLACE = 'replace';
    const SCENARIO_CREATE = 'create';

    /**
     * @var int|float|null Height
     */
    private $element;

    /**
     * @var int|null Folder ID
     */
    public $folderId;

    /**
     * @var int|null The ID of the user who first added this asset (if known)
     */
    public $uploaderId;

    /**
     * @var string|null Folder path
     */
    public $folderPath;

    /**
     * @var string|null Filename
     * @todo rename to private $_basename w/ getter & setter in 4.0; and getFilename() should not include the extension (to be like PATHINFO_FILENAME). We can add a getBasename() for getting the whole thing.
     */
    public $filename;

    /**
     * @var string|null Kind
     */
    public $kind;

    /**
     * @var int|null Size
     */
    public $size;

    /**
     * @var bool|null Whether the file was kept around when the asset was deleted
     */
    public $keptFile;

    /**
     * @var \DateTime|null Date modified
     */
    public $dateModified;

    /**
     * @var string|null New file location
     */
    public $newLocation;

    /**
     * @var string|null Location error code
     * @see AssetLocationValidator::validateAttribute()
     */
    public $locationError;

    /**
     * @var string|null New filename
     */
    public $newFilename;

    /**
     * @var int|null New folder id
     */
    public $newFolderId;

    /**
     * @var string|null The temp file path
     */
    public $tempFilePath;

    /**
     * @var bool Whether Asset should avoid filename conflicts when saved.
     */
    public $avoidFilenameConflicts = false;

    /**
     * @var string|null The suggested filename in case of a conflict.
     */
    public $suggestedFilename;

    /**
     * @var string|null The filename that was used that caused a conflict.
     */
    public $conflictingFilename;

    /**
     * @var bool Whether the asset was deleted along with its volume
     * @see beforeDelete()
     */
    public $deletedWithVolume = false;

    /**
     * @var bool Whether the associated file should be preserved if the asset record is deleted.
     * @see beforeDelete()
     * @see afterDelete()
     */
    public $keepFileOnDelete = false;

    /**
     * @var int|null Volume ID
     */
    private $_volumeId;

    /**
     * @var int|float|null Width
     */
    private $_width;

    /**
     * @var int|float|null Height
     */
    private $_height;

    /**
     * @var array|null Focal point
     */
    private $_focalPoint;

    /**
     * @var AssetTransform|null
     */
    private $_transform;

    /**
     * @var string
     */
    private $_transformSource = '';

    /**
     * @var VolumeInterface|null
     */
    private $_volume;

    /**
     * @var User|null
     */
    private $_uploader;

    /**
     * @var int|null
     */
    private $_oldVolumeId;

    /**
     * @inheritdoc
     * @since 3.3.0
     */
    public static function gqlScopesByContext(mixed $context): array
    {
        return ['volumes.' . $context->uid];
    }

    /**
     * @param $config
     */
    /**
     * @param $config
     */
    public function __construct($config = []) {
        if($config != null) {
            $this->dam_meta_key = (array_key_exists("dam_meta_key", $config)) ? $config["dam_meta_key"] : null ;
            $this->dam_meta_value = (array_key_exists("dam_meta_value", $config)) ? $config["dam_meta_value"] : null;
            if(array_key_exists('damMetadata', $config)) {  
                $this->damMetadata = $config['damMetadata'];
            }  
            $this->id = (array_key_exists("id", $config)) ? $config["id"] : null;
            $this->assetId = (array_key_exists("assetId", $config)) ? $config["assetId"] : null;
        }

        parent::__construct();
    }

    /**
     * @param $el
     * @return void
     */
    /**
     * @param $el
     * @return void
     */
    public function setAsset($el) {
        $this->element = $el;
    }

        /**
     * Sets the image width.
     *
     * @param int|float|null $width the image width
     */
    public function setWidth($width)
    {
        $this->_width = $width;
    }

    /**
     * Sets the image height.
     *
     * @param int|float|null $height the image height
     */
    public function setHeight($height)
    {
        $this->_height = $height;
    }

        /**
     * Returns the image width.
     *
     * @param AssetTransform|string|array|null $transform A transform handle or configuration that should be applied to the image
     * @return int|float|null
     */
    public function getWidth($transform = null)
    {
        return $this->_dimensions($transform)[0];
    }

        /**
     * Returns the image height.
     *
     * @param AssetTransform|string|array|null $transform A transform handle or configuration that should be applied to the image
     * @return int|float|null
     */

    public function getHeight($transform = null)
    {
        return $this->_dimensions($transform)[1];
    }

    /**
     * Returns the width and height of the image.
     *
     * @param AssetTransform|string|array|null $transform
     * @return array
     */
    private function _dimensions($transform = null): array
    {
        if(substr($asset->kind, 0, 3) != "ext") {
            return [null, null];
        }

        if (!$this->_width || !$this->_height) {
            if ($this->getScenario() !== self::SCENARIO_CREATE) {
                Craft::warning("Asset $this->id is missing its width or height", __METHOD__);
            }
            return [null, null];
        }

        $transform = $transform ?? $this->_transform;

        if ($transform === null || !Image::canManipulateAsImage($this->getExtension())) {
            return [$this->_width, $this->_height];
        }
        $transform = Craft::$app->getAssetTransforms()->normalizeTransform($transform);

        [$width, $height] = Image::calculateMissingDimension($transform->width, $transform->height, $this->_width, $this->_height);

        return [$width, $height];
    }


    /**
     * Returns the volume’s ID.
     *
     * @return int|null
     */
    public function getVolumeId()
    {
        return (int)$this->_volumeId ?: null;
    }

    /**
     * @param bool $isNew
     * @return void
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $isCpRequest = Craft::$app->getRequest()->getIsCpRequest();
            $sanitizeCpImageUploads = Craft::$app->getConfig()->getGeneral()->sanitizeCpImageUploads;

            if (
                \in_array($this->getScenario(), [self::SCENARIO_REPLACE, self::SCENARIO_CREATE], true) &&
                !($isCpRequest && !$sanitizeCpImageUploads)
            ) {
                Image::cleanImageByPath($this->tempFilePath);
            }

            // Get the asset record
            if (!$isNew) {
                $record = AssetRecord::findOne($this->$element->id);

                if (!$record) {
                    throw new Exception('Invalid asset ID: ' . $element->id);
                }
            } else {
                $record = new AssetRecord();
                $record->id = (int)$this->element->id;
             }

            $damVol = \rosas\dam\DamPlugin::getInstance()->settings->damVolume;

            $now = new DateTime();

            $record->filename = $this->element->filename;
            $record->volumeId = Craft::$app->getVolumes()->getVolumeByHandle($damVol)["id"];
            $record->folderId = (int)$this->element->folderId;
            $record->uploaderId = (int)$this->element->uploaderId ?: null;
            $record->kind = $this->element->kind;
            $record->size = (int)$this->element->size ?: null;
            $record->width = (int)$this->element->getWidth() ?: null;;
            $record->height = (int)$this->element->getHeight() ?: null;;
            $record->dateModified = $now->format('Y-m-d H:i:s');

            $tester = $record->save(true);


        }
        parent::afterSave($isNew);
    }

    /**
     * @return DAMAssetQuery The newly created [[AssetQuery]] instance.
     */
    public static function find(): ElementQueryInterface {
        return new DAMAssetQuery(static::class);
    }

}