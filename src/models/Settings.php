<?php

namespace lsst\dam\models;

use Craft;
use craft\base\Model;

/**
 *
 *
 * @property-read null|mixed $volumeId
 * @property-read array $volumes
 */
class Settings extends Model
{
    
    public string $appId = "";
    public string $secretKey = "";
    public string $authEndpoint = "";
    public string $retrieveAssetMetadataEndpoint = "";
    public mixed $damVolume = null;

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
    public function getVolumes(): array {
        $rawVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $vols = [];
        $vols[] = array(
            "label" => "- Select Volume -",
            "value" => ""
        );
        foreach($rawVolumes as $vol) {
            $vols[] = array(
                "label" => $vol["name"],
                "value" => $vol["handle"]
            );
        }
        return $vols;
    }

    /**
     * @return mixed|null
     */
    /**
     * @return mixed|null
     */
    public function getVolumeId(): mixed
    {
        if($this->damVolume != null) {
            return Craft::$app->getVolumes()->getVolumeByHandle($this->damVolume)["id"];
        } else {
            return null;
        }
        
    }
}