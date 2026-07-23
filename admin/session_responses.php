<?php
/**
 * AFMS Admin — Session Responses
 * Anonymized view of all feedback submissions for a specific session.
 */
declare(strict_types=1);

$page_title = 'Session Responses';
require_once __DIR__ . '/header.php';

$db = get_db();
$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId <= 0) {
    header('Location: responses.php');
    exit;
}

// Fetch session info
$stmt = $db->prepare("SELECT * FROM feedback_sessions WHERE session_id = ?");
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$session) {
    header('Location: responses.php');
    exit;
}

// Fetch departments for filter
$departments = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name");

// Fetch questionnaires for this session for filter
$qStmt = $db->prepare("SELECT questionnaire_id, title FROM questionnaires WHERE session_id = ? ORDER BY created_at DESC");
$qStmt->bind_param('i', $sessionId);
$qStmt->execute();
$questionnaires = $qStmt->get_result();
$qStmt->close();

// Filters
$deptFilter = (int)($_GET['department'] ?? 0);
$formFilter = (int)($_GET['form'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;

$where  = ['fsub.session_id = ?'];
$params = [$sessionId];
$types  = 'i';

if ($deptFilter > 0) {
    $where[] = 'fsub.department_id = ?';
    $params[] = $deptFilter;
    $types .= 'i';
}
if ($formFilter > 0) {
    $where[] = 'fsub.questionnaire_id = ?';
    $params[] = $formFilter;
    $types .= 'i';
}

$whereStr = implode(' AND ', $where);

// Total count
$cSql = "SELECT COUNT(*) FROM feedback_submissions fsub WHERE {$whereStr}";
$stmt = $db->prepare($cSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();

$pg = paginate($total, $limit, $page);

// Fetch submissions
$sql = "SELECT fsub.submission_id, fsub.submission_hash, fsub.submitted_at,
               q.title as form_title,
               (SELECT ROUND(AVG(rating_value),1) FROM submission_responses sr WHERE sr.submission_id = fsub.submission_id) as avg_rating,
               (SELECT COUNT(*) FROM submission_comments sc WHERE sc.submission_id = fsub.submission_id) as comment_count
        FROM feedback_submissions fsub
        JOIN questionnaires q ON q.questionnaire_id = fsub.questionnaire_id
        WHERE {$whereStr}
        ORDER BY fsub.submitted_at DESC
        LIMIT ? OFFSET ?";

$fParams = $params;
$fParams[] = $limit;
$fParams[] = $pg['offset'];
$fTypes = $types . 'ii';

$stmt = $db->prepare($sql);
$stmt->bind_param($fTypes, ...$fParams);
$stmt->execute();
$submissions = $stmt->get_result();
$stmt->close();
?>

<div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <a href="responses.php" class="text-gray-400 hover:text-brand-600 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Responses: <?= h($session['session_title']) ?></h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Total submissions for this session: <?= $total ?></p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 mb-5">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="id" value="<?= $sessionId ?>">
        
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
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Questionnaire</label>
            <select name="form" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                <option value="">All Questionnaires</option>
                <?php while ($q = $questionnaires->fetch_assoc()): ?>
                <option value="<?= $q['questionnaire_id'] ?>" <?= $formFilter === (int)$q['questionnaire_id'] ? 'selected' : '' ?>><?= h($q['title']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-gray-800 dark:bg-slate-700 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-gray-700 transition-colors shadow-sm">Filter</button>
            <?php if ($deptFilter || $formFilter): ?>
            <a href="session_responses.php?id=<?= $sessionId ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-xl transition-colors">Clear</a>
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
                    <th class="px-5 py-3">Submission ID</th>
                    <th class="px-4 py-3">Questionnaire</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-center">Avg Rating</th>
                    <th class="px-4 py-3 text-center">Comments</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $submissions->fetch_assoc()):
                    $hasRows = true;
                    $shortHash = substr($row['submission_hash'], 0, 8) . '...';
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <span class="font-mono text-xs text-gray-600 dark:text-slate-400 bg-gray-100 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-gray-200 dark:border-slate-700">#<?= $row['submission_id'] ?></span>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1" title="<?= h($row['submission_hash']) ?>">Hash: <?= $shortHash ?></p>
                    </td>
                    <td class="px-4 py-3.5">
                        <p class="font-medium text-gray-800 dark:text-slate-200"><?= h($row['form_title']) ?></p>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-600 dark:text-slate-400">
                        <?= date('M j, Y', strtotime($row['submitted_at'])) ?><br>
                        <span class="text-[10px] text-gray-400"><?= date('h:i A', strtotime($row['submitted_at'])) ?></span>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($row['avg_rating'] !== null): ?>
                        <span class="font-bold <?= sentiment_class((float)$row['avg_rating']) ?>"><?= number_format((float)$row['avg_rating'], 1) ?></span>
                        <?php else: ?>
                        <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($row['comment_count'] > 0): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-full">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            <?= $row['comment_count'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="response_detail.php?id=<?= $row['submission_id'] ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-800 dark:hover:text-brand-300 transition-colors">
                            View Details <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if (!$hasRows): ?>
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-gray-500 font-medium">
                        No responses found for this session.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['id' => $sessionId, 'department' => $deptFilter, 'form' => $formFilter]))) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
