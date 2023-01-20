<?php
namespace rosas\dam\services;

use \Datetime;
use Craft;
use yii\base\Component;
use craft\elements\Asset;
use craft\services\Assets as AssetsService;
use rosas\dam\services\Elements;
use craft\helpers\Json;
use craft\events\GetAssetThumbUrlEvent;
use craft\events\GetAssetUrlEvent;
use craft\models\VolumeFolder;
use \rosas\dam\Plugin;

// DB access
use rosas\dam\records\VolumeFolders;
use rosas\dam\db\AssetMetadata;
use rosas\dam\elements\db\DAMAssetQuery;

class Assets extends Component
{
    private static string $FILENAME_URL_PREFIX = "https://rubin.canto.com/direct/";

    private $authToken;

    private $assetMetadata;

    public function __construct($config = []) {
        parent::__construct($config);
    }

    public function init() {
        parent::init();
    }

    public function getVolumes() {
        $rawVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $vols = [];
        foreach($rawVolumes as $vol) {
            array_push($vols, array(
                "name" => $vol["name"],
                "handle" => $vol["handle"]
            ));
        }
        return $vols;
    }

    public function saveDamAsset($damId, $elementId, $fieldId) {
        // Ensure settings are saved before attempting any requests
        if(isset(\rosas\dam\Plugin::getInstance()->getSettings()->retrieveAssetMetadataEndpoint) &&
           isset(\rosas\dam\Plugin::getInstance()->getSettings()->authEndpoint) &&
           isset(\rosas\dam\Plugin::getInstance()->getSettings()->secretKey) &&
           isset(\rosas\dam\Plugin::getInstance()->getSettings()->appId)) {
            try {
                $this->authToken = $this->getAuthToken();
                if($this->authToken != null && !empty($this->authToken)) {
                    $this->assetMetadata = $this->getAssetMetadata($damId);
                    $this->assetMetadata["epo_etc"]["elementId"] = $elementId;
                    $this->assetMetadata["epo_etc"]["fieldId"] = $fieldId;
                    if(in_array('errorMessage', $this->assetMetadata)) {
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
                Craft::info($e);
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
                    "retrieveAssetMetadataEndpointIsSet" => isset(\rosas\dam\Plugin::getInstance()->getSettings()->retrieveAssetMetadataEndpoint),
                    "authEndpointIsSet" => isset(\rosas\dam\Plugin::getInstance()->getSettings()->authEndpoint),
                    "secretKeyIsSet" => isset(\rosas\dam\Plugin::getInstance()->getSettings()->secretKey),
                    "appIdIsSet" => isset(\rosas\dam\Plugin::getInstance()->getSettings()->appId)
                ]
            ];
        }
        
    }

    private function _propagateFolders($path, $damVolId) {
        $pathArr = explode('/', $path);
        $parentId = null;
        foreach($pathArr as $folderName) {
            $result = VolumeFolders::getIdsByFolderName($folderName);
            $newFolder = new VolumeFolder();

            // Determine parentId for folder
            if($parentId == null) {
                $parentId = $damVolId;
            } else {
                if($result != null) {
                    if(array_search($folderName, $pathArr) != (count($pathArr)-1)) {
                        $parentId = $result["id"];
                    }
                }
            }
            $newFolder->parentId = $parentId;
            $newFolder->name = $folderName;
            $newFolder->volumeId = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = Plugin::getInstance()->getSettings()->damVolume)["id"];
            $parentId = AssetsService::storeFolderRecord($newFolder);
            $newFolderRecord = VolumeFolders::getIdsByFolderName($folderName);
            $parentId = $newFolderRecord["id"];
        }

        return $parentId;
    }

    private function saveAssetMetadata() {
        $damVolume = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = Plugin::getInstance()->getSettings()->damVolume);
        $damVolResult = VolumeFolders::getIdsByFolderName($damVolume["name"]);

        $newAsset = new Asset();
        $newAsset->avoidFilenameConflicts = true;
        $newAsset->setScenario(Asset::SCENARIO_CREATE);
        $filename = strtolower($this->assetMetadata["url"]["directUrlOriginal"]);
        $newAsset->filename = str_replace($FILENAME_URL_PREFIX, "", $filename);
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
        $now = new DateTime();
        $newAsset->dateModified = $now->format('Y-m-d H:i:s');
        $elements = new Elements();
	
        $success = $elements->saveElement($newAsset, false, true, true, $this->assetMetadata);

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
     * @param \craft\events\DefineAssetThumbUrlEvent $event
     *
     * @return null|string
     */
    public function handleGetAssetThumbUrlEvent(\craft\events\DefineAssetThumbUrlEvent $event) {
        $url = $event->url;
        $asset = $event->asset;
    
        if(Plugin::getInstance()->getSettings()->damVolume != null) {
            $settingsVolID = Craft::$app->getVolumes()->getVolumeByHandle($getAssetMetadataEndpoint = Plugin::getInstance()->getSettings()->damVolume)["id"];
            if($asset->getVolumeId() == $settingsVolID) {
                $rows = AssetMetadata::find()
                    ->where(['assetId' => $asset->id, 'dam_meta_key' => 'thumbnailUrl'])
                    ->one();
                if($rows != null && array_key_exists("dam_meta_value", $rows)) {
                    return str_replace('"', '', $rows['dam_meta_value']);
                }
            }
        }
    }

    /**
     * Get asset metadata
     */ 
    public function getAssetMetadata($assetId) {
        try {
            $client = Craft::createGuzzleClient();
            $baseUrl = \rosas\dam\Plugin::getInstance()->getSettings()->getRetrieveAssetMetadataEndpoint();
            if(substr($baseUrl, (strlen($baseUrl) - 1), strlen($baseUrl)) != '/') {
                $baseUrl = $baseUrl . '/';
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
        }
    }

    /**
     *  Private function for using the app ID and secret key to get an auth token
     */ 
    public function getAuthToken($validateOnly = false) : string {
        $client = Craft::createGuzzleClient();
        $appId = \rosas\dam\Plugin::getInstance()->getSettings()->getAppId();
        $secretKey = \rosas\dam\Plugin::getInstance()->getSettings()->getSecretKey();
        $authEndpoint = \rosas\dam\Plugin::getInstance()->getSettings()->getAuthEndpoint();

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
                'errorMessage' => 'Plugin is not configured to authenticate!'
            ];
        }

    }

    /**
     * Returns the element’s full URL.
     *
     * @param string|array|null $transform A transform handle or configuration that should be applied to the
     * image If an array is passed, it can optionally include a `transform` key that defines a base transform
     * which the rest of the settings should be applied to.
     * @param bool|null $generateNow Whether the transformed image should be generated immediately if it doesn’t exist. If `null`, it will be left
     * up to the `generateTransformsBeforePageLoad` config setting.
     * @return string|null
     * @throws InvalidConfigException
     */
    public function getUrl(\craft\events\DefineAssetUrlEvent $event)
    {
        $asset = $event->asset;
        $url = $event->url;

        return $url;
    }



}
