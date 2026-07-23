<?php
/**
 * AFMS Admin — Analytics Dashboard
 * Session-wide and cross-session statistical aggregations.
 */
declare(strict_types=1);

$page_title = 'Analytics';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── Dropdowns Data ──────────────────────────────────────────────────────────
$departments = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$sessions    = $db->query("SELECT session_id, session_title FROM feedback_sessions ORDER BY created_at DESC");
$academicYears = $db->query("SELECT DISTINCT academic_year FROM feedback_sessions WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
$semesters = [1, 2, 3, 4, 5, 6, 7, 8];

// ── Form State ──────────────────────────────────────────────────────────────
$deptFilter = (int)($_GET['department'] ?? 0);
$ayFilter   = sanitize_input($_GET['academic_year'] ?? '');
$semFilter  = (int)($_GET['semester'] ?? 0);
$sessFilter = (int)($_GET['session'] ?? 0);
$formFilter = (int)($_GET['questionnaire'] ?? 0);

$whereSub = ["1=1"];
$params   = [];
$types    = '';

if ($deptFilter > 0) {
    $whereSub[] = "fsub.department_id = ?";
    $params[] = $deptFilter;
    $types .= 'i';
}
if ($sessFilter > 0) {
    $whereSub[] = "fsub.session_id = ?";
    $params[] = $sessFilter;
    $types .= 'i';
}
if ($formFilter > 0) {
    $whereSub[] = "fsub.questionnaire_id = ?";
    $params[] = $formFilter;
    $types .= 'i';
}
if ($ayFilter !== '') {
    $whereSub[] = "fs.academic_year = ?";
    $params[] = $ayFilter;
    $types .= 's';
}
if ($semFilter > 0) {
    $whereSub[] = "fs.semester = ?";
    $params[] = $semFilter;
    $types .= 'i';
}

$whereSubStr = implode(" AND ", $whereSub);

// ── Eligible Participants ────────────────────────────────────────────────────
$totalEligible = 0;
if ($deptFilter > 0 && $semFilter > 0) {
    $eligStmt = $db->prepare("SELECT COUNT(*) FROM students WHERE department_id = ? AND semester = ?");
    $eligStmt->bind_param('ii', $deptFilter, $semFilter);
    $eligStmt->execute();
    $totalEligible = (int) $eligStmt->get_result()->fetch_row()[0];
    $eligStmt->close();
} elseif ($deptFilter > 0) {
    $eligStmt = $db->prepare("SELECT (SELECT COUNT(*) FROM students WHERE department_id = ?) + (SELECT COUNT(*) FROM faculty WHERE department_id = ?) + (SELECT COUNT(*) FROM employees WHERE department_id = ?) + (SELECT COUNT(*) FROM alumni WHERE department_id = ?)");
    $eligStmt->bind_param('iiii', $deptFilter, $deptFilter, $deptFilter, $deptFilter);
    $eligStmt->execute();
    $totalEligible = (int) $eligStmt->get_result()->fetch_row()[0];
    $eligStmt->close();
} else {
    $totalEligible = (int) $db->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM faculty) + (SELECT COUNT(*) FROM employees) + (SELECT COUNT(*) FROM alumni)")->fetch_row()[0];
}

// ── Base KPIs ────────────────────────────────────────────────────────────────
$comCountSql = "SELECT COUNT(*) FROM submission_comments sc 
                JOIN feedback_submissions fsub ON sc.submission_id = fsub.submission_id 
                LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id 
                WHERE sc.is_hidden=0 AND {$whereSubStr}";
$comCountStmt = $db->prepare($comCountSql);
if ($params) $comCountStmt->bind_param($types, ...$params);
$comCountStmt->execute();
$totalComments = (int)$comCountStmt->get_result()->fetch_row()[0];
$comCountStmt->close();

$kpiSql = "SELECT
             COUNT(DISTINCT fsub.submission_id) as total_responses,
             ROUND(AVG(sr.rating_value), 2) as avg_rating
           FROM feedback_submissions fsub
           LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
           LEFT JOIN submission_responses sr ON sr.submission_id = fsub.submission_id
           WHERE {$whereSubStr}";

$kpiStmt = $db->prepare($kpiSql);
if ($params) $kpiStmt->bind_param($types, ...$params);
$kpiStmt->execute();
$kpi = $kpiStmt->get_result()->fetch_assoc();
$kpiStmt->close();

$totalResp = (int)($kpi['total_responses'] ?? 0);
$avgRating = $kpi['avg_rating'] !== null ? (float)$kpi['avg_rating'] : 0.0;
$responseRate = $totalEligible > 0 ? round(($totalResp / $totalEligible) * 100, 1) : 0;
// Completion percentage is roughly the same as response rate unless we track partials. We'll mirror it for now or assume 100% of submitted are complete since form requires it.
$completionPct = $totalResp > 0 ? 100 : 0; 

// ── Sentiment Distribution ──────────────────────────────────────────────────
$sentSql = "SELECT
                SUM(rating_value >= 4) AS pos,
                SUM(rating_value = 3)  AS neu,
                SUM(rating_value <= 2) AS neg
            FROM submission_responses sr
            JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
            LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
            WHERE {$whereSubStr}";
