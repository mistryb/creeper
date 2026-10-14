<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Paper, so the first paint is already the right colour. --}}
        <style>
            html {
                background-color: #f5f4ec;
            }
        </style>

        {{-- The card a link unfurls into on Twitter/X, Slack, iMessage and
             LinkedIn. Their crawlers do not run JavaScript, so these have to be
             in the server-rendered shell rather than set with Inertia's <Head>.
             public/og-image.png is 1200×630, the size every one of them crops to. --}}
        @php
            $shareTitle = config('app.name').' — Know what your rivals are doing this week';
            $shareDescription = 'Creeper researches your competitors and emails you one digest of what they launched, repriced, raised and hired, with a source for every claim.';
            $shareImageAlt = 'Creeper: Know what your rivals are doing this week, above a sample competitor digest.';
        @endphp
        <meta name="description" content="{{ $shareDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $shareTitle }}">
        <meta property="og:description" content="{{ $shareDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $shareImageAlt }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $shareTitle }}">
        <meta name="twitter:description" content="{{ $shareDescription }}">
        <meta name="twitter:image" content="{{ asset('og-image.png') }}">
        <meta name="twitter:image:alt" content="{{ $shareImageAlt }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- All three faces are part of the design system now: sans for prose,
             mono for anything a machine printed, Doto for display. --}}
        @fonts(['instrument-sans', 'ibm-plex-mono', 'doto'])

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
