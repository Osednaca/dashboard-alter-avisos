<?php

return [
    'package' => 'tv.signage.player',
    'base_url' => 'https://signage.finespublicidad.com/updates/android',
    'directory' => storage_path('app/public/updates/android'),
    'staging_directory' => storage_path('app/private/android-update-staging'),
    'max_upload_kb' => 128 * 1024,
    'unzip_binary' => 'unzip',
];
