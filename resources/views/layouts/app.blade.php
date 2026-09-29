<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" class="light" style="color-scheme: light;">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'GMR System'))</title>

    <script>
        (function() {
            try {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.style.colorScheme = 'light';
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
            <div class="dropdown relative inline-flex [--placement:bottom-end] [--offset:4]">
                <button type="button" class="dropdown-toggle flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-base-200" aria-haspopup="menu" aria-expanded="false" aria-label="Profile menu">
                    <span class="hidden sm:inline text-sm font-medium text-base-content">Hello, {{ auth()->user()->name }}</span>
                    <x-user-avatar :user="auth()->user()" size="size-8" />
                    <span class="icon-[tabler--chevron-down] dropdown-open:rotate-180 size-4 text-base-content/50"></span>
                </button>
                <ul class="dropdown-menu dropdown-open:opacity-100 hidden mt-2 min-w-44 rounded-box shadow-lg shadow-base-300/30" role="menu" aria-orientation="vertical">
                    <li>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2">
                            <span class="icon-[tabler--user-cog] size-4"></span>Edit Profile
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 text-error">
                                <span class="icon-[tabler--logout] size-4"></span>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
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
                    <li>
                        <a href="{{ route('millings.index') }}" class="{{ request()->routeIs('millings.*') ? 'menu-active' : '' }}" title="Rice Milling">
                            <span class="icon-[tabler--building-factory-2] size-5"></span>
                            <span class="overlay-minified:hidden">Rice Milling</span>
                        </a>
                    </li>
                    @php($isReportActive = request()->routeIs('amr.*', 'pmr.*', 'emr.*', 'gmr.*', 'gmr-approvals.*'))
                    <li class="dropdown relative {{ $isReportActive ? 'open' : '' }} [--adaptive:none] [--strategy:static]" data-flyout-title="Reports">
                        <button id="reports-dropdown" type="button" class="dropdown-toggle {{ $isReportActive ? 'menu-active' : '' }}" aria-haspopup="menu" aria-expanded="{{ $isReportActive ? 'true' : 'false' }}" aria-label="Reports" title="Reports">
                            <span class="icon-[tabler--report-analytics] size-5"></span>
                            <span class="overlay-minified:hidden">Reports</span>
                            <span class="icon-[tabler--chevron-down] dropdown-open:rotate-180 size-4 overlay-minified:hidden"></span>
                        </button>
                        <ul class="dropdown-menu mt-0 shadow-none dropdown-open:opacity-100 {{ $isReportActive ? 'block' : 'hidden' }} min-w-60" role="menu" aria-orientation="vertical" aria-labelledby="reports-dropdown">
                            <li><a href="{{ route('amr.index') }}" class="{{ request()->routeIs('amr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-bar] size-5"></span>AMR Report</a></li>
                            <li><a href="{{ route('pmr.index') }}" class="{{ request()->routeIs('pmr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-dots] size-5"></span>PMR Report</a></li>
                            <li><a href="{{ route('emr.index') }}" class="{{ request()->routeIs('emr.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-arrows] size-5"></span>Expected Milling Recovery</a></li>
                            <li><a href="{{ route('gmr.summary') }}" class="{{ request()->routeIs('gmr.summary') ? 'menu-active' : '' }}"><span class="icon-[tabler--chart-dots] size-5"></span>GMR Summary</a></li>
                            @if (auth()->user()?->hasRole('RMEC', 'ADMINISTRATOR'))
                                <li><a href="{{ route('gmr.config.edit') }}" class="{{ request()->routeIs('gmr.config.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--adjustments] size-5"></span>GMR Report Configuration</a></li>
                                <li><a href="{{ route('gmr-approvals.index') }}" class="{{ request()->routeIs('gmr-approvals.*') ? 'menu-active' : '' }}"><span class="icon-[tabler--clipboard-check] size-5"></span>GMR Central Office Approvals</a></li>
                            @endif
                        </ul>
                    </li>
                    @php($isSettingActive = request()->routeIs('profile.*', 'users.*', 'settings.*'))
                    <li class="dropdown relative {{ $isSettingActive ? 'open' : '' }} [--adaptive:none] [--strategy:static]" data-flyout-title="Setting">
                        <button id="settings-dropdown" type="button" class="dropdown-toggle {{ $isSettingActive ? 'menu-active' : '' }}" aria-haspopup="menu" aria-expanded="{{ $isSettingActive ? 'true' : 'false' }}" aria-label="Setting" title="Setting">
                            <span class="icon-[tabler--settings] size-5"></span>
                            <span class="overlay-minified:hidden">Setting</span>
                            <span class="icon-[tabler--chevron-down] dropdown-open:rotate-180 size-4 overlay-minified:hidden"></span>
                        </button>
                        <ul class="dropdown-menu mt-0 shadow-none dropdown-open:opacity-100 {{ $isSettingActive ? 'block' : 'hidden' }} min-w-60" role="menu" aria-orientation="vertical" aria-labelledby="settings-dropdown">
                            <li>
                                <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'menu-active' : '' }}">
                                    <span class="icon-[tabler--user-cog] size-5"></span>Profile settings
                                </a>
                            </li>
                            @can('manage-millings')
                                <li>
                                    <a href="{{ route('settings.millers') }}" class="{{ request()->routeIs('settings.millers*') ? 'menu-active' : '' }}">
                                        <span class="icon-[tabler--building-factory-2] size-5"></span>Miller Management
                                    </a>
                                </li>
                            @endcan
                            @if (auth()->user()?->hasRole('ADMINISTRATOR'))
                                <li>
                                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'menu-active' : '' }}">
                                        <span class="icon-[tabler--users] size-5"></span>User management
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('settings.blocked-ips') }}" class="{{ request()->routeIs('settings.blocked-ips') ? 'menu-active' : '' }}">
                                        <span class="icon-[tabler--shield-lock] size-5"></span>Blocked IPs
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('settings.activity-logs') }}" class="{{ request()->routeIs('settings.activity-logs*') ? 'menu-active' : '' }}">
                                        <span class="icon-[tabler--list-details] size-5"></span>Activity Logs
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('settings.data-cleanup') }}" class="{{ request()->routeIs('settings.data-cleanup*') ? 'menu-active' : '' }}">
                                        <span class="icon-[tabler--database-cog] size-5"></span>Data Cleanup
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Floating Flyout Submenu (enabled ONLY when sidebar has been minified by user) -->
        <div id="sidebar-floating-flyout"
             class="hidden fixed z-50 min-w-64 max-w-xs rounded-xl border border-base-content/10 bg-base-100 text-base-content shadow-2xl transition-opacity duration-150 pointer-events-auto"
             role="menu"
             aria-orientation="vertical">
            <div id="sidebar-floating-flyout-header" class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-base-content/60 border-b border-base-content/10 flex items-center justify-between">
                <span id="sidebar-floating-flyout-title">Menu</span>
            </div>
            <div class="p-1.5 max-h-[calc(100vh-8rem)] overflow-y-auto">
                <ul id="sidebar-floating-flyout-menu" class="menu p-0 gap-1 text-sm font-medium">
                    <!-- Populated dynamically with the hovered item's existing submenu -->
                </ul>
            </div>
        </div>

        <!-- Working Area (expands left when sidebar is minified) -->
        <div class="main-content-wrapper min-w-0 flex-1 pt-16 transition-all duration-300">
            <main class="w-full px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('#collapsible-mini-sidebar');
            const flyout = document.querySelector('#sidebar-floating-flyout');
            const flyoutTitle = document.querySelector('#sidebar-floating-flyout-title');
            const flyoutMenu = document.querySelector('#sidebar-floating-flyout-menu');
            let activeParentLi = null;
            let hideTimer = null;
            const HIDE_DELAY = 180;

            if (localStorage.getItem('sidebar-minified') === 'true') {
                document.body.classList.add('overlay-minified');
                if (sidebar) {
                    sidebar.classList.add('minified');
                }
            }

            function isMinified() {
                if (window.innerWidth < 640) return false;
                return (
                    document.documentElement.classList.contains('sidebar-is-minified') ||
                    document.body.classList.contains('overlay-minified') ||
                    (sidebar && sidebar.classList.contains('minified'))
                );
            }

            function showFlyout(parentLi) {
                if (!isMinified() || !flyout || !flyoutTitle || !flyoutMenu || !sidebar) {
                    hideFlyout(true);
                    return;
                }

                const originalSubmenu = parentLi.querySelector('.dropdown-menu');
                if (!originalSubmenu) {
                    hideFlyout(true);
                    return;
                }

                clearTimeout(hideTimer);
                activeParentLi = parentLi;

                const title = parentLi.dataset.flyoutTitle ||
                              parentLi.querySelector('.dropdown-toggle')?.getAttribute('aria-label') ||
                              'Menu';
                flyoutTitle.textContent = title;

                // Clone exact submenu links to preserve active status, permissions, and URLs
                flyoutMenu.innerHTML = originalSubmenu.innerHTML;

                // Position flyout beside the collapsed sidebar
                const parentRect = parentLi.getBoundingClientRect();
                const sidebarRect = sidebar.getBoundingClientRect();

                flyout.classList.remove('hidden');
                flyout.style.visibility = 'hidden';
                flyout.style.display = 'block';

                const flyoutHeight = flyout.offsetHeight || 220;
                let top = parentRect.top;

                // Viewport bottom boundary clamp
                if (top + flyoutHeight > window.innerHeight - 16) {
                    top = Math.max(70, window.innerHeight - flyoutHeight - 16);
                }

                const left = Math.round(sidebarRect.right + 2);

                flyout.style.top = top + 'px';
                flyout.style.left = left + 'px';
                flyout.style.visibility = 'visible';
                flyout.style.opacity = '1';
            }

            function scheduleHide() {
                clearTimeout(hideTimer);
                hideTimer = setTimeout(function () {
                    hideFlyout();
                }, HIDE_DELAY);
            }

            function hideFlyout(immediate) {
                clearTimeout(hideTimer);
                activeParentLi = null;
                if (!flyout) return;
                if (immediate) {
                    flyout.classList.add('hidden');
                    flyout.style.display = 'none';
                    flyout.style.opacity = '0';
                } else {
                    flyout.style.opacity = '0';
                    hideTimer = setTimeout(function () {
                        flyout.classList.add('hidden');
                        flyout.style.display = 'none';
                    }, 120);
                }
            }

            if (sidebar && flyout) {
                const menuItems = sidebar.querySelectorAll('ul.menu > li');
                menuItems.forEach(function (li) {
                    const hasSubmenu = li.classList.contains('dropdown') || !!li.querySelector('.dropdown-menu');

                    li.addEventListener('mouseenter', function () {
                        if (!isMinified()) return;
                        if (hasSubmenu) {
                            showFlyout(li);
                        } else {
                            hideFlyout(true);
                        }
                    });

                    li.addEventListener('mouseleave', function () {
                        if (!isMinified()) return;
                        if (hasSubmenu) {
                            scheduleHide();
                        }
                    });

                    if (hasSubmenu) {
                        const toggleBtn = li.querySelector('.dropdown-toggle');
                        if (toggleBtn) {
                            toggleBtn.addEventListener('click', function (e) {
                                if (isMinified()) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    showFlyout(li);
                                }
                            });
                        }
                    }
                });

                flyout.addEventListener('mouseenter', function () {
                    if (!isMinified()) return;
                    clearTimeout(hideTimer);
                    flyout.style.opacity = '1';
                });

                flyout.addEventListener('mouseleave', function () {
                    if (!isMinified()) return;
                    scheduleHide();
                });

                flyout.addEventListener('click', function (e) {
                    const link = e.target.closest('a');
                    if (link) {
                        hideFlyout(true);
                    }
                });

                window.addEventListener('scroll', function () {
                    hideFlyout(true);
                }, { passive: true });

                sidebar.addEventListener('scroll', function () {
                    hideFlyout(true);
                }, { passive: true });

                document.addEventListener('click', function (e) {
                    if (!isMinified()) return;
                    if (!flyout.contains(e.target) && !sidebar.contains(e.target)) {
                        hideFlyout(true);
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        hideFlyout(true);
                    }
                });
            }

            document.querySelectorAll('[data-overlay-minifier="#collapsible-mini-sidebar"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    hideFlyout(true);
                    setTimeout(function () {
                        const isMinifiedState = document.body.classList.contains('overlay-minified') ||
                                           document.querySelector('#collapsible-mini-sidebar')?.classList.contains('minified');
                        localStorage.setItem('sidebar-minified', isMinifiedState ? 'true' : 'false');
                        if (isMinifiedState) {
                            document.documentElement.classList.add('sidebar-is-minified');
                        } else {
                            document.documentElement.classList.remove('sidebar-is-minified');
                        }
                        hideFlyout(true);
                        window.dispatchEvent(new Event('resize'));
                    }, 50);
                });
            });
        });
    </script>
    @stack('modals')
</body>

</html>
