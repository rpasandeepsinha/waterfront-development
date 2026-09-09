<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;

$application = Application::getInstance();

return [
    //file system
    'default' => Env::get('FILESYSTEM_DRIVER', 'local'),

    //cloud filesystem
    'cloud' => Env::get('FILESYSTEM_CLOUD', 's3'),

    //filesystem disks. supported: local, ftp, s3 & rackspace
    'disks' => [
        'private' => [
            'driver'       => 'local',
            'root'         => $application->storagePath('app'),
            'csr'          => $application->storagePath('app') . '/csr',
            'certificates' => $application->storagePath('app') . '/certificates',
            'exports'      => $application->storagePath('app') . '/exports',
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => $application->publicPath(''),
            'page-texts' => $application->publicPath('uploads/page-texts'),
            'users'      => $application->publicPath('uploads/users'),
            'url'        => Env::getRepository()->get('APP_URL') . '/uploads',
            'visibility' => 'public',
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => Env::get('AWS_ACCESS_KEY_ID'),
            'secret'                  => Env::get('AWS_SECRET_ACCESS_KEY'),
            'region'                  => Env::get('AWS_DEFAULT_REGION'),
            'bucket'                  => Env::get('AWS_BUCKET'),
            'url'                     => Env::get('AWS_URL'),
            'endpoint'                => Env::get('AWS_ENDPOINT'),
            'use_path_style_endpoint' => Env::get('AWS_USE_PATH_STYLE_ENDPOINT'),
        ],

        'products' => [
            'driver'                  => 's3',
            'key'                     => Env::get('S3_PRODUCTS_ACCESS_KEY_ID'),
            'secret'                  => Env::get('S3_PRODUCTS_SECRET_ACCESS_KEY'),
            'region'                  => Env::get('S3_PRODUCTS_DEFAULT_REGION'),
            'bucket'                  => Env::get('S3_PRODUCTS_BUCKET'),
            'url'                     => Env::get('S3_PRODUCTS_URL'),
            'endpoint'                => Env::get('S3_PRODUCTS_ENDPOINT'),
            'use_path_style_endpoint' => Env::get('S3_PRODUCTS_USE_PATH_STYLE_ENDPOINT'),
        ],

        'translations-s3' => [
            'driver'                  => 's3',
            'key'                     => Env::get('S3_TRANSLATIONS_ACCESS_KEY_ID'),
            'secret'                  => Env::get('S3_TRANSLATIONS_SECRET_ACCESS_KEY'),
            'region'                  => Env::get('S3_TRANSLATIONS_DEFAULT_REGION'),
            'bucket'                  => Env::get('S3_TRANSLATIONS_BUCKET'),
            'url'                     => Env::get('S3_TRANSLATIONS_URL'),
            'endpoint'                => Env::get('S3_TRANSLATIONS_ENDPOINT'),
            'use_path_style_endpoint' => Env::get('S3_TRANSLATIONS_USE_PATH_STYLE_ENDPOINT'),
            'options'                 => ['CacheControl' => 'max-age=300, no-transform, public'],
        ],

        'imports' => [
            'driver'                  => 's3',
            'key'                     => Env::get('AWS_ACCESS_KEY_ID'),
            'secret'                  => Env::get('AWS_SECRET_ACCESS_KEY'),
            'region'                  => Env::get('AWS_DEFAULT_REGION'),
            'bucket'                  => Env::get('AWS_BUCKET_IMPORTS'),
            'url'                     => Env::get('AWS_URL'),
            'endpoint'                => Env::get('AWS_ENDPOINT'),
            'use_path_style_endpoint' => Env::get('AWS_USE_PATH_STYLE_ENDPOINT'),
        ],

        'uiconfig' => [
            'driver'                  => 's3',
            'key'                     => Env::get('S3_UICONFIG_ACCESS_KEY_ID'),
            'secret'                  => Env::get('S3_UICONFIG_SECRET_ACCESS_KEY'),
            'region'                  => Env::get('S3_UICONFIG_DEFAULT_REGION'),
            'bucket'                  => Env::get('S3_UICONFIG_BUCKET'),
            'url'                     => Env::get('S3_UICONFIG_URL'),
            'endpoint'                => Env::get('S3_UICONFIG_ENDPOINT'),
            'use_path_style_endpoint' => Env::get('S3_UICONFIG_USE_PATH_STYLE_ENDPOINT'),
        ],

        'translations' => [
            'driver' => 'local',
            'root'   => $application->resourcePath('lang'),
        ],
    ],
];
