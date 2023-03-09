<?php

namespace rosas\dam\volumes;

use Craft;
use craft\events\DefineBehaviorsEvent;
use craft\models\Volume;
use craft\base\FlysystemVolume;
use League\Flysystem\Filesystem;
use craft\base\FsInterface;
use craft\fs\MissingFs;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use craft\events\DefineRulesEvent;

/**
 *
 *
 * @property-read null|string $settingsHtml
 */
class DAMVolume extends Volume
{

    public ?string $dummySetting = ""; // Do not remove, Craft expects at least one volume setting for some reason and removing this will break the plugin/volumes

    public ?string $handle = "cantoDam";
    private ?string $_fsHandle = "cantoDamFsHandle";


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
     * @return string
     */
    public static function displayName(): string
    {
        return 'Canto DAM'; // return display name from settings
    }

    /**
     * @return string|null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getSettingsHtml(): ?string
    {
        Craft::info("Inside of DAMVolume::getSettingsHtml()", "maddie");
        return Craft::$app->getView()->renderTemplate('_components/fs/Local/settings.twig',
            [
                'volume' => $this,
            ]);
    }

    /**
     * @return FsInterface
     * @throws InvalidConfigException
     */
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

}