<?php

namespace rosas\dam\records;

use Craft;
use craft\db\Query;

class VolumeFolders {


    public static function getIdsByFolderName($folderName) {
        $query = new Query;
        return $query->select('id, parentId')
                    ->from('volumefolders')
                    ->where("name = :name", [ ":name" => $folderName])
                    ->one();
    }
}