<?php
namespace lsst\dam\services;

use craft\errors\InvalidFieldException;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineAssetThumbUrlEvent;
use \Datetime;
use Craft;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;
use craft\elements\Asset;
use craft\services\Assets as AssetsService;
use craft\helpers\Json;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\models\VolumeFolder;
use lsst\dam\DamPlugin;
use lsst\dam\records\VolumeFolders;
use lsst\dam\db\AssetMetadata;
use yii\base\Exception;

/**
 *
 *
 * @property-read array $volumes
 */
class Assets extends Component
{
    private static string $FILENAME_URL_PREFIX = "https://rubin.canto.com/direct/";

    private string $authToken;

    private mixed $assetMetadata;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * @return array
     */
    public function getVolumes(): array
    {
        $rawVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $vols = [];
        foreach($rawVolumes as $vol) {
            $vols[] = array(
                "name" => $vol["name"],
                "handle" => $vol["handle"]
            );
        }
        return $vols;
    }

    /**
     * @param $damId
     * @param $elementId
     * @param $fieldId
     * @return array|string[]|void
     * @throws \Throwable
     */
    public function saveDamAsset($damId, $elementId, $fieldId) {
        // Ensure settings are saved before attempting any requests
        if(isset(DamPlugin::getInstance()->getSettings()->retrieveAssetMetadataEndpoint,
                 DamPlugin::getInstance()->getSettings()->authEndpoint,
                 DamPlugin::getInstance()->getSettings()->secretKey,
                 DamPlugin::getInstance()->getSettings()->appId)) {
            try {
                $this->authToken = $this->getAuthToken();
                if(!empty($this->authToken)) {
                    $this->assetMetadata = $this->getAssetMetadata($damId);
                    $this->assetMetadata["epo_etc"]["elementId"] = $elementId;
                    $this->assetMetadata["epo_etc"]["fieldId"] = $fieldId;
                    if(in_array('errorMessage', $this->assetMetadata, true)) {
                        return [
                            "status" => "error",
                            "message" => "An error occurred while fetching the asset from Canto!",
                            "details" => [
                                "error" => $this->assetMetadata
                            ]
                        ];
                    } else {
                        return $this->saveAssetMetadata();
                    }
                }
            } catch (\Exception $e) {
                Craft::info($e->getMessage(), "UDAMI");
                return [
                    "status" => "error",
                    "message" => "An error occurred while attempting to fetch the asset from Canto!",
                    "details" => [
                        "error" => $e->getTraceAsString(),
                        "errorStr" => strval($e),
                        "errorMessage" => $e->getMessage(),
                        "errorLineNumber" => $e->getLine()
                    ]
                ];
            }
        } else {
            return [
                "status" => "error",
                "message" => "The plugin is configured incorrectly!",
                "details" => [
                    "retrieveAssetMetadataEndpointIsSet" => isset(DamPlugin::getInstance()->getSettings()->retrieveAssetMetadataEndpoint),
                    "authEndpointIsSet" => isset(DamPlugin::getInstance()->getSettings()->authEndpoint),
                    "secretKeyIsSet" => isset(DamPlugin::getInstance()->getSettings()->secretKey),
                    "appIdIsSet" => isset(DamPlugin::getInstance()->getSettings()->appId)
                ]
            ];
        }
        
    }

    /**
     * @param $path
     * @param $damVolId
     * @return mixed|null
     */
    private function _propagateFolders($path, $damVolId): mixed
    {
        $pathArr = explode('/', $path);
        $parentId = null;
        foreach($pathArr as $folderName) {
            $result = VolumeFolders::getIdsByFolderName($folderName);
            $newFolder = new VolumeFolder();

            // Determine parentId for folder
            if($parentId == null) {
                $parentId = $damVolId;
            } else if(($result != null) && array_search($folderName, $pathArr) != (count($pathArr) - 1)) {
                $parentId = $result["id"];
            }
            $newFolder->parentId = $parentId;
            $newFolder->name = $folderName;
            $newFolder->volumeId = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = DamPlugin::getInstance()->getSettings()->damVolume)["id"];
            $assetsService = new AssetsService();
            $parentId = $assetsService->storeFolderRecord($newFolder);
            $newFolderRecord = VolumeFolders::getIdsByFolderName($folderName);
            $parentId = $newFolderRecord["id"];
        }

