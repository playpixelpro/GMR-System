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
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
        <div class="min-h-screen md:flex">
            <aside class="border-b border-gray-200 bg-white md:fixed md:inset-y-0 md:left-0 md:z-30 md:w-64 md:overflow-y-auto md:border-b-0 md:border-r">
                <div class="flex items-center justify-between px-6 py-5">
                    <a href="{{ route('home') }}" class="text-lg font-semibold text-gray-900">GMR System</a>
                </div>

                <nav class="px-4 pb-5 md:sticky md:top-0 md:pt-2">

                    <div class="mt-3 flex flex-col gap-1">
                        <a href="{{ route('records.create') }}"
                           class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('records.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Data Entry
                        </a>

                        <details class="group" {{ request()->routeIs('amr.index', 'pmr.index') ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                <span>Reports</span>
                                <span class="text-xs transition-transform group-open:rotate-180">⌄</span>
                            </summary>
                            <div class="mt-1 flex flex-col gap-1 border-l border-gray-200 pl-3">
                                <a href="{{ route('amr.index') }}"
                                   class="rounded-md px-3 py-2 text-sm {{ request()->routeIs('amr.index') ? 'bg-blue-50 font-medium text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                    AMR Details
                                </a>
                                <a href="{{ route('pmr.index') }}"
                                   class="rounded-md px-3 py-2 text-sm {{ request()->routeIs('pmr.index') ? 'bg-blue-50 font-medium text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                                    PMR Details
                                </a>
                            </div>
                        </details>
                    </div>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 md:ml-64">
                <header class="border-b border-gray-200 bg-white">
                    <div class="mx-auto flex w-[90%] items-center justify-between px-4 py-4 sm:px-6">
                        <div>
                            <p class="text-sm font-medium text-gray-500">NFA GMR</p>
                            <p class="text-xs text-gray-400">Rice trial management</p>
                        </div>
                    </div>
                </header>

                <main class="mx-auto w-[90%] px-4 py-8 sm:px-6 lg:py-10">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
