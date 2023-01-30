<?php

namespace rosas\dam\volumes;

use Craft;
// use craft\base\Volume;
use craft\events\DefineBehaviorsEvent;
use craft\models\Volume;
use craft\base\FlysystemVolume;
use League\Flysystem\Filesystem;

// Craft 4
use craft\base\FsInterface;
use craft\fs\MissingFs;
use yii\base\InvalidConfigException;
use craft\events\DefineRulesEvent;

class DAMVolume extends Volume
{

    public $dummySetting = ""; // Do not remove, Craft expects at least one volume setting for some reason and removing this will break the plugin/volumes

    public ?string $handle = "cantoDam";
    private ?string $_fsHandle = "cantoDamFsHandle";

    // public function init() {
    //     parent::init();
    // }
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if ($this->hasEventHandlers(self::EVENT_INIT)) {
            $this->trigger(self::EVENT_INIT);
        }
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
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    public function getFs(): FsInterface
    {
        if (!isset($this->_fs)) {
            $handle = $this->getFsHandle();
            if (!$handle) {
                throw new InvalidConfigException('Volume is missing its filesystem handle.');
            }
            $fs = Craft::$app->getFs()->getFilesystemByHandle($handle);
            if (!$fs) {
                Craft::error("Invalid filesystem handle: $this->_fsHandle for the $this->name volume.");
                return new MissingFs(['handle' => $this->_fsHandle]);
            }
            $this->_fs = $fs;
        }

        return $this->_fs;
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = $this->defineBehaviors();
        
        // Give plugins a chance to modify them
        $event = new DefineBehaviorsEvent([
            'behaviors' => $behaviors,
        ]);
        $this->trigger(self::EVENT_DEFINE_BEHAVIORS, $event);

        return $event->behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = $this->defineRules();

        // Give plugins a chance to modify them
        $event = new DefineRulesEvent([
            'rules' => $rules,
        ]);
        $this->trigger(self::EVENT_DEFINE_RULES, $event);

        foreach ($event->rules as &$rule) {
            $this->_normalizeRule($rule);
        }

        return $event->rules;
    }

    // /**
    //  * @inheritdoc
    //  */
    // protected function addFileMetadataToConfig(array $config): array
    // {
    //     return parent::addFileMetadataToConfig($config);
    // }

    // // Beginning of inherited class declaration

    // protected function filesystem(array $config = []): Filesystem
    // {
    //     // Constructing a Filesystem is super cheap and we always get the config we want, so no caching.
    //     return new Filesystem($this->adapter(), new Config($config));
    // }

    // public function getFileMetadata(string $uri): array {
    //     return parent::getFileSize($uri);
    // }

    // public function getFileSize(string $uri): ?int {
    //     return parent::getFileSize($uri);
    // }

    // public function getDateModified(string $uri): ?int {
    //     return parent::getDateModified($uri);
    // }

    // public function createFileByStream(string $path, $stream, array $config) {
    //     return parent::createFileByStream($path, $stream, $config);
    // }

    // public function updateFileByStream(string $path, $stream, array $config) {
    //     return parent::updateFileByStream($path, $stream, $config);
    // }

    // public function fileExists(string $path): bool {
    //     return parent::fileExists($path);
    // }

    // public function deleteFile(string $path) {
    //     return parent::deleteFile($path);
    // }

    // public function renameFile(string $path, string $newPath) {
    //     return parent::renameFile($path, $newPath);
    // }

    // public function copyFile(string $path, string $newPath) {
    //     return parent::copyFile($path, $newPath);
    // }

    // public function saveFileLocally(string $uriPath, string $targetPath): int {
    //     return parent::saveFileLocally($uriPath, $targetPath);
    // }

    // public function getFileStream(string $uriPath) {
    //     return parent::getFileStream($uriPath);
    // }

    // public function getFileList(string $directory, bool $recursive): array {
    //     return parent::getFileList($directory, $recursive);
    // }

    // End of inherited class declaration

}