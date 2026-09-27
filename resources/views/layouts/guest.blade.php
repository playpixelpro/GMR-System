<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'GMR System'))</title>
    <link rel="icon" type="image/webp" href="{{ asset('new-nfa-logo.webp') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>@import 'tailwindcss';</style>
    @endif
</head>
<body class="grid min-h-dvh place-items-center bg-base-200 px-4 py-8 text-base-content antialiased">
    <main class="w-full max-w-sm">
        @yield('content')
    </main>
</body>
</html>
