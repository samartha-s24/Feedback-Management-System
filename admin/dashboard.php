<?php
/**
 * AFMS Admin — Dashboard
 * Feedback-focused KPI cards, charts, quick actions, recent sessions.
 */
declare(strict_types=1);

$page_title = 'Dashboard';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── KPI Queries ───────────────────────────────────────────────────────────────
$kpi = [];

// Sessions
$kpi['active_sessions']   = (int) $db->query("SELECT COUNT(*) FROM feedback_sessions WHERE status='Active'")->fetch_row()[0];
$kpi['closed_sessions']   = (int) $db->query("SELECT COUNT(*) FROM feedback_sessions WHERE status='Closed'")->fetch_row()[0];
$kpi['draft_sessions']    = (int) $db->query("SELECT COUNT(*) FROM feedback_sessions WHERE status='Draft'")->fetch_row()[0];
$kpi['published_sessions']= (int) $db->query("SELECT COUNT(*) FROM feedback_sessions WHERE status='Published'")->fetch_row()[0];
$kpi['total_forms']       = (int) $db->query("SELECT COUNT(*) FROM questionnaires")->fetch_row()[0];

// Responses
$kpi['total_responses']   = (int) $db->query("SELECT COUNT(*) FROM feedback_submissions")->fetch_row()[0];
$kpi['total_comments']    = (int) $db->query("SELECT COUNT(*) FROM submission_comments WHERE is_hidden=0")->fetch_row()[0];

// Average rating (across all responses)
$avg_row = $db->query("SELECT ROUND(AVG(rating_value),2) FROM submission_responses")->fetch_row();
$kpi['avg_rating'] = $avg_row[0] !== null ? (float) $avg_row[0] : 0.0;

