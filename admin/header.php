<?php
/**
 * Admin Layout Header — AFMS
 * =====================================================================
 * Included at the top of every admin page.
 * Handles: authentication, migration, HTML <head>, sidebar, top-navbar.
 * Expects $page_title (string) to be set by the including page.
 * =====================================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_once __DIR__ . '/db_migrate.php';

// Enforce Admin-only access
require_role('Admin');

// Run DB migrations once per session
run_migrations();

$_page_title     = isset($page_title) ? h($page_title) . ' — AFMS Admin' : 'AFMS Admin Portal';
$_unread         = get_unread_count();
$_institution    = h(get_setting('institution_name', 'Autonomous College'));
$_admin_name     = h($_SESSION['name'] ?? 'Administrator');
$_admin_initial  = mb_strtoupper(mb_substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $_page_title ?></title>
    <!-- Ultra-Strict New Tab Guard -->
    <script>
        if (!sessionStorage.getItem('afms_tab_session')) {
            window.location.replace('<?= BASE_URL ?>/auth/logout.php');
        }
    </script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Theme Customization Script -->
    <!-- Theme Customization Script -->
    <script>
        const themes = {
            'ocean': {
                colors: { 50:'#eff6ff', 100:'#dbeafe', 200:'#bfdbfe', 300:'#93c5fd', 400:'#60a5fa', 500:'#3b82f6', 600:'#2563eb', 700:'#1d4ed8', 800:'#1e40af', 900:'#1e3a8a' },
                bgDark: '#0d1117', cardDark: '#161b22'
            },
            'emerald': {
                colors: { 50:'#ecfdf5', 100:'#d1fae5', 200:'#a7f3d0', 300:'#6ee7b7', 400:'#34d399', 500:'#10b981', 600:'#059669', 700:'#047857', 800:'#065f46', 900:'#064e3b' },
                bgDark: '#0d1411', cardDark: '#16211c'
            },
            'amethyst': {
                colors: { 50:'#f5f3ff', 100:'#ede9fe', 200:'#ddd6fe', 300:'#c4b5fd', 400:'#a78bfa', 500:'#8b5cf6', 600:'#7c3aed', 700:'#6d28d9', 800:'#5b21b6', 900:'#4c1d95' },
                bgDark: '#110d14', cardDark: '#1d1624'
            },
            'sunset': {
                colors: { 50:'#fffbeb', 100:'#fef3c7', 200:'#fde68a', 300:'#fcd34d', 400:'#fbbf24', 500:'#f59e0b', 600:'#d97706', 700:'#b45309', 800:'#92400e', 900:'#78350f' },
                bgDark: '#14100d', cardDark: '#211a16'
            },
            'rose': {
                colors: { 50:'#fff1f2', 100:'#ffe4e6', 200:'#fecdd3', 300:'#fda4af', 400:'#fb7185', 500:'#f43f5e', 600:'#e11d48', 700:'#be123c', 800:'#9f1239', 900:'#881337' },
                bgDark: '#140d0f', cardDark: '#211619'
            },
            'cyan': {
                colors: { 50:'#ecfeff', 100:'#cffafe', 200:'#a5f3fc', 300:'#67e8f9', 400:'#22d3ee', 500:'#06b6d4', 600:'#0891b2', 700:'#0e7490', 800:'#155e75', 900:'#164e63' },
                bgDark: '#0c1314', cardDark: '#152022'
            }
        };

        const activeThemeName = localStorage.getItem('afms-custom-theme') || 'ocean';
        const activeTheme = themes[activeThemeName] || themes['ocean'];

        // Inject CSS variables
        document.documentElement.style.setProperty('--bg-dark', activeTheme.bgDark);
        document.documentElement.style.setProperty('--card-dark', activeTheme.cardDark);

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: activeTheme.colors },
                    animation: { 'fade-in': 'fadeIn 0.3s ease-in-out', 'slide-up': 'slideUp 0.3s ease-out' },
                    keyframes: {
                        fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                        slideUp: { '0%': { transform: 'translateY(10px)', opacity: 0 }, '100%': { transform: 'translateY(0)', opacity: 1 } },
                    }
                }
            }
        };

        // FOUC Prevention & Initial Dark Mode application
        if (localStorage.getItem('afms-theme') === 'dark' || (!localStorage.getItem('afms-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Sidebar transition */
        #admin-sidebar { transition: transform 0.25s ease-in-out, width 0.25s ease-in-out; }

        /* Dark mode overrides (Dynamic via CSS variables) */
        .dark body { background: var(--bg-dark); color: #e2e8f0; }
        .dark .content-area { background: var(--bg-dark); }
        
        /* Intercept Tailwind's hardcoded slate classes to enforce dynamic themes */
        .dark .dark\:bg-slate-900 { background-color: var(--bg-dark) !important; }
        .dark .dark\:bg-slate-800 { background-color: var(--card-dark) !important; }
        .dark .dark\:border-slate-700 { border-color: rgba(255,255,255,0.05) !important; }
        
        /* Toast notification */
        #toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { animation: slideUp 0.3s ease-out; }

        /* Table styles */
        .data-table th { background: #f8fafc; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
        .dark .data-table th { background: var(--card-dark); color: #94a3b8; border-bottom-color: rgba(255,255,255,0.05); }
        .data-table td { vertical-align: middle; border-bottom-color: #e2e8f0; }
        .dark .data-table td { border-bottom-color: rgba(255,255,255,0.05); }
        .data-table tbody tr:hover { background: #f8fafc; }
        .dark .data-table tbody tr:hover { background: rgba(255,255,255,0.02); }

        /* Form inputs */
        .form-input { transition: border-color 0.15s, box-shadow 0.15s; }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }

        /* Loading spinner */
        .spinner { width: 18px; height: 18px; border: 2px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Page fade-in */
        main { animation: fadeIn 0.25s ease-in-out; }
    </style>
</head>

<body class="bg-brand-50/40 text-gray-900 dark:bg-slate-900 dark:text-slate-100 flex h-full overflow-hidden">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- ── Main Wrapper ───────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        <!-- ── Top Navbar ─────────────────────────────────────────── -->
        <header class="top-navbar bg-brand-100/50 dark:bg-slate-800 border-b border-brand-200/50 dark:border-slate-700 h-16 flex items-center justify-between px-6 z-10 flex-shrink-0 shadow-sm">

            <!-- Left: hamburger + breadcrumb -->
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle"
                    class="text-gray-500 hover:text-brand-600 focus:outline-none transition-colors"
                    aria-label="Toggle sidebar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="hidden sm:block">
                    <p class="text-xs text-gray-400 dark:text-slate-500"><?= $_institution ?></p>
                    <h1 class="text-sm font-semibold text-gray-700 dark:text-slate-200 leading-tight">
                        <?= isset($page_title) ? h($page_title) : 'Dashboard' ?>
                    </h1>
                </div>
            </div>

            <!-- Right: dark mode, notifications, user -->
            <div class="flex items-center gap-3">

                <!-- Dark Mode Toggle -->
                <button id="dark-toggle"
                    class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                    title="Toggle dark mode" aria-label="Toggle dark mode">
                    <svg id="icon-sun" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="icon-moon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <!-- Notifications Bell -->
                <div class="relative">
                    <a href="notifications.php"
                        class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                        aria-label="Notifications">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <?php if ($_unread > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 text-white text-xs flex items-center justify-center font-bold leading-none">
                            <?= min($_unread, 99) ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Divider -->
                <div class="h-8 w-px bg-gray-200 dark:bg-slate-700"></div>

                <!-- Admin User -->
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                        <?= $_admin_initial ?>
                    </div>
                    <div class="hidden md:block leading-tight">
                        <p class="text-sm font-semibold text-gray-800 dark:text-slate-200"><?= $_admin_name ?></p>
                        <p class="text-xs text-gray-400 dark:text-slate-500">Administrator</p>
                    </div>
                </div>

                <!-- Logout -->
                <a href="../auth/logout.php"
                   class="hidden sm:flex items-center gap-1.5 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors"
                   title="Logout">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Logout
                </a>
            </div>
        </header>

        <!-- ── Scrollable Content Area ──────────────────────────── -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-transparent p-6">
            <!-- Toast notification container -->
            <div id="toast-container"></div>
