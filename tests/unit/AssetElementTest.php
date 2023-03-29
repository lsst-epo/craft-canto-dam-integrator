<?php 

namespace rosas\dam\tests;

use Craft;
use Codeception\Test\Unit;
use UnitTester;
use rosas\dam\elements\Asset;

class AssetElementTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }


    public function testAssetConstructorHandlesExpectedValues() {
        $test_id = 1;
        $test_key = "test_key";
        $test_value = "test_value";
        $asset_id = 2;

        $config = [
            "dam_meta_key" => $test_key,
            "dam_meta_value" => $test_value,
            "id" => $test_id,
            "assetId" => $asset_id
        ];
        $asset = new Asset($config);

        $this->assertSame(
            $asset->id,
            $test_id);

        $this->assertSame(
            $asset->assetId,
            $asset_id);
        
        $this->assertSame(
            $asset->dam_meta_key,
            $test_key);

        $this->assertSame(
            $asset->dam_meta_value,
            $test_value);
    }
}