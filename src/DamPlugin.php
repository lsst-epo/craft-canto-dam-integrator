<?php
namespace lsst\dam;

use Craft;
use craft\base\Model;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\DefineAssetUrlEvent;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use craft\web\twig\variables\CraftVariable;
use lsst\dam\services\Assets;
use craft\services\Assets as CraftAssets;
use craft\elements\Asset as CraftAsset;
use lsst\dam\fields\DAMAsset;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use lsst\dam\gql\queries\DAMAssetQuery;
use craft\services\Gql;
use craft\events\RegisterGqlQueriesEvent;
use craft\web\UrlManager;
use craft\services\Fields;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\base\Plugin;
use lsst\dam\models\Settings;

// Craft 4
use craft\services\Fs;
use lsst\dam\fs\CantoFs;
use yii\base\Exception;

/**
 *
 */

/**
 *
 */
class DamPlugin extends Plugin
{
    // added asset extends
    public static Plugin $plugin;

    public bool $hasCpSettings = true;
    public string $schemaVersion = "2.0.0";

    /**
     * @param $id
     * @param $parent
     * @param array $config
     */
    /**
     * @param $id
     * @param $parent
     * @param array $config
     */
    public function __construct($id, $parent = null, array $config = []) {
        $config["components"] = [
            'assets' => Assets::class
        ];
        parent::__construct($id, $parent, $config);
    }

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Bind Assets service to be scoped to this plugin
        $this->setComponents([
            'assets' => \lsst\dam\services\Assets::class,
        ]);

        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    /**
     * @return void
     */
    private function attachEventHandlers(): void
    {
        // Add permission for Editors
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    "heading" => "Editor",
                    "permissions" => [
                        'accessPlugin-universal-dam-integrator' => [
                            'label' => 'Use DAM Integration Plugin',
                            ],
                        ]
                    ];
            }
        );

        // Add a tag for the settings page for testing services
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $e) {
            /** @var CraftVariable $variable */
            $tag = $e->sender;

            // Attach a service:
            $tag->set('metaSave', services\Assets::class);
        });

        // Handler: Assets::EVENT_GET_ASSET_THUMB_URL
        Event::on(
            CraftAssets::class,
            CraftAssets::EVENT_DEFINE_THUMB_URL,
            function (\craft\events\DefineAssetThumbUrlEvent $event) {
                Craft::debug(
                    '\craft\services\Assets::EVENT_DEFINE_THUMB_URL',
                    __METHOD__
                );
                // Return the URL to the asset URL or null to let Craft handle it
                $event->url = DamPlugin::$plugin->assets->handleGetAssetThumbUrlEvent($event);
            }
        );

        // Register DAM remote volume type
        Event::on(
            Fs::class,
            Fs::EVENT_REGISTER_FILESYSTEM_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CantoFs::class;
            }
        );

        // Register getAssetUrl event
        Event::on(
            CraftAsset::class,
            CraftAsset::EVENT_DEFINE_URL,
            function(DefineAssetUrlEvent $event) {
                $event->url = DamPlugin::$plugin->assets->getUrl($event);
            }
        );

        // Register query for retrieving DAM asset metadata
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function(RegisterGqlQueriesEvent $event) {
                $event->queries['enhancedAssetsQuery'] = DAMAssetQuery::getQueries();
            }
        );

        // Register the webhook endpoints CREATE controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/create'] = 'canto-dam-integrator/asset-sync/asset-create-webhook';
            }
        );

        // Register the webhook endpoints DELETE controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/delete'] = 'canto-dam-integrator/asset-sync/asset-delete-webhook';
            }
        );

        // Register the webhook endpoints UPDATE controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/update'] = 'canto-dam-integrator/asset-sync/asset-update-webhook';
            }
        );

        // Register the webhook endpoints MASS SYNC controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/mass-sync'] = 'canto-dam-integrator/asset-sync/asset-mass-sync-webhook';
            }
        );

        // Register the webhook endpoints DAM asset upload controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/dam-asset-upload'] = 'canto-dam-integrator/asset-sync/dam-asset-upload';
            }
        );

        // Register the webhook endpoints DAM asset removal controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['canto-dam-integrator/dam-asset-removal'] = 'canto-dam-integrator/asset-sync/dam-asset-removal';
            }
        );

        // Register the custom field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = DAMAsset::class;
            }
        );
    }

    /**
     * @return string|null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    protected function settingsHtml(): ?string {
        return \Craft::$app->getView()->renderTemplate(
            'canto-dam-integrator/settings',
            [ 'settings' => $this->getSettings() ]
        );
    }

    /**
     * @return Model|null
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}