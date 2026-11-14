<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="broadcaster" content="pusher">
        <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
        <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') ?: 'sa1' }}">

        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" href="/logo-provisorio-papelera.png?v=2" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

        @if (! \Illuminate\Support\Facades\Vite::isRunningHot())
            <link rel="preload" href="/fonts/GoogleSans-VariableFont.woff2" as="font" type="font/woff2" crossorigin>
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Papelera') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
