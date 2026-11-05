<?php

return [
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Media storage
    |--------------------------------------------------------------------------
    | Media is never streamed through Laravel in production. Assets live on an
    | S3 compatible disk (Cloudflare R2 / AWS S3) and are delivered via CDN.
    */
    'media_disk' => env('MEDIA_DISK', 'public'),

    'device' => [
        'token_ttl_days' => (int) env('DEVICE_TOKEN_TTL_DAYS', 90),
        'activation_ttl_minutes' => (int) env('DEVICE_ACTIVATION_TTL_MINUTES', 60),
        'offline_after_minutes' => (int) env('DEVICE_OFFLINE_AFTER_MINUTES', 15),
        'heartbeat_retention_days' => (int) env('DEVICE_HEARTBEAT_RETENTION_DAYS', 14),
        'playback_batch_limit' => (int) env('DEVICE_PLAYBACK_BATCH_LIMIT', 500),
    ],

    'uploads' => [
        'max_image_kb' => (int) env('UPLOAD_MAX_IMAGE_KB', 10240),
        'max_video_kb' => (int) env('UPLOAD_MAX_VIDEO_KB', 512000),
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'video_mimes' => ['video/mp4'],
    ],

    'analytics' => [
        'cache_ttl' => (int) env('ANALYTICS_CACHE_TTL', 300),
        'default_range_days' => 30,
    ],
];