$sentStmt = $db->prepare($sentSql);
if ($params) $sentStmt->bind_param($types, ...$params);
$sentStmt->execute();
$sentiment = $sentStmt->get_result()->fetch_assoc();
$sentStmt->close();

$pos = (int)($sentiment['pos'] ?? 0);
$neu = (int)($sentiment['neu'] ?? 0);
$neg = (int)($sentiment['neg'] ?? 0);

// ── Category Averages ───────────────────────────────────────────────────────
$catSql = "SELECT qb.category, ROUND(AVG(sr.rating_value),2) as avg_score, COUNT(sr.response_id) as res_count
           FROM submission_responses sr
           JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
           LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
           JOIN question_bank qb ON qb.question_id = sr.question_id
           WHERE {$whereSubStr} AND sr.rating_value > 0
           GROUP BY qb.category
           ORDER BY avg_score DESC";
$catStmt = $db->prepare($catSql);
if ($params) $catStmt->bind_param($types, ...$params);
$catStmt->execute();
$catData = $catStmt->get_result();
$catStmt->close();

$catLabels = []; $catScores = [];
while ($c = $catData->fetch_assoc()) {
    $catLabels[] = $c['category'] ?: 'Uncategorized';
    $catScores[] = (float)$c['avg_score'];
}

// ── Top & Bottom Questions ──────────────────────────────────────────────────
$qSql = "SELECT qb.question_text, ROUND(AVG(sr.rating_value),2) as avg_score
         FROM submission_responses sr
         JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
         LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
         JOIN question_bank qb ON qb.question_id = sr.question_id
         WHERE {$whereSubStr} AND sr.rating_value > 0
         GROUP BY sr.question_id
         ORDER BY avg_score DESC";
$qStmt = $db->prepare($qSql);
if ($params) $qStmt->bind_param($types, ...$params);
$qStmt->execute();
$qResult = $qStmt->get_result();
$qStmt->close();

$allQ = [];
while ($q = $qResult->fetch_assoc()) $allQ[] = $q;

$totalQ = count($allQ);
$topLimit = min(5, (int)ceil($totalQ / 2));
$botLimit = min(5, $totalQ - $topLimit);

$topQ = array_slice($allQ, 0, $topLimit);
$botQ = array_slice(array_reverse($allQ), 0, $botLimit);

// ── Recent Comments ─────────────────────────────────────────────────────────
$comSql = "SELECT sc.comment_text, sc.created_at, fsub.submission_hash 
           FROM submission_comments sc
           JOIN feedback_submissions fsub ON sc.submission_id = fsub.submission_id
           LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
           WHERE sc.is_hidden = 0 AND {$whereSubStr}
           ORDER BY sc.created_at DESC LIMIT 10";
