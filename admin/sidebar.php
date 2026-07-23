<?php
/**
 * Admin Sidebar — AFMS
 * =====================================================================
 * Feedback-Management-only navigation. ERP sections removed.
 * =====================================================================
 */
declare(strict_types=1);
$_cur = basename($_SERVER['PHP_SELF']);

/**
 * Renders a sidebar navigation link.
 */
function _nav_item(
    string $href,
    string $icon,
    string $label,
    string $current,
    string $badge = ''
): string {
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

function _nav_section(string $label): string
{
    return "<div class=\"px-4 pt-4 pb-1\">
                <p class=\"text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-600\">{$label}</p>
            </div>";
}

// ── SVG icons ─────────────────────────────────────────────────────────────────
$_ic = [
    'dashboard'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>',
    'sessions'   => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    'forms'      => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'qbank'      => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'responses'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>',
    'analytics'  => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'reports'    => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'announce'   => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>',
    'audit'      => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
    'settings'   => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'logout'     => '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>',
];
?>
<aside id="admin-sidebar"
    class="w-64 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 flex-shrink-0
           flex flex-col h-full z-20 shadow-lg
           absolute inset-y-0 left-0 -translate-x-full
           md:relative md:translate-x-0">

    <!-- Brand Logo -->
    <div class="h-16 flex items-center px-4 border-b border-gray-200 dark:border-slate-700 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center shadow">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="leading-tight">
                <p class="text-sm font-bold text-gray-900 dark:text-white">AFMS</p>
                <p class="text-[10px] text-gray-400 dark:text-slate-500">Admin Portal</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 space-y-0.5">

        <?= _nav_item('dashboard.php',    $_ic['dashboard'], 'Dashboard',       $_cur) ?>

        <?= _nav_section('Feedback') ?>
        <?= _nav_item('sessions.php',     $_ic['sessions'],  'Sessions',         $_cur) ?>
        <?= _nav_item('forms.php',        $_ic['forms'],     'Questionnaires',   $_cur) ?>
        <?= _nav_item('question_bank.php',$_ic['qbank'],     'Question Bank',    $_cur) ?>
        <?= _nav_item('responses.php',    $_ic['responses'], 'Responses',        $_cur) ?>

        <?= _nav_section('Insights') ?>
        <?= _nav_item('analytics.php',    $_ic['analytics'], 'Analytics',        $_cur) ?>
        <?= _nav_item('reports.php',      $_ic['reports'],   'Reports',          $_cur) ?>

        <?= _nav_section('System') ?>
        <?= _nav_item('announcements.php',$_ic['announce'],  'Announcements',    $_cur) ?>
        <?= _nav_item('audit_log.php',    $_ic['audit'],     'Audit Log',        $_cur) ?>
        <?= _nav_item('settings.php',  $_ic['settings'],  'Settings',         $_cur) ?>

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
