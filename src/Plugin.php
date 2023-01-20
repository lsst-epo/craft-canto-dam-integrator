<?php
namespace rosas\dam;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Volumes;
use yii\base\Event;
use craft\web\twig\variables\CraftVariable;
use rosas\dam\volumes\DAMVolume;
use rosas\dam\services\Assets;
use rosas\dam\fields\DAMAsset;
use craft\services\Assets as CraftAssets;
use yii\base\Behavior;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use rosas\dam\gql\queries\DAMAssetQuery;
use craft\services\Gql;
use craft\events\RegisterGqlQueriesEvent;
use craft\web\UrlManager;
use craft\services\Fields;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;

// Craft 4
use craft\services\Fs;
use craft\elements\Asset;

class Plugin extends \craft\base\Plugin
{
    // added asset extends
    public static $plugin;

    public bool $hasCpSettings = true;

    public function __construct($id, $parent = null, array $config = []) {
        $config["components"] = [
            'assets' => Assets::class
        ];
        parent::__construct($id, $parent, $config);
    }

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Bind Assets service to be scoped to this plugin
        $this->setComponents([
            'assets' => \rosas\dam\services\Assets::class,
        ]);

        // Add permission for Editors
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Editor'] = [
                    'accessPlugin-universal-dam-integrator' => [
                        'label' => 'Use DAM Integration Plugin',
                    ],
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
            \craft\services\Assets::class,
            \craft\services\Assets::EVENT_DEFINE_THUMB_URL,
            function (\craft\events\DefineAssetThumbUrlEvent $event) {
                Craft::debug(
                    '\craft\services\Assets::EVENT_DEFINE_THUMB_URL',
                    __METHOD__
                );
                // Return the URL to the asset URL or null to let Craft handle it
                $event->url = Plugin::$plugin->assets->handleGetAssetThumbUrlEvent($event);
            }
        );

        // Register DAM remote volume type
        Event::on(
            Fs::class,
            Fs::EVENT_REGISTER_FILESYSTEM_TYPES,
                function(RegisterComponentTypesEvent $event) {
                $event->types[] = DAMVolume::class;
            }
        );

        // Register getAssetUrl event  
        Event::on(
            \craft\elements\Asset::class,
            \craft\elements\Asset::EVENT_DEFINE_URL,
                function(\craft\events\DefineAssetUrlEvent $event) {
                    $event->url = Plugin::$plugin->assets->getUrl($event);
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
                $event->rules['universal-dam-integrator/create'] = 'universal-dam-integrator/asset-sync/asset-create-webhook';
            }
        );

        // Register the webhook endpoints DELETE controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['universal-dam-integrator/delete'] = 'universal-dam-integrator/asset-sync/asset-delete-webhook';
            }
        );

        // Register the webhook endpoints UPDATE controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['universal-dam-integrator/update'] = 'universal-dam-integrator/asset-sync/asset-update-webhook';
            }
        );

        // Register the webhook endpoints MASS SYNC controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['universal-dam-integrator/mass-sync'] = 'universal-dam-integrator/asset-sync/asset-mass-sync-webhook';
            }
        );

        // Register the webhook endpoints DAM asset upload controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['universal-dam-integrator/dam-asset-upload'] = 'universal-dam-integrator/asset-sync/dam-asset-upload';
            }
        );

        // Register the webhook endpoints DAM asset removal controller
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['universal-dam-integrator/dam-asset-removal'] = 'universal-dam-integrator/asset-sync/dam-asset-removal';
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

    protected function settingsHtml(): ?string {
        return \Craft::$app->getView()->renderTemplate(
            'universal-dam-integrator/settings',
            [ 'settings' => $this->getSettings() ]
        );
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new \rosas\dam\models\Settings();
    }
}