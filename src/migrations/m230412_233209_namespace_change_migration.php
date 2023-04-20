<?php

namespace lsst\dam\migrations;

use Craft;
use craft\db\Migration;

/**
 * m230412_233209_namespace_change_migration migration.
 */
class m230412_233209_namespace_change_migration extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        echo "m230412_233209_namespace_change_migration executing...\n";
        Craft::info("Inside of safeUp!!!!", "shavvy!");

        $this->update('{{%fields}}', [
            "type" => 'lsst\dam\fields\DAMAsset',
    ],[
            "type" => 'rosas\dam\fields\DAMAsset'
    ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230412_233209_namespace_change_migration cannot be reverted.\n";
        return false;
    }
}
