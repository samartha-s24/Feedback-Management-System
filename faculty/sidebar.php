<?php
/**
 * Faculty Sidebar — AFMS
 */
declare(strict_types=1);
$_cur = basename($_SERVER['PHP_SELF']);

function _nav_item(string $href, string $icon, string $label, string $current, string $badge = ''): string {
    $active = ($current === $href);
    $base   = 'flex items-center gap-3 px-4 py-2.5 rounded-xl mx-2 transition-all duration-150 group text-sm font-medium';
    $cls    = $active
        ? "{$base} bg-brand-600 text-white shadow-sm"
        : "{$base} text-slate-600 dark:text-slate-400 hover:bg-brand-50 dark:hover:bg-slate-700 hover:text-brand-700 dark:hover:text-brand-300";
    $iconCls = $active ? 'text-white' : 'text-slate-400 dark:text-slate-500 group-hover:text-brand-600 dark:group-hover:text-brand-300';
    $badgeHtml = $badge
        ? "<span class=\"ml-auto min-w-[1.25rem] h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center px-1 font-bold\">{$badge}</span>"
        : '';
    return "<a href=\"{$href}\" class=\"{$cls}\">
                <span class=\"{$iconCls} flex-shrink-0\">{$icon}</span>
                <span class=\"truncate\">{$label}</span>{$badgeHtml}
            </a>";
}

function _nav_section(string $label): string {
    return "<div class=\"px-4 pt-4 pb-1\">
                <p class=\"text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-600\">{$label}</p>
            </div>";
}

$_ic = [
    'dashboard' => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>',
    'profile'   => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    'feedback'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'history'   => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'announce'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
    'settings'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'logout'    => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>',
];
?>
<aside id="faculty-sidebar"
    class="w-64 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 flex-shrink-0
           flex flex-col h-full z-20 shadow-lg
           absolute inset-y-0 left-0 -translate-x-full
           md:relative md:translate-x-0">

    <!-- Brand Logo -->
    <div class="h-16 flex items-center px-4 border-b border-gray-200 dark:border-slate-700 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center shadow">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
            </div>
            <div class="leading-tight">
                <p class="text-sm font-bold text-gray-900 dark:text-white">AFMS</p>
                <p class="text-[10px] text-gray-400 dark:text-slate-500">Faculty Portal</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 space-y-0.5">
        <?= _nav_item('dashboard.php',        $_ic['dashboard'], 'Dashboard',        $_cur) ?>
        <?= _nav_item('profile.php',          $_ic['profile'],   'My Profile',       $_cur) ?>

        <?= _nav_section('Feedback') ?>
        <?= _nav_item('feedback.php',         $_ic['feedback'],  'Assigned Feedback', $_cur) ?>
        <?= _nav_item('reports.php',          $_ic['history'],   'Published Reports', $_cur) ?>

        <?= _nav_section('Updates') ?>
        <?= _nav_item('announcements.php',    $_ic['announce'],  'Announcements',    $_cur) ?>
        <?= _nav_item('notifications.php',    '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>', 'Notifications', $_cur) ?>
        
        <?= _nav_section('System') ?>
        <?= _nav_item('settings.php',         $_ic['settings'],  'Settings',         $_cur) ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="border-t border-gray-200 dark:border-slate-700 p-3 flex-shrink-0">
        <a href="../auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-sm font-medium group">
            <span class="flex-shrink-0"><?= $_ic['logout'] ?></span>
            <span>Logout</span>
        </a>
    </div>
</aside>