        return $parentId;
    }

    /**
     * @return string[]
     * @throws Exception
     * @throws InvalidFieldException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    private function saveAssetMetadata(): array
    {
        $damVolume = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = DamPlugin::getInstance()->getSettings()->damVolume);
        $damVolResult = VolumeFolders::getIdsByFolderName($damVolume["name"]);

        $newAsset = new Asset();
        $newAsset->avoidFilenameConflicts = true;
        $newAsset->setScenario(Asset::SCENARIO_CREATE);
        $filename = strtolower($this->assetMetadata["url"]["directUrlOriginal"]);
        $newAsset->filename = str_replace("https://rubin.canto.com/direct/", "", $filename);
        $newAsset->kind = "image";
        $newAsset->setHeight($this->assetMetadata["height"]);
        $newAsset->setWidth($this->assetMetadata["width"]);
        $newAsset->size = $this->assetMetadata["metadata"]["Asset Data Size (Long)"];

        if(array_key_exists("relatedAlbums", $this->assetMetadata) &&
           count($this->assetMetadata["relatedAlbums"]) > 0 &&
           array_key_exists("namePath", $this->assetMetadata["relatedAlbums"][0])) {
            $newAsset->folderId = $this->_propagateFolders($this->assetMetadata["relatedAlbums"][0]["namePath"], $damVolResult["id"]);
        } else {
            $newAsset->folderId = $damVolResult["id"];
        }
        
        $newAsset->firstSave = true;
        $newAsset->propagateAll = false; //changed from true for debugging purposes
        $newAsset->dateModified = new DateTime();
        $elementService = new Elements();
        $success = $elementService->saveElement($newAsset, false, true, true, $this->assetMetadata);

        if($success) {
            return [
                "status" => "success"
            ];
        } else {
            return [
                "status" => "error",
                "message" => "Error while attempting to save the asset metadata!"
            ];
        }
    }

    /**
     * Handle responding to EVENT_GET_ASSET_THUMB_URL events
     *
     * @param DefineAssetThumbUrlEvent $event
     *
     * @return null|string
     */
    public function handleGetAssetThumbUrlEvent(DefineAssetThumbUrlEvent $event): ?string
    {
        $url = $event->url;
        $asset = $event->asset;
    
        if(DamPlugin::getInstance()->getSettings()->damVolume != null) {
            $settingsVolID = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = DamPlugin::getInstance()->getSettings()->damVolume)["id"];
            if($asset->getVolumeId() == $settingsVolID) {
                $rows = AssetMetadata::find()
                    ->where(['assetId' => $asset->id, 'dam_meta_key' => 'thumbnailUrl'])
                    ->one();
                if($rows != null && isset($rows["dam_meta_value"])) {
                    return str_replace('"', '', $rows['dam_meta_value']);
                }
            }
        }
        return null;
    }

    /**
     * Get asset metadata
     */ 
    public function getAssetMetadata($assetId) {
        try {
            $client = Craft::createGuzzleClient();
            $baseUrl = DamPlugin::getInstance()->getSettings()->getRetrieveAssetMetadataEndpoint();
            if(substr($baseUrl, (strlen($baseUrl) - 1), strlen($baseUrl)) != '/') {
                $baseUrl .= '/';
            }
            $getAssetMetadataEndpoint = $baseUrl . $assetId;

            if(!isset($this->authToken)) {
                $this->authToken = $this->getAuthToken();
            }

            $bearerToken = "Bearer {$this->authToken}";
            $response = $client->request("GET", $getAssetMetadataEndpoint, ['headers' => ["Authorization" => $bearerToken]]);
            $body = $response->getBody();

            if(!is_array(Json::decodeIfJson($body))) {
                return [
                    "status" => "error",
                    'errorMessage' => 'Asset metadata retrieval failed!'
                ];
            } else {
                return Json::decodeIfJson($body);
            }
            
        } catch (Exception $e) {
            Craft::info("An exception occurred in getAssetMetadata()", "UDAMI");
            return $e;
        } catch (GuzzleException $e) {
        }
    }

    /**
     *  Private function for using the app ID and secret key to get an auth token
     */ 
    public function getAuthToken($validateOnly = false) : string {
        $client = Craft::createGuzzleClient();
        $appId = DamPlugin::getInstance()->getSettings()->getAppId();
        $secretKey = DamPlugin::getInstance()->getSettings()->getSecretKey();
        $authEndpoint = DamPlugin::getInstance()->getSettings()->getAuthEndpoint();

        if($appId != null &&
           $secretKey != null &&
           $authEndpoint != null) {
            
            // Inject appId if the token is included in the URL
            $authEndpoint = str_replace("{appId}", $appId, $authEndpoint);

            // Inject secretKey if the token is included in the URL
            $authEndpoint = str_replace("{secretKey}", $secretKey, $authEndpoint);

            // Get auth token
            try {
                $response = $client->post($authEndpoint);
                $body = $response->getBody();
            } catch (\Exception $e) {
                return $e->getMessage();
            }
            

            // Extract auth token from response
            if(!$validateOnly) {
                $authTokenDecoded = Json::decodeIfJson($body);
                $authToken = $authTokenDecoded["accessToken"];
        
                return $authToken;
            } else {
                Craft::info("An exception occurred in getAuthToken()", "UDAMI");
                return [
                    "status" => "error",
                    'errorMessage' => 'An error occurred fetching auth token!'
                ];
            }
        } else {
            Craft::info("An exception occurred in getAuthToken()", "UDAMI");
            return [
                "status" => "error",
                'errorMessage' => 'DamPlugin is not configured to authenticate!'
            ];
        }

    }

    /**
     * Returns the element’s full URL.
     *
     * @param DefineAssetUrlEvent $event
     * @return string|null
     */
    public function getUrl(\craft\events\DefineAssetUrlEvent $event): ?string
    {
        $asset = $event->asset;
        return $event->url;
    }



}
