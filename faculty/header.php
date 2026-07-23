<?php
/**
 * Faculty Layout Header — AFMS
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_login();
require_role('Faculty');

$_page_title     = isset($page_title) ? h($page_title) . ' — Faculty Portal' : 'Faculty Portal';
$_unread         = get_unread_count();
$_institution    = h(get_setting('institution_name', 'Autonomous College'));
$_faculty_name   = h($_SESSION['name'] ?? 'Faculty');
$_faculty_id     = h($_SESSION['login_id'] ?? '');
$_initial        = mb_strtoupper(mb_substr($_SESSION['name'] ?? 'S', 0, 1));
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        #faculty-sidebar { transition: transform 0.25s ease-in-out, width 0.25s ease-in-out; }

        /* Dark mode overrides (Dynamic via CSS variables) */
        .dark body { background: var(--bg-dark); color: #e2e8f0; }
        .dark .content-area { background: var(--bg-dark); }
        
        /* Intercept Tailwind's hardcoded slate classes to enforce dynamic themes */
        .dark .dark\:bg-slate-900 { background-color: var(--bg-dark) !important; }
        .dark .dark\:bg-slate-800 { background-color: var(--card-dark) !important; }
        .dark .dark\:border-slate-700 { border-color: rgba(255,255,255,0.05) !important; }

        #toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { animation: slideUp 0.3s ease-out; }
        
        .form-input { transition: border-color 0.15s, box-shadow 0.15s; }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-slate-800 antialiased selection:bg-brand-200 selection:text-brand-900 dark:selection:bg-brand-900 dark:selection:text-brand-100">

    <div id="toast-container"></div>

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-brand-50/40 dark:bg-slate-900 transition-colors">
        
        <!-- Top Navbar -->
        <header class="top-navbar h-16 bg-brand-100/50 dark:bg-slate-800 border-b border-brand-200/50 dark:border-slate-700 flex items-center justify-between px-4 sm:px-6 z-10 flex-shrink-0 shadow-sm">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" class="p-2 -ml-2 rounded-xl text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-400 transition-colors" aria-label="Toggle sidebar">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="hidden sm:block">
                    <h1 class="text-sm font-bold text-gray-900 dark:text-white"><?= $_institution ?></h1>
                    <p class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">Faculty Portal</p>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                <!-- Theme Toggle -->
                <button id="dark-toggle" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors" aria-label="Toggle dark mode">
                    <svg id="icon-moon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg id="icon-sun" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                <!-- Notifications -->
                <a href="notifications.php" class="relative p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <?php if ($_unread > 0): ?>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-800"></span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown (Simulated) -->
                <div class="flex items-center gap-2 pl-2 sm:pl-4 border-l border-gray-200 dark:border-slate-700">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight"><?= $_faculty_name ?></p>
                        <p class="text-[10px] text-gray-500 dark:text-slate-400 font-medium"><?= $_faculty_id ?></p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                        <?= $_initial ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 content-area">
