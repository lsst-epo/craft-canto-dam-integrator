<?php

namespace lsst\dam\migrations;

use Craft;
use craft\db\Migration;

/**
 * Universal DAM Integrator Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Eric Rosas
 * @package   UniversalDamIntegrator
 * @since     1.0.0
 */
class Install extends Migration
{
    /**
     * @var string The database driver to use
     */
    public string $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        // Perform migration as if this is a new plugin due to namespace changes
        echo "Performing install-migration for Canto Integration Plugin";
        $this->update('{{%fields}}', [
            "type" => 'lsst\dam\fields\DAMAsset'
        ],[
            "type" => 'rosas\dam\fields\DAMAsset'
          ]
        );

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%universaldamintegrator_asset_metadata}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%universaldamintegrator_asset_metadata}}',
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    // Custom columns in the table
                    'assetId' => $this->integer()->notNull(),
                    'dam_meta_key' => $this->string(255)->notNull(),
                    'dam_meta_value' => $this->string(1000)->notNull()
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes(): void
    {
        $this->createIndex(
            $this->db->getIndexName(
                '{{%universaldamintegrator_asset_metadata}}',
                'assetId',
                false
            ),
            '{{%universaldamintegrator_asset_metadata}}',
            'assetId',
            false
        );
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%universaldamintegrator_asset_metadata}}', 'siteId'),
            '{{%universaldamintegrator_asset_metadata}}',
            'assetId',
            '{{%assets}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables(): void
    {
        $this->dropTableIfExists('{{%universaldamintegrator_asset_metadata}}');
    }
}
