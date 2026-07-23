<?php
/**
 * AFMS Admin — Questionnaires List
 */
declare(strict_types=1);

$page_title = 'Questionnaires';
require_once __DIR__ . '/header.php';

$db    = get_db();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = sanitize_input($_GET['q'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(q.title LIKE ? OR fs.session_title LIKE ?)';
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s;
    $types   .= 'ss';
}
if ($status !== '') {
    $where[] = 'q.status = ?';
    $params[] = $status;
    $types   .= 's';
}

$whereStr = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM questionnaires q JOIN feedback_sessions fs ON fs.session_id = q.session_id WHERE {$whereStr}";
if ($params) {
    $cStmt = $db->prepare($countSql);
    $cStmt->bind_param($types, ...$params);
    $cStmt->execute();
    $total = (int)$cStmt->get_result()->fetch_row()[0];
    $cStmt->close();
} else {
    $total = (int)$db->query($countSql)->fetch_row()[0];
}

$pg = paginate($total, $limit, $page);

$sql = "SELECT q.questionnaire_id, q.title, q.status, q.created_at, q.updated_at,
               fs.session_title, fs.status AS session_status, fs.session_id,
               (SELECT COUNT(*) FROM questionnaire_questions qq WHERE qq.questionnaire_id = q.questionnaire_id) AS question_count,
               (SELECT COUNT(*) FROM feedback_submissions fsub WHERE fsub.questionnaire_id = q.questionnaire_id) AS response_count
        FROM questionnaires q
        JOIN feedback_sessions fs ON fs.session_id = q.session_id
        WHERE {$whereStr}
        ORDER BY q.updated_at DESC
        LIMIT ? OFFSET ?";

$fetchParams = $params;
$fetchParams[] = $limit;
$fetchParams[] = $pg['offset'];

$stmt = $db->prepare($sql);
$stmt->bind_param($types . 'ii', ...$fetchParams);
$stmt->execute();
$forms = $stmt->get_result();
$stmt->close();
?>

<?php if ($flash): ?>
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?> text-sm" data-auto-dismiss="4000">
    <?= h($flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Questionnaires</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5"><?= $total ?> form<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="form_builder.php"
       class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Questionnaire
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" class="flex flex-col sm:flex-row gap-3 mb-5">
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search questionnaires or sessions…"
               class="form-input w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200">
    </div>
    <select name="status" class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200">
        <option value="">All Statuses</option>
        <?php foreach (['Draft','Active','Closed'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bg-gray-800 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 transition-colors">Filter</button>
</form>

<!-- Questionnaires Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-5">
    <?php
    $hasRows = false;
    while ($row = $forms->fetch_assoc()):
        $hasRows = true;
        $qid = (int)$row['questionnaire_id'];
    ?>
    <div id="form-card-<?= $qid ?>" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all overflow-hidden">
        <!-- Card Header -->
        <div class="px-5 pt-4 pb-3 border-b border-gray-50 dark:border-slate-700/50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-800 dark:text-slate-200 text-sm truncate"><?= h($row['title']) ?></h3>
                    <a href="sessions.php?highlight=<?= $row['session_id'] ?>" class="text-xs text-brand-600 dark:text-brand-400 hover:underline truncate block mt-0.5">
                        <?= h($row['session_title']) ?>
                    </a>
                </div>
                <?= status_badge($row['status']) ?>
            </div>
        </div>
        <!-- Stats -->
        <div class="px-5 py-3 grid grid-cols-2 gap-3 border-b border-gray-50 dark:border-slate-700/50">
            <div class="text-center">
                <p class="text-xl font-bold text-gray-900 dark:text-white"><?= $row['question_count'] ?></p>
                <p class="text-[10px] text-gray-400 dark:text-slate-500 uppercase font-medium">Questions</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-gray-900 dark:text-white"><?= $row['response_count'] ?></p>
                <p class="text-[10px] text-gray-400 dark:text-slate-500 uppercase font-medium">Responses</p>
            </div>
        </div>
        <!-- Footer Actions -->
        <div class="px-5 py-3 flex items-center justify-between">
            <span class="text-[10px] text-gray-400 dark:text-slate-500">Updated <?= date('d M Y', strtotime($row['updated_at'])) ?></span>
            <div class="flex items-center gap-1">
                <a href="form_builder.php?id=<?= $qid ?>" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-colors" title="Edit">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
                <a href="analytics.php?questionnaire=<?= $qid ?>" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors" title="Analytics">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </a>
                <button onclick="confirmAction('Delete this questionnaire and all its responses?', () => deleteForm(<?= $qid ?>))"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <?php if (!$hasRows): ?>
    <div class="md:col-span-2 xl:col-span-3 py-16 text-center">
        <svg class="w-14 h-14 text-gray-200 dark:text-slate-700 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-gray-500 dark:text-slate-400 font-semibold">No questionnaires found</p>
        <a href="form_builder.php" class="mt-2 inline-block text-sm text-brand-600 dark:text-brand-400 hover:underline">Build your first questionnaire →</a>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['q' => $search, 'status' => $status]))) ?>
</div>
<?php endif; ?>

<script>
function deleteForm(id) {
    afmsPost('form_actions.php', { action: 'delete', id, csrf_token: '<?= h(get_csrf_token()) ?>' }, res => {
        showToast(res.message, 'success');
        document.getElementById('form-card-' + id)?.remove();
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