$comStmt = $db->prepare($comSql);
if ($params) $comStmt->bind_param($types, ...$params);
$comStmt->execute();
$commentsResult = $comStmt->get_result();
$comStmt->close();
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Analytics Dashboard</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Deep dive into feedback data with department-wise filtering.</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Department</label>
            <select name="department" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Departments</option>
                <?php while ($d = $departments->fetch_assoc()): ?>
                <option value="<?= $d['department_id'] ?>" <?= $deptFilter === (int)$d['department_id'] ? 'selected' : '' ?>><?= h($d['department_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Academic Year</label>
            <select name="academic_year" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Years</option>
                <?php while ($ay = $academicYears->fetch_assoc()): ?>
                <option value="<?= $ay['academic_year'] ?>" <?= $ayFilter === $ay['academic_year'] ? 'selected' : '' ?>><?= h($ay['academic_year']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Semester</label>
            <select name="semester" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $s): ?>
                <option value="<?= $s ?>" <?= $semFilter === $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Session</label>
            <select name="session" id="sessionFilter" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Sessions</option>
                <?php while ($s = $sessions->fetch_assoc()): ?>
                <option value="<?= $s['session_id'] ?>" <?= $sessFilter === (int)$s['session_id'] ? 'selected' : '' ?>><?= h($s['session_title']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Questionnaire</label>
            <select name="questionnaire" id="formFilter" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Questionnaires</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm">Filter</button>
            <?php if ($deptFilter || $sessFilter || $formFilter || $ayFilter || $semFilter): ?>
            <a href="analytics.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-xl transition-colors">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Key Metrics Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-brand-600 to-brand-800 rounded-2xl p-5 text-white shadow-sm flex flex-col justify-between">
        <p class="text-brand-100 text-xs font-semibold uppercase tracking-wider mb-2">Eligible</p>
        <div class="flex items-end justify-between">
            <p class="text-3xl font-bold"><?= number_format($totalEligible) ?></p>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-slate-700 flex flex-col justify-between">
        <p class="text-gray-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Total Responses</p>
        <div class="flex items-end justify-between">
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($totalResp) ?></p>
            <span class="text-sm font-medium text-emerald-500"><?= $responseRate ?>% Rate</span>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-slate-700 flex flex-col justify-between">
        <p class="text-gray-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Average Score</p>
        <div class="flex items-end justify-between">
            <p class="text-3xl font-bold <?= sentiment_class($avgRating) ?>"><?= number_format($avgRating, 2) ?><span class="text-lg text-gray-400 font-normal">/5</span></p>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-slate-700 flex flex-col justify-between">
        <p class="text-gray-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Completion</p>
        <div class="flex items-end justify-between">
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $completionPct ?>%</p>
            <span class="text-sm text-gray-400"><?= number_format($totalComments) ?> Comments</span>
        </div>
    </div>
</div>

<?php if ($totalResp === 0): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <p class="text-gray-500 font-medium">No data available for the selected filters.</p>
    </div>
<?php else: ?>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Category Radar -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Average Rating by Category</h3>
        <div class="h-64">
            <canvas id="catChart"></canvas>
        </div>
    </div>

    <!-- Sentiment -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Sentiment Balance</h3>
        <div class="h-48 mb-4">
            <canvas id="sentChart"></canvas>
        </div>
        <div class="space-y-2 text-xs font-medium">
            <div class="flex justify-between items-center bg-gray-50 dark:bg-slate-900/50 p-2 rounded-lg">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Positive (4-5)</span>
                <span><?= $pos ?></span>
            </div>
            <div class="flex justify-between items-center bg-gray-50 dark:bg-slate-900/50 p-2 rounded-lg">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span> Neutral (3)</span>
                <span><?= $neu ?></span>
            </div>
            <div class="flex justify-between items-center bg-gray-50 dark:bg-slate-900/50 p-2 rounded-lg">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Negative (1-2)</span>
                <span><?= $neg ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Top & Bottom Questions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Top Rated Questions</h3>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-slate-700/50">
            <?php foreach ($topQ as $q): ?>
            <div class="p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 font-bold flex items-center justify-center flex-shrink-0 text-sm">
                    <?= number_format((float)$q['avg_score'], 1) ?>
                </div>
                <p class="text-sm text-gray-800 dark:text-slate-200"><?= h($q['question_text']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Lowest Rated Questions</h3>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-slate-700/50">
            <?php foreach ($botQ as $q): ?>
            <div class="p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-50 dark:bg-red-900/20 text-red-600 font-bold flex items-center justify-center flex-shrink-0 text-sm">
                    <?= number_format((float)$q['avg_score'], 1) ?>
                </div>
                <p class="text-sm text-gray-800 dark:text-slate-200"><?= h($q['question_text']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Anonymous Comments Feed -->
<?php if ($commentsResult && $commentsResult->num_rows > 0): ?>
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Recent Anonymous Comments</h3>
        <span class="text-xs text-gray-500">Filtered by your current selection</span>
    </div>
    <div class="divide-y divide-gray-50 dark:divide-slate-700/50">
        <?php while ($c = $commentsResult->fetch_assoc()): ?>
        <div class="p-5">
            <p class="text-sm text-gray-800 dark:text-slate-200 mb-2 font-medium italic">"<?= h($c['comment_text']) ?>"</p>
            <div class="flex items-center justify-between text-xs text-gray-400">
                <span>Hash: <?= substr(h($c['submission_hash']), 0, 8) ?>...</span>
                <span><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    const isDark = document.documentElement.classList.contains('dark');
    const text = isDark ? '#cbd5e1' : '#64748b';
    const grid = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    // Category Radar Chart
    const catCtx = document.getElementById('catChart');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    label: 'Avg Score',
                    data: <?= json_encode($catScores) ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: text }, grid: { display: false } },
                    y: { ticks: { color: text }, grid: { color: grid }, min: 0, max: 5 }
                }
            }
        });
    }

    // Sentiment Doughnut
    const sentCtx = document.getElementById('sentChart');
    if (sentCtx) {
        new Chart(sentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Positive', 'Neutral', 'Negative'],
                datasets: [{
                    data: [<?= $pos ?>, <?= $neu ?>, <?= $neg ?>],
                    backgroundColor: ['#10b981', '#fbbf24', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { display: false } }
            }
        });
    }
})();
</script>

<?php endif; ?>

<!-- Script to handle dynamic Questionnaire loading -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sessionSel = document.getElementById('sessionFilter');
    const formSel = document.getElementById('formFilter');
    const currentFormId = <?= $formFilter ?>;
    
    function loadForms(sessionId) {
        if (!sessionId) {
            formSel.innerHTML = '<option value="">All Questionnaires</option>';
            return;
        }
        
        fetch('ajax_questionnaires.php?session_id=' + sessionId)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    let html = '<option value="">All Questionnaires</option>';
                    data.questionnaires.forEach(q => {
                        const sel = (currentFormId === q.id) ? 'selected' : '';
                        html += `<option value="${q.id}" ${sel}>${q.title}</option>`;
                    });
                    formSel.innerHTML = html;
                }
            })
            .catch(err => console.error(err));
    }
    
    // Load initially if session is selected
    if (sessionSel.value) {
        loadForms(sessionSel.value);
    }
    
    sessionSel.addEventListener('change', function() {
        loadForms(this.value);
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
