<?php

namespace rosas\dam\models;

class Constants {

    const ASSET_METADATA_FIELDS = [
        "filename" => ["name"],
        "tags" => ["tag"],
        "altText" => ["additional", "Alt Text **EN**"],
        "titleSpanish" => ["additional", "Title **ES**"],
        "titleEnglish" => ["additional", "Title **EN**"],
        "thumbnailUrl" => ["url", "directUrlPreview"],
        "highResJpegUrl" => ["url", "HighJPG"],
        "pngUrl" => ["url", "PNG"],
        "directImageUrl" => ["url", "directUrlOriginal"],
        "damId" => ["id"],
        "description" => ["description"]
    ];

}