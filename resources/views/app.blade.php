<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Favicon — palitan ang href ng path sa iyong logo -->
        <link rel="icon" type="image/png" href="{{ asset('storage/icon/favicon-32x32.png') }}">
        <!-- O kung nasa public folder -->
        {{-- <link rel="icon" type="image/png" href="/favicon.png"> --}}

        <title inertia>{{ config('app.name', 'TDI') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
