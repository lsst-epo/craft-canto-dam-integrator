<?php

namespace rosas\dam\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    
    public $appId;
    public $secretKey;
    public $authEndpoint;
    public $retrieveAssetMetadataEndpoint;
    public $damVolume;

    public function init(): void {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function __construct(array $config = []) {
        parent::__construct($config);
    }

    public function getRetrieveAssetMetadataEndpoint(): string {
        return Craft::parseEnv($this->retrieveAssetMetadataEndpoint);
    }

    public function getAuthEndpoint(): string {
        return Craft::parseEnv($this->authEndpoint);
    }

    public function getSecretKey(): string {
        return Craft::parseEnv($this->secretKey);
    }

    public function getAppId(): string {
        return Craft::parseEnv($this->appId);
    }

    public function rules(): array
    {
        return [
            [['authEndpoint', 'appId', 'secretKey', 'retrieveAssetMetadataEndpoint', 'damVolume'], 'required']
        ];
    }

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

    public function getVolumeId() {
        if($this->damVolume != null) {
            return Craft::$app->getVolumes()->getVolumeByHandle($this->damVolume)["id"];
        } else {
            return null;
        }
        
    }
}