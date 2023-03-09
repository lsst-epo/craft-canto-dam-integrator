<?php

namespace rosas\dam\records;

use Craft;
use craft\db\Query;

/**
 *
 */
class VolumeFolders {

    /**
     * @param $folderName
     * @return array|bool|mixed
     */
    public static function getIdsByFolderName($folderName): mixed
    {
        $query = new Query;
        return $query->select('id, parentId')
                    ->from('volumefolders')
                    ->where("name = :name", [ ":name" => $folderName])
                    ->one();
    }
}