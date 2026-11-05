<?php

return [
    'provider_hosts' => [
        'youtube' => ['youtube.com', 'youtube-nocookie.com', 'ytimg.com', 'googlevideo.com', 'google.com', 'gstatic.com', 'doubleclick.net', 'googleadservices.com'],
        'twitch' => ['twitch.tv', 'twitchcdn.net', 'ttvnw.net', 'jtvnw.net', 'amazon-adsystem.com', 'google.com', 'gstatic.com', 'doubleclick.net'],
        'kick' => ['kick.com', 'kickstatic.com', 'live-video.net', 'cloudfront.net'],
    ],
    // Exact hosts for source URLs; Android also permits their subdomains for HLS resources.
    // No schemes or wildcards. Players fetch HLS directly; PHP never proxies it.
    'hls_hosts' => array_values(array_filter(array_map('trim', explode(',', env('LIVE_HLS_HOSTS', ''))))),
    'twitch_parents' => array_values(array_filter(array_map('trim', explode(',', env('LIVE_TWITCH_PARENTS', parse_url(env('APP_URL', ''), PHP_URL_HOST) ?: ''))))),
    'connect_timeout_seconds' => (int) env('LIVE_CONNECT_TIMEOUT_SECONDS', 30),
    'retry_seconds' => (int) env('LIVE_RETRY_SECONDS', 30),
    'max_retry_seconds' => (int) env('LIVE_MAX_RETRY_SECONDS', 300),
    'unverified_seconds' => (int) env('LIVE_UNVERIFIED_SECONDS', 300),
];
