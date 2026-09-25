<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'GMR System'))</title>

    <script>
        (function() {
            try {
                if (localStorage.getItem('sidebar-minified') === 'true') {
                    document.documentElement.classList.add('sidebar-is-minified');
                }
            } catch (e) {}
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        @import 'tailwindcss';
    </style>
    @endif
</head>

<body class="min-h-screen bg-base-200 text-base-content antialiased">
    <!-- Fixed Top Header -->
    <header class="fixed top-0 inset-x-0 h-16 z-40 border-b border-base-content/10 bg-base-100 flex items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <!-- Mobile drawer toggle -->
            <button type="button" class="btn btn-circle btn-text sm:hidden" aria-haspopup="dialog" aria-expanded="false" aria-controls="collapsible-mini-sidebar" data-overlay="#collapsible-mini-sidebar" aria-label="Open navigation">
                <span class="icon-[tabler--menu-2] size-5"></span>
            </button>

            <!-- Desktop minify toggle -->
            <button type="button" class="btn btn-circle btn-text hidden sm:inline-flex" aria-haspopup="dialog" aria-expanded="false" aria-controls="collapsible-mini-sidebar" aria-label="Toggle navigation" data-overlay-minifier="#collapsible-mini-sidebar">
                <span class="icon-[tabler--menu-2] size-5"></span>
            </button>

            <div>
                <a href="{{ route('home') }}" class="text-sm font-semibold text-base-content hover:text-primary transition-colors block leading-tight">NFA GMR</a>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="badge badge-soft badge-primary text-xs font-medium hidden sm:inline-flex">GMR System</span>
        </div>
    </header>

    <div class="min-h-screen">
        <!-- Sidebar below fixed topheader -->
        <aside id="collapsible-mini-sidebar" class="overlay [--auto-close:sm] transition-all duration-300 overlay-minified:w-17 sm:shadow-none overlay-open:translate-x-0 drawer drawer-start hidden w-66 sm:fixed sm:top-16 sm:bottom-0 sm:start-0 sm:z-30 sm:flex sm:translate-x-0 border-e border-base-content/20 bg-base-100 overflow-y-auto" role="dialog" tabindex="-1">
            <div class="drawer-body px-2 py-4">
                <ul class="menu p-0">
                    <li>
                        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'menu-active' : '' }}" title="Home">
                            <span class="icon-[tabler--home] size-5"></span>
                            <span class="overlay-minified:hidden">Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('records.create') }}" class="{{ request()->routeIs('records.*') ? 'menu-active' : '' }}" title="Data Entry">
                            <span class="icon-[tabler--edit] size-5"></span>
                            <span class="overlay-minified:hidden">Data Entry</span>
                        </a>
                    </li>
                    @php($isReportActive = request()->routeIs('amr.*', 'pmr.*', 'emr.*', 'gmr.*'))
                    <li class="dropdown relative {{ $isReportActive ? 'open' : '' }} [--adaptive:none] [--strategy:static] overlay-minified:[--adaptive:adaptive] overlay-minified:[--strategy:fixed] overlay-minified:[--offset:15] overlay-minified:[--trigger:hover] overlay-minified:[--placement:right-start]">
                        <button id="reports-dropdown" type="button" class="dropdown-toggle {{ $isReportActive ? 'menu-active' : '' }}" aria-haspopup="menu" aria-expanded="{{ $isReportActive ? 'true' : 'false' }}" aria-label="Reports" title="Reports">
                            <span class="icon-[tabler--report-analytics] size-5"></span>
                            <span class="overlay-minified:hidden">Reports</span>
                            <span class="icon-[tabler--chevron-down] dropdown-open:rotate-180 size-4 overlay-minified:hidden"></span>
                        </button>
                        <ul class="dropdown-menu mt-0 shadow-none overlay-minified:shadow-md overlay-minified:shadow-base-300/20 dropdown-open:opacity-100 {{ $isReportActive ? 'block' : 'hidden' }} min-w-60 overlay-minified:before:absolute overlay-minified:before:-start-4 overlay-minified:before:top-0 overlay-minified:before:h-full overlay-minified:before:w-4 before:bg-transparent" role="menu" aria-orientation="vertical" aria-labelledby="reports-dropdown">
                            <li><a href="{{ route('amr.index') }}" class="{{ request()->routeIs('amr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-bar] size-5"></span>AMR Report</a></li>
                            <li><a href="{{ route('pmr.index') }}" class="{{ request()->routeIs('pmr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-dots] size-5"></span>PMR Report</a></li>
                            <li><a href="{{ route('emr.index') }}" class="{{ request()->routeIs('emr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-arrows] size-5"></span>Expected Milling Recovery</a></li>
                            <li><a href="{{ route('gmr.summary') }}" class="{{ request()->routeIs('gmr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-dots] size-5"></span>GMR Summary</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Working Area (expands left when sidebar is minified) -->
        <div class="main-content-wrapper min-w-0 flex-1 pt-16 transition-all duration-300">
            <main class="w-full px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (localStorage.getItem('sidebar-minified') === 'true') {
                document.body.classList.add('overlay-minified');
                const sidebar = document.querySelector('#collapsible-mini-sidebar');
                if (sidebar) {
                    sidebar.classList.add('minified');
                }
            }

            document.querySelectorAll('[data-overlay-minifier="#collapsible-mini-sidebar"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setTimeout(function () {
                        const isMinified = document.body.classList.contains('overlay-minified') ||
                                           document.querySelector('#collapsible-mini-sidebar')?.classList.contains('minified');
                        localStorage.setItem('sidebar-minified', isMinified ? 'true' : 'false');
                        if (isMinified) {
                            document.documentElement.classList.add('sidebar-is-minified');
                        } else {
                            document.documentElement.classList.remove('sidebar-is-minified');
                        }
                    }, 50);
                });
            });
        });
    </script>
</body>

</html>
