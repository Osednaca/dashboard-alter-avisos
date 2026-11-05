<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#060f19">
        <link rel="manifest" href="/manifest.webmanifest">

        <title inertia>{{ config('branding.name', 'Alter') }}</title>
        <link rel="icon" type="image/png" href="{{ config('branding.logo_url', '/brand/alter-logo-20261003.png') }}">
        <link rel="apple-touch-icon" href="/icons/alter-192-20261003.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        >

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-canvas font-sans text-fg antialiased">
        @inertia
    </body>
</html>
