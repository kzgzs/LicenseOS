<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Cloudflare R2 (S3-compatible, egress-free)
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET', ''),
            'url' => env('R2_CUSTOM_DOMAIN', ''),
            'endpoint' => env('R2_ENDPOINT', ''),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        // Alibaba Cloud OSS (S3-compatible endpoint)
        'aliyun-oss' => [
            'driver' => 's3',
            'key' => env('ALIYUN_OSS_ACCESS_KEY_ID'),
            'secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET'),
            'region' => 'oss-cn-hangzhou',
            'bucket' => env('ALIYUN_OSS_BUCKET', ''),
            'url' => env('ALIYUN_OSS_CUSTOM_DOMAIN', ''),
            'endpoint' => env('ALIYUN_OSS_ENDPOINT', ''),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        // Tencent Cloud COS (S3-compatible endpoint)
        'tencent-cos' => [
            'driver' => 's3',
            'key' => env('TENCENT_COS_SECRET_ID'),
            'secret' => env('TENCENT_COS_SECRET_KEY'),
            'region' => env('TENCENT_COS_REGION', 'ap-guangzhou'),
            'bucket' => env('TENCENT_COS_BUCKET', ''),
            'url' => env('TENCENT_COS_CUSTOM_DOMAIN', ''),
            'endpoint' => env('TENCENT_COS_ENDPOINT', ''),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        // Qiniu Cloud (S3-compatible endpoint)
        'qiniu' => [
            'driver' => 's3',
            'key' => env('QINIU_ACCESS_KEY'),
            'secret' => env('QINIU_SECRET_KEY'),
            'region' => 'auto',
            'bucket' => env('QINIU_BUCKET', ''),
            'url' => env('QINIU_CUSTOM_DOMAIN', ''),
            'endpoint' => env('QINIU_ENDPOINT', ''),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Storage Settings
    |--------------------------------------------------------------------------
    | product_disk: The disk to use for NEW uploads (local or r2).
    | When set to 'r2', new uploads go to Cloudflare R2.
    | Existing local files remain accessible via fallback.
    */
    'product_disk' => env('PRODUCT_STORAGE_DISK', 'public'),

];
