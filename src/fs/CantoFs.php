<?php

namespace lsst\dam\fs;

use Craft;
use \Datetime;
use craft\base\Fs;
use craft\models\FsListing;
use Generator;

/**
 *
 */
class CantoFs extends Fs {

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Canto DAM Volume');
    }

    /**
     * @inheritdoc
     */
    public function getRootUrl(): ?string
    {
        return "CantoFs root url";
    }

    /**
     * @inheritdoc
     */
    public function getFileList(string $directory = '', bool $recursive = true): Generator
    {
        yield new FsListing([
            'dirname' => "fake/dir",
            'basename' => "fake basename",
            'type' => 'file',
            'dateModified' => new DateTime(),
            'fileSize' => null
        ]);
    }

    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteFile(string $path): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function renameFile(string $path, string $newPath): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function copyFile(string $path, string $newPath): void
    {
        return;
    }

    /**
     * @param string $uriPath
     * @param string $targetPath
     * @return int
     */
    public function saveFileLocally(string $uriPath, string $targetPath): int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getFileStream(string $uriPath)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function read(string $path): string
    {
        return "coming back from CantoFs::read()";
    }

        /**
     * @inheritdoc
     */
    public function write(string $path, string $contents, array $config = []): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function renameDirectory(string $path, string $newName): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function getFileSize(string $uri): int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getDateModified(string $uri): int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        return;
    }
}