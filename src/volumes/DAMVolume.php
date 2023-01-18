<?php

namespace rosas\dam\volumes;

use Craft;
use craft\base\Volume;
use craft\base\FlysystemVolume;
use League\Flysystem\Filesystem;

/**
 * It's possible this class isn't needed at all, for context:
 * https://github.com/craftcms/cms/discussions/9991
 * The extended Volume class implements FlysystemVolume and it seems
 * most of the interface functions are for image manipulation, which 
 * for phase 1 of the DAM integration has been deemed not necessary
 */

class DAMVolume extends Volume
//abstract class DAMVolume extends FlysystemVolume
{

    public $dummySetting = ""; // Do not remove, Craft expects at least one volume setting for some reason and removing this will break the plugin/volumes

    public function init() {
        parent::init();
    }
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Canto DAM'; // return display name from settings
    }

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }



    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getRootUrl()
    {

        return parent::getRootUrl();
    }



    /**
     * @inheritdoc
     */
    protected function addFileMetadataToConfig(array $config): array
    {
        return parent::addFileMetadataToConfig($config);
    }

    // Beginning of inherited class declaration

    protected function filesystem(array $config = []): Filesystem
    {
        // Constructing a Filesystem is super cheap and we always get the config we want, so no caching.
        return new Filesystem($this->adapter(), new Config($config));
    }

    public function getFileMetadata(string $uri): array {
        return parent::getFileSize($uri);
    }

    public function getFileSize(string $uri): ?int {
        return parent::getFileSize($uri);
    }

    public function getDateModified(string $uri): ?int {
        return parent::getDateModified($uri);
    }

    public function createFileByStream(string $path, $stream, array $config) {
        return parent::createFileByStream($path, $stream, $config);
    }

    public function updateFileByStream(string $path, $stream, array $config) {
        return parent::updateFileByStream($path, $stream, $config);
    }

    public function fileExists(string $path): bool {
        return parent::fileExists($path);
    }

    public function deleteFile(string $path) {
        return parent::deleteFile($path);
    }

    public function renameFile(string $path, string $newPath) {
        return parent::renameFile($path, $newPath);
    }

    public function copyFile(string $path, string $newPath) {
        return parent::copyFile($path, $newPath);
    }

    public function saveFileLocally(string $uriPath, string $targetPath): int {
        return parent::saveFileLocally($uriPath, $targetPath);
    }

    public function getFileStream(string $uriPath) {
        return parent::getFileStream($uriPath);
    }

    public function getFileList(string $directory, bool $recursive): array {
        return parent::getFileList($directory, $recursive);
    }

    // End of inherited class declaration

}