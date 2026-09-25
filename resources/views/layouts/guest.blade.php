<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'GMR System'))</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            @import 'tailwindcss';
        </style>
    @endif
</head>

<body class="min-h-screen bg-base-200 text-base-content antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(28rem,36rem)]">
        <section class="relative hidden overflow-hidden bg-primary px-10 py-12 text-primary-content lg:flex lg:flex-col lg:justify-between xl:px-16">
            <div class="absolute -end-24 -top-24 size-72 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-32 -start-24 size-96 rounded-full border border-white/10"></div>

            <div class="relative z-10">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                    <span class="grid size-10 place-items-center rounded-xl bg-white/15 text-sm font-bold">NFA</span>
                    NFA GMR
                </a>
            </div>

            <div class="relative z-10 max-w-xl space-y-6">
                <div class="space-y-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary-content/70">Rice quality intelligence</p>
                    <h1 class="text-4xl font-semibold leading-tight xl:text-5xl">Reliable milling recovery data for better decisions.</h1>
                    <p class="max-w-lg text-base leading-7 text-primary-content/75">Manage AMR, PMR, EMR, and GMR workflows in one secure workspace for the National Food Authority.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-medium text-primary-content/80">
                    <span class="rounded-full border border-white/20 px-3 py-1.5">AMR &amp; PMR</span>
                    <span class="rounded-full border border-white/20 px-3 py-1.5">Workflow controls</span>
                    <span class="rounded-full border border-white/20 px-3 py-1.5">Audit-ready reports</span>
                </div>
            </div>

            <p class="relative z-10 text-sm text-primary-content/60">Authorized personnel only</p>
        </section>

        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                        <span class="grid size-10 place-items-center rounded-xl bg-primary text-sm font-bold text-primary-content">NFA</span>
                        NFA GMR
                    </a>
                </div>

                @yield('content')

                <p class="mt-8 text-center text-xs text-base-content/50">NFA GMR System</p>
            </div>
        </section>
    </main>
</body>

</html>
