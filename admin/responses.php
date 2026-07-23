<?php
/**
 * AFMS Admin — Responses List (Session-Centric)
 * Displays a list of Feedback Sessions and their overall response statistics.
 */
declare(strict_types=1);

$page_title = 'Responses by Session';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── Dropdowns ──────────────────────────────────────────────────────────────────
$academicYears = $db->query("SELECT DISTINCT academic_year FROM feedback_sessions WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
$semesters = [1, 2, 3, 4, 5, 6, 7, 8];

// ── Filters ───────────────────────────────────────────────────────────────────
$search        = sanitize_input($_GET['q'] ?? '');
$ayFilter      = sanitize_input($_GET['academic_year'] ?? '');
$semFilter     = (int)($_GET['semester'] ?? 0);
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = 20;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(fs.session_title LIKE ?)';
    $s = "%{$search}%";
    $params[] = $s;
    $types .= 's';
}
if ($ayFilter !== '') {
    $where[] = 'fs.academic_year = ?';
    $params[] = $ayFilter;
    $types .= 's';
}
if ($semFilter > 0) {
    $where[] = 'fs.semester = ?';
    $params[] = $semFilter;
    $types .= 'i';
}

$whereStr = implode(' AND ', $where);

// Total count
$cSql = "SELECT COUNT(DISTINCT fs.session_id)
         FROM feedback_sessions fs
         WHERE {$whereStr}";
if ($params) {
    $stmt = $db->prepare($cSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $total = (int)$db->query($cSql)->fetch_row()[0];
}
$pg = paginate($total, $limit, $page);

// Fetch sessions
$sql = "SELECT fs.session_id, fs.session_title, fs.academic_year, fs.semester, fs.status,
               COUNT(DISTINCT q.questionnaire_id) AS questionnaire_count,
               COUNT(DISTINCT fsub.submission_id) AS total_responses,
               (SELECT ROUND(AVG(rating_value),1) 
                FROM submission_responses sr 
                JOIN feedback_submissions fsub2 ON sr.submission_id = fsub2.submission_id 
                WHERE fsub2.session_id = fs.session_id) AS avg_rating
        FROM feedback_sessions fs
        LEFT JOIN questionnaires q ON q.session_id = fs.session_id
        LEFT JOIN feedback_submissions fsub ON fsub.session_id = fs.session_id
        WHERE {$whereStr}
        GROUP BY fs.session_id
        ORDER BY fs.created_at DESC
        LIMIT ? OFFSET ?";

$fParams = $params;
$fParams[] = $limit;
$fParams[] = $pg['offset'];
$fTypes = $types . 'ii';

$stmt = $db->prepare($sql);
if ($fParams) {
    $stmt->bind_param($fTypes, ...$fParams);
}
$stmt->execute();
$sessions = $stmt->get_result();
$stmt->close();
?>

<div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Responses by Session</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Showing aggregated feedback submissions grouped by session. Total sessions: <?= $total ?></p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 mb-5">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Search Session</label>
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by title..." class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
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
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-gray-800 dark:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-gray-700 transition-colors shadow-sm">Filter</button>
            <?php if ($search || $ayFilter || $semFilter): ?>
            <a href="responses.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-xl transition-colors">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">
                    <th class="px-5 py-3">Session Title</th>
                    <th class="px-4 py-3 text-center">Forms</th>
                    <th class="px-4 py-3 text-center">Total Responses</th>
                    <th class="px-4 py-3 text-center">Avg Rating</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $sessions->fetch_assoc()):
                    $hasRows = true;
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-gray-800 dark:text-slate-200"><?= h($row['session_title']) ?></p>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                            <?= h($row['academic_year']) ?> <?= $row['semester'] ? ' - Sem ' . $row['semester'] : '' ?>
                            <span class="inline-block ml-2 px-1.5 py-0.5 rounded text-[10px] bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 font-semibold"><?= h($row['status']) ?></span>
                        </p>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="font-semibold text-gray-700 dark:text-slate-300"><?= $row['questionnaire_count'] ?></span>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 dark:text-slate-200">
                            <?= number_format((float)$row['total_responses']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($row['avg_rating'] !== null): ?>
                        <span class="font-bold <?= sentiment_class((float)$row['avg_rating']) ?>"><?= number_format((float)$row['avg_rating'], 1) ?></span>
                        <?php else: ?>
                        <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="session_responses.php?id=<?= $row['session_id'] ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-800 dark:hover:text-brand-300 transition-colors">
                            View Responses <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if (!$hasRows): ?>
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-gray-500 font-medium">
                        No sessions found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['q' => $search, 'academic_year' => $ayFilter, 'semester' => $semFilter]))) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
