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

        $this->update('{{%fields}}', [
            "type" => 'lsst\dam\fields\DAMAsset'
            ],[
            "type" => 'rosas\dam\fields\DAMAsset'
            ]
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230412_233209_namespace_change_migration is be reverted.\n";

        $this->update('{{%fields}}', [
            "type" => 'rosas\dam\fields\DAMAsset'
        ],[
            "type" => 'lsst\dam\fields\DAMAsset'
        ]
        );
        return true;
    }
}