// Sentiment distribution
$sent = $db->query("SELECT
    SUM(rating_value >= 4) AS positive,
    SUM(rating_value = 3)  AS neutral,
    SUM(rating_value <= 2) AS negative
FROM submission_responses")->fetch_assoc();
$kpi['positive'] = (int)($sent['positive'] ?? 0);
$kpi['neutral']  = (int)($sent['neutral']  ?? 0);
$kpi['negative'] = (int)($sent['negative'] ?? 0);

$totalSent = $kpi['positive'] + $kpi['neutral'] + $kpi['negative'];
$kpi['positive_pct'] = $totalSent > 0 ? round($kpi['positive'] / $totalSent * 100, 1) : 0;

// Announcements (published & not expired)
$kpi['active_announcements'] = (int) $db->query("SELECT COUNT(*) FROM announcements WHERE status='Published'")->fetch_row()[0];

// Question bank count
$kpi['questions'] = (int) $db->query("SELECT COUNT(*) FROM question_bank WHERE is_active=1")->fetch_row()[0];

// ── Daily submission trend (last 14 days) ─────────────────────────────────────
$trend = $db->query("SELECT DATE(submitted_at) AS day, COUNT(*) AS total
                     FROM feedback_submissions
                     WHERE submitted_at >= CURDATE() - INTERVAL 13 DAY
                     GROUP BY day ORDER BY day");
$trendDays = [];
$trendData = [];
for ($i = 13; $i >= 0; $i--) {
    $trendDays[] = date('M j', strtotime("-{$i} days"));
    $trendData[]  = 0;
}
while ($row = $trend->fetch_assoc()) {
    $offset = 13 - (int)floor((strtotime(date('Y-m-d')) - strtotime($row['day'])) / 86400);
    if (isset($trendData[$offset])) $trendData[$offset] = (int)$row['total'];
}
$trendDaysJson = json_encode($trendDays);
$trendDataJson = json_encode($trendData);

// ── Recent Sessions ───────────────────────────────────────────────────────────
$recentSessions = $db->query("SELECT session_id, session_title, status, academic_year, semester, start_date, end_date
                               FROM feedback_sessions
                               ORDER BY created_at DESC LIMIT 6");

// ── Active Announcements ──────────────────────────────────────────────────────
$announcements = $db->query("SELECT title, priority, end_date FROM announcements
                              WHERE status='Published' ORDER BY priority DESC, created_at DESC LIMIT 4");
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
            Welcome back, <strong><?= h($_SESSION['name'] ?? 'Admin') ?></strong>. Here's what's happening with your feedback system.
        </p>
    </div>
    <div class="flex gap-2">
        <a href="session_form.php"
           class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Session
        </a>
    </div>
</div>

<!-- ── KPI Cards ──────────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['Active Sessions',    $kpi['active_sessions'],   'bg-emerald-500', 'from-emerald-50',  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', 'sessions.php?status=Active'],
        ['Total Responses',    $kpi['total_responses'],   'bg-brand-500',   'from-blue-50',    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>', 'responses.php'],
        ['Avg Rating',         number_format($kpi['avg_rating'],1) . '/5', 'bg-amber-500', 'from-amber-50', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>', 'reports.php'],
        ['Positive %',         $kpi['positive_pct'] . '%', 'bg-purple-500', 'from-purple-50', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'reports.php'],
        ['Questionnaires',     $kpi['total_forms'],       'bg-indigo-500',  'from-indigo-50',  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>', 'forms.php'],
        ['Closed Sessions',    $kpi['closed_sessions'],   'bg-red-500',     'from-red-50',     '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'sessions.php?status=Closed'],
        ['Question Bank',      $kpi['questions'],         'bg-teal-500',    'from-teal-50',    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'question_bank.php'],
        ['Announcements',      $kpi['active_announcements'], 'bg-orange-500', 'from-orange-50', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>', 'announcements.php'],
    ];
    foreach ($cards as [$title, $value, $iconBg, $gradFrom, $iconPath, $linkUrl]):
    ?>
    <a href="<?= h($linkUrl) ?>" class="block bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 group">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= $gradFrom ?> to-white dark:from-slate-700 dark:to-slate-800 flex items-center justify-center">
                <div class="w-8 h-8 rounded-lg <?= $iconBg ?> flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><?= $iconPath ?></svg>
                </div>
            </div>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $value ?></p>
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 mt-0.5"><?= $title ?></p>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Charts Row ─────────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Trend Chart -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Submission Trend</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">Responses over the last 14 days</p>
            </div>
            <span class="text-xs bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-300 px-2 py-1 rounded-lg font-medium">14-day</span>
        </div>
        <div class="h-52">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Sentiment Pie -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <div class="mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Sentiment Distribution</h3>
            <p class="text-xs text-gray-400 dark:text-slate-500">Based on all responses</p>
        </div>
        <div class="h-40">
            <canvas id="sentimentChart"></canvas>
        </div>
        <div class="mt-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Positive (4–5)</span>
                <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $kpi['positive'] ?></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>Neutral (3)</span>
                <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $kpi['neutral'] ?></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Negative (1–2)</span>
                <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $kpi['negative'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ── Bottom Row: Sessions + Quick Actions + Announcements ─────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Recent Sessions -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Recent Feedback Sessions</h3>
            <a href="sessions.php" class="text-xs text-brand-600 dark:text-brand-400 hover:underline font-medium">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-slate-700">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Session</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Period</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                    <?php
                    $hasRows = false;
                    while ($sess = $recentSessions->fetch_assoc()):
                        $hasRows = true;
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-5 py-3">
                            <a href="sessions.php?highlight=<?= $sess['session_id'] ?>" class="font-medium text-gray-800 dark:text-slate-200 hover:text-brand-600 dark:hover:text-brand-400 transition-colors line-clamp-1">
                                <?= h($sess['session_title']) ?>
                            </a>
                            <?php if ($sess['academic_year']): ?>
                            <p class="text-xs text-gray-400 dark:text-slate-500"><?= h($sess['academic_year']) ?><?= $sess['semester'] ? ' · Sem ' . $sess['semester'] : '' ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">
                            <?php
                            $sStart = !empty($sess['start_date']) && strpos($sess['start_date'], '0000') !== 0 ? strtotime($sess['start_date']) : false;
                            $sEnd = !empty($sess['end_date']) && strpos($sess['end_date'], '0000') !== 0 ? strtotime($sess['end_date']) : false;
                            if ($sStart && $sEnd) {
                                echo date('d M', $sStart) . ' – ' . date('d M Y', $sEnd);
                            } elseif ($sStart) {
                                echo 'From ' . date('d M Y', $sStart);
                            } elseif ($sEnd) {
                                echo 'Until ' . date('d M Y', $sEnd);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="px-4 py-3"><?= status_badge($sess['status'] ?? 'Draft') ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if (!$hasRows): ?>
                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400 dark:text-slate-500 text-sm">
                        No feedback sessions yet.
                        <a href="session_form.php" class="text-brand-600 hover:underline ml-1">Create one →</a>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Quick Actions + Announcements -->
    <div class="space-y-4">

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Quick Actions</h3>
            <div class="space-y-2">
                <?php
                $actions = [
                    ['session_form.php',  'bg-brand-600 hover:bg-brand-700',   'New Feedback Session',   'M12 4v16m8-8H4'],
                    ['forms.php',         'bg-indigo-600 hover:bg-indigo-700',  'Create Questionnaire',   'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['reports.php',       'bg-emerald-600 hover:bg-emerald-700','View Reports',           'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['analytics.php',     'bg-purple-600 hover:bg-purple-700',  'Analytics Dashboard',    'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['announcements.php', 'bg-orange-500 hover:bg-orange-600',  'Announcements',          'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
                ];
                foreach ($actions as [$href, $clr, $label, $path]):
                ?>
                <a href="<?= $href ?>" class="flex items-center gap-3 <?= $clr ?> text-white text-xs font-medium px-3 py-2.5 rounded-xl transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/>
                    </svg>
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Active Announcements -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Announcements</h3>
                <a href="announcements.php" class="text-xs text-brand-600 dark:text-brand-400 hover:underline">Manage</a>
            </div>
            <div class="space-y-2">
                <?php
                $hasAnn = false;
                while ($ann = $announcements->fetch_assoc()):
                    $hasAnn = true;
                    $priCls = ['High' => 'text-red-500', 'Medium' => 'text-yellow-500', 'Low' => 'text-slate-400'][$ann['priority']] ?? 'text-gray-400';
                ?>
                <div class="flex items-start gap-2">
                    <span class="mt-1 <?= $priCls ?>">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-800 dark:text-slate-200 truncate"><?= h($ann['title']) ?></p>
                        <?php if (!empty($ann['end_date']) && strpos($ann['end_date'], '0000') !== 0): ?>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500">Ends <?= date('d M Y', strtotime($ann['end_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if (!$hasAnn): ?>
                <p class="text-xs text-gray-400 dark:text-slate-500">No active announcements.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart.js Initialization ───────────────────────────────────────────────── -->
<script>
(function () {
    const isDark = () => document.documentElement.classList.contains('dark');
    const grid   = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
    const text   = () => isDark() ? '#94a3b8' : '#64748b';

    // Trend line chart
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= $trendDaysJson ?>,
                datasets: [{
                    label: 'Responses',
                    data: <?= $trendDataJson ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.08)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3b82f6',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: grid() }, ticks: { color: text(), font: { size: 10 }, maxTicksLimit: 7 } },
                    y: { grid: { color: grid() }, ticks: { color: text(), font: { size: 10 }, stepSize: 1 }, beginAtZero: true }
                }
            }
        });
    }

    // Sentiment pie
    const sentCtx = document.getElementById('sentimentChart');
    if (sentCtx) {
        new Chart(sentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Positive', 'Neutral', 'Negative'],
                datasets: [{
                    data: [<?= $kpi['positive'] ?>, <?= $kpi['neutral'] ?>, <?= $kpi['negative'] ?>],
                    backgroundColor: ['#10b981', '#fbbf24', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => ` ${c.label}: ${c.raw} (${c.dataset.data.reduce((a,b)=>a+b,0) > 0 ? Math.round(c.raw/c.dataset.data.reduce((a,b)=>a+b,0)*100) : 0}%)` } }
                }
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
