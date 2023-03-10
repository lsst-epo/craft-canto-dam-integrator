<?php

namespace lsst\dam\elements;

use \Datetime;
use Craft;
use craft\records\Asset as AssetRecord;
use craft\base\Element;
use lsst\dam\elements\db\DAMAssetQuery;
use craft\elements\db\ElementQueryInterface;

/**
 *
 *
 * @property-write null|float|int $width
 * @property-read null|int $volumeId
 * @property-write mixed $asset
 * @property-write null|float|int $height
 */
class Asset extends Element {

    /**
     * @var string|null dam_meta_key
     */
    public mixed $dam_meta_key;

    public mixed $assetId;

    public mixed $asset_id;

    public mixed $dam_meta_value;

    public mixed $damMetadata;

    public string $thumbnailUrl;

    /**
     * Validation scenario that should be used when the asset is only getting *moved*; not renamed.
     *
     * @since 3.7.1
     */
    public const SCENARIO_REPLACE = 'replace';
    public const SCENARIO_CREATE = 'create';

    private mixed $element;

    /**
     * @var int|null Folder ID
     */
    public ?int $folderId;

    /**
     * @var int|null The ID of the user who first added this asset (if known)
     */
    public ?int $uploaderId;

    /**
     * @var string|null Folder path
     */
    public ?string $folderPath;

    /**
     * @var string|null Filename
     */
    public ?string $filename;

    /**
     * @var string|null Kind
     */
    public ?string $kind;

    /**
     * @var int|null Size
     */
    public ?int $size;

    /**
     * @var bool|null Whether the file was kept around when the asset was deleted
     */
    public ?bool $keptFile;

    /**
     * @var \DateTime|null Date modified
     */
    public ?DateTime $dateModified;

    /**
     * @var string|null New file location
     */
    public ?string $newLocation;

    /**
     * @var string|null Location error code
     * @see AssetLocationValidator::validateAttribute()
     */
    public ?string $locationError;

    /**
     * @var string|null New filename
     */
    public ?string $newFilename;

    /**
     * @var int|null New folder id
     */
    public ?int $newFolderId;

    /**
     * @var string|null The temp file path
     */
    public ?string $tempFilePath;

    /**
     * @var bool Whether Asset should avoid filename conflicts when saved.
     */
    public bool $avoidFilenameConflicts = false;

    /**
     * @var string|null The suggested filename in case of a conflict.
     */
    public ?string $suggestedFilename;

    /**
     * @var string|null The filename that was used that caused a conflict.
     */
    public ?string $conflictingFilename;

    /**
     * @var bool Whether the asset was deleted along with its volume
     * @see beforeDelete()
     */
    public bool $deletedWithVolume = false;

    /**
     * @var bool Whether the associated file should be preserved if the asset record is deleted.
     * @see beforeDelete()
     * @see afterDelete()
     */
    public bool $keepFileOnDelete = false;

    /**
     * @var int|null Volume ID
     */
    private mixed $_volumeId;

    /**
     * @var int|float|null Width
     */
    private int|null|float $_width;

    /**
     * @var int|float|null Height
     */
    private int|null|float $_height;

    /**
     * @var array|null Focal point
     */
    private ?array $_focalPoint;

    /**
     * @var AssetTransform|null
     */
    private mixed $_transform;

    /**
     * @var string
     */
    private string $_transformSource = '';

    /**
     * @var VolumeInterface|null
     */
    private ?VolumeInterface $_volume;

    /**
     * @var User|null
     */
    private ?User $_uploader;

    /**
     * @var int|null
     */
    private ?int $_oldVolumeId;

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
     * @param array $config
     */
    public function __construct(array $config = []) {
        if($config != null) {
            $this->dam_meta_key = $config["dam_meta_key"] ?? null;
            $this->dam_meta_value = $config["dam_meta_value"] ?? null;
            if(array_key_exists('damMetadata', $config)) {  
                $this->damMetadata = $config['damMetadata'];
            }  
            $this->id = $config["id"] ?? null;
            $this->assetId = $config["assetId"] ?? null;
        }

        parent::__construct();
    }

    /**
     * @param $el
     * @return void
     */
    public function setAsset($el): void
    {
        $this->element = $el;
    }

        /**
     * Sets the image width.
     *
     * @param float|int|null $width the image width
     */
    public function setWidth(float|int|null $width): void
    {
        $this->_width = $width;
    }

    /**
     * Sets the image height.
     *
     * @param float|int|null $height the image height
     */
    public function setHeight(float|int|null $height): void
    {
        $this->_height = $height;
    }

        /**
     * Returns the image width.
     *
     * @param array|string|AssetTransform|null $transform A transform handle or configuration that should be applied to the image
     * @return int|float|null
     */
    public function getWidth(AssetTransform|array|string $transform = null): float|int|null
    {
        return $this->_dimensions($transform)[0];
    }

        /**
     * Returns the image height.
     *
     * @param array|string|AssetTransform|null $transform A transform handle or configuration that should be applied to the image
     * @return int|float|null
     */

    public function getHeight(AssetTransform|array|string $transform = null): float|int|null
    {
        return $this->_dimensions($transform)[1];
    }

    /**
     * Returns the width and height of the image.
     *
     * @param array|string|AssetTransform|null $transform
     * @return array
     */
    private function _dimensions(AssetTransform|array|string $transform = null): array
    {
        if(!str_starts_with($this->kind, "ext")) {
            return [null, null];
        }

        if (!$this->_width || !$this->_height) {
            if ($this->getScenario() !== self::SCENARIO_CREATE) {
                Craft::warning("Asset $this->id is missing its width or height", __METHOD__);
            }
            return [null, null];
        }

        $transform = $transform ?? $this->_transform;

        [$width, $height] = Image::calculateMissingDimension($transform->width, $transform->height, $this->_width, $this->_height);

        return [$width, $height];
    }


    /**
     * Returns the volume’s ID.
     *
     * @return int|null
     */
    public function getVolumeId(): ?int
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

            $damVol = \lsst\dam\DamPlugin::getInstance()->settings->damVolume;

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