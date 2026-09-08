<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            $broadcaster = config('broadcasting.default');
            if ($broadcaster === 'null' || empty($broadcaster)) {
                if (config('broadcasting.connections.pusher.key')) {
                    $broadcaster = 'pusher';
                } elseif (config('broadcasting.connections.reverb.key')) {
                    $broadcaster = 'reverb';
                }
            }
        @endphp
        <meta name="broadcaster" content="{{ $broadcaster }}">
        @if($broadcaster === 'pusher')
            <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
            <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') ?: 'sa1' }}">
        @elseif($broadcaster === 'reverb')
            <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
            <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') }}">
            <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
            <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
