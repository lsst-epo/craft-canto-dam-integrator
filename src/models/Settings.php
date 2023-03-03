<?php

namespace rosas\dam\models;

use Craft;
use craft\base\Model;

/**
 *
 */

/**
 *
 */
class Settings extends Model
{
    
    public $appId;
    public $secretKey;
    public $authEndpoint;
    public $retrieveAssetMetadataEndpoint;
    public $damVolume;

    /**
     * @return void
     */
    public function init(): void {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function __construct(array $config = []) {
        parent::__construct($config);
    }

    /**
     * @return string
     */
    public function getRetrieveAssetMetadataEndpoint(): string {
        return Craft::parseEnv($this->retrieveAssetMetadataEndpoint);
    }

    /**
     * @return string
     */
    public function getAuthEndpoint(): string {
        return Craft::parseEnv($this->authEndpoint);
    }

    /**
     * @return string
     */
    public function getSecretKey(): string {
        return Craft::parseEnv($this->secretKey);
    }

    /**
     * @return string
     */
    public function getAppId(): string {
        return Craft::parseEnv($this->appId);
    }

    /**
     * @return array[]
     */
    public function rules(): array
    {
        return [
            [['authEndpoint', 'appId', 'secretKey', 'retrieveAssetMetadataEndpoint', 'damVolume'], 'required']
        ];
    }

    /**
     * @return array
     */
    /**
     * @return array
     */
    public function getVolumes() {
        $rawVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $vols = [];
        array_push($vols, array(
            "label" => "- Select Volume -",
            "value" => ""
        ));
        foreach($rawVolumes as $vol) {
            array_push($vols, array(
                "label" => $vol["name"],
                "value" => $vol["handle"]
            ));
        }
        return $vols;
    }

    /**
     * @return mixed|null
     */
    /**
     * @return mixed|null
     */
    public function getVolumeId() {
        if($this->damVolume != null) {
            return Craft::$app->getVolumes()->getVolumeByHandle($this->damVolume)["id"];
        } else {
            return null;
        }
        
    }
}