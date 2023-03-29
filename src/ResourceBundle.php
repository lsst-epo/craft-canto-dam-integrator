<?php

namespace rosas\dam;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ResourceBundle extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@rosas/dam/resources';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'script.js',
            'test.js'
        ];

        $this->css = [
            'base.css'
        ];

        parent::init();
    }
}