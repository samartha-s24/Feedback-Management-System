<?php
/**
 * AFMS Admin — Reports
 * Build custom tabular reports and export to CSV.
 */
declare(strict_types=1);

$page_title = 'Reports';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── Dropdowns ────────────────────────────────────────────────────────────────
$departments = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$sessions = $db->query("SELECT session_id, session_title FROM feedback_sessions ORDER BY created_at DESC");
$academicYears = $db->query("SELECT DISTINCT academic_year FROM feedback_sessions WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
$semesters = [1, 2, 3, 4, 5, 6, 7, 8];

// ── Form State ───────────────────────────────────────────────────────────────
$deptFilter = (int)($_GET['department'] ?? 0);
$ayFilter   = sanitize_input($_GET['academic_year'] ?? '');
$semFilter  = (int)($_GET['semester'] ?? 0);
$sessId  = (int)($_GET['session'] ?? 0);
$formId  = (int)($_GET['questionnaire'] ?? 0);
$groupBy = sanitize_input($_GET['groupby'] ?? 'question');

$whereSub = ["1=1"];
$params = [];
$types  = '';

if ($deptFilter > 0) { $whereSub[] = "fsub.department_id = ?"; $params[] = $deptFilter; $types .= 'i'; }
if ($sessId > 0) { $whereSub[] = "fsub.session_id = ?"; $params[] = $sessId; $types .= 'i'; }
if ($formId > 0) { $whereSub[] = "fsub.questionnaire_id = ?"; $params[] = $formId; $types .= 'i'; }
if ($ayFilter !== '') { $whereSub[] = "fs.academic_year = ?"; $params[] = $ayFilter; $types .= 's'; }
if ($semFilter > 0) { $whereSub[] = "fs.semester = ?"; $params[] = $semFilter; $types .= 'i'; }

$whereSubStr = implode(" AND ", $whereSub);

$reportData = [];

// Only run report if at least one filter is applied or "Run" is explicitly pressed
if (isset($_GET['run'])) {
    if ($groupBy === 'question') {
        $sql = "SELECT qb.question_text, qb.category, qb.question_type,
                       COUNT(sr.response_id) as total_responses,
                       ROUND(AVG(sr.rating_value),2) as avg_score,
                       SUM(sr.rating_value >= 4) as pos_count,
                       SUM(sr.rating_value <= 2) as neg_count
                FROM submission_responses sr
                JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
                LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
                JOIN question_bank qb ON qb.question_id = sr.question_id
                WHERE {$whereSubStr}
                GROUP BY qb.question_id
                ORDER BY qb.category, avg_score DESC";
    } else {
        // category
        $sql = "SELECT qb.category,
                       COUNT(sr.response_id) as total_responses,
                       ROUND(AVG(sr.rating_value),2) as avg_score,
                       SUM(sr.rating_value >= 4) as pos_count,
                       SUM(sr.rating_value <= 2) as neg_count
                FROM submission_responses sr
                JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
                LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
                JOIN question_bank qb ON qb.question_id = sr.question_id
                WHERE {$whereSubStr}
                GROUP BY qb.category
                ORDER BY avg_score DESC";
    }

    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $reportData[] = $r;
    $stmt->close();
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Custom Reports</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Generate tabular data for analysis and export to CSV.</p>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <input type="hidden" name="run" value="1">
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
                <option value="<?= $s['session_id'] ?>" <?= $sessId === (int)$s['session_id'] ? 'selected' : '' ?>><?= h($s['session_title']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Questionnaire</label>
            <select name="questionnaire" id="formFilter" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Questionnaires</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Group By</label>
            <select name="groupby" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="question" <?= $groupBy === 'question' ? 'selected' : '' ?>>Question</option>
                <option value="category" <?= $groupBy === 'category' ? 'selected' : '' ?>>Category</option>
            </select>
        </div>
        <div class="md:col-span-6 flex gap-2">
            <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm">Run Report</button>
            <?php if (isset($_GET['run'])): ?>
            <a href="reports.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-xl transition-colors">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (isset($_GET['run'])): ?>
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Report Results</h3>
        <?php if (!empty($reportData)): ?>
        <form method="POST" action="export.php">
            <?= csrf_field() ?>
            <input type="hidden" name="department" value="<?= $deptFilter ?>">
            <input type="hidden" name="academic_year" value="<?= h($ayFilter) ?>">
            <input type="hidden" name="semester" value="<?= $semFilter ?>">
            <input type="hidden" name="session" value="<?= $sessId ?>">
            <input type="hidden" name="questionnaire" value="<?= $formId ?>">
            <input type="hidden" name="groupby" value="<?= $groupBy ?>">
            <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">
                    <?php if ($groupBy === 'question'): ?>
                    <th class="px-5 py-3">Question</th>
                    <th class="px-4 py-3">Category</th>
                    <?php else: ?>
                    <th class="px-5 py-3">Category</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-center">Responses</th>
                    <th class="px-4 py-3 text-center">Avg Score</th>
                    <th class="px-4 py-3 text-center">Positive (4-5)</th>
                    <th class="px-5 py-3 text-center">Negative (1-2)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php foreach ($reportData as $row): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <?php if ($groupBy === 'question'): ?>
                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-slate-200"><?= h($row['question_text']) ?></td>
                    <td class="px-4 py-3 text-gray-500 dark:text-slate-400"><?= h($row['category'] ?: '—') ?></td>
                    <?php else: ?>
                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-slate-200"><?= h($row['category'] ?: 'Uncategorized') ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-center"><?= $row['total_responses'] ?></td>
                    <td class="px-4 py-3 text-center font-bold <?= sentiment_class((float)$row['avg_score']) ?>"><?= number_format((float)$row['avg_score'], 2) ?></td>
                    <td class="px-4 py-3 text-center text-emerald-600"><?= $row['pos_count'] ?></td>
                    <td class="px-5 py-3 text-center text-red-600"><?= $row['neg_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reportData)): ?>
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No data found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Script to handle dynamic Questionnaire loading -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sessionSel = document.getElementById('sessionFilter');
    const formSel = document.getElementById('formFilter');
    const currentFormId = <?= $formId ?>;
    
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
