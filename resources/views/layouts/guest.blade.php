<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" class="light" style="color-scheme: light;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'GMR System'))</title>
    <link rel="icon" type="image/webp" href="{{ asset('new-nfa-logo.webp') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>@import 'tailwindcss';</style>
    @endif
</head>
<body class="flex min-h-dvh flex-col justify-between items-center bg-base-200 px-4 py-8 text-base-content antialiased">
    <div class="w-full flex-1 flex items-center justify-center">
        <main class="w-full max-w-sm">
            @yield('content')
        </main>
    </div>
    <footer class="mt-6 text-center text-xs text-base-content/60 space-y-1">
        <div>&copy; {{ date('Y') }} National Food Authority &bull; GMR System. All rights reserved.</div>
        <div>Developer: <strong class="font-medium text-base-content/80">Dindo O. Quitor - R12 RECO</strong></div>
    </footer>
</body>
</html>
