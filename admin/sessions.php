<?php
/**
 * AFMS Admin — Feedback Sessions List
 * Full CRUD table: search, filter, sort, pagination, inline actions.
 */
declare(strict_types=1);

$page_title = 'Feedback Sessions';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── Flash message from actions ────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Filters ───────────────────────────────────────────────────────────────────
$search = sanitize_input($_GET['q'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$year   = sanitize_input($_GET['year'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(fs.session_title LIKE ? OR fs.academic_year LIKE ?)';
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s;
    $types   .= 'ss';
}
if ($status !== '') {
    $where[] = 'fs.status = ?';
    $params[] = $status;
    $types   .= 's';
}
if ($year !== '') {
    $where[] = 'fs.academic_year = ?';
    $params[] = $year;
    $types   .= 's';
}

$whereStr = implode(' AND ', $where);

// Total count
$countSql = "SELECT COUNT(*) FROM feedback_sessions fs WHERE {$whereStr}";
if ($params) {
    $stmt = $db->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
} else {
    $total = (int)$db->query($countSql)->fetch_row()[0];
}

$pg = paginate($total, $limit, $page);

// Fetch rows
$sql = "SELECT fs.session_id, fs.session_title, fs.status, fs.academic_year, fs.semester,
               fs.start_date, fs.start_time, fs.end_date, fs.end_time,
               fs.max_attempts, fs.result_visibility,
               u.full_name AS created_by_name,
               (SELECT COUNT(*) FROM feedback_submissions fsub WHERE fsub.session_id = fs.session_id) AS response_count,
               (SELECT GROUP_CONCAT(target_role ORDER BY target_role SEPARATOR ', ')
                FROM session_target_roles WHERE session_id = fs.session_id) AS target_roles
        FROM feedback_sessions fs
        JOIN users u ON u.user_id = fs.created_by
        WHERE {$whereStr}
        ORDER BY fs.created_at DESC
        LIMIT ? OFFSET ?";

$fetchParams = $params;
$fetchParams[] = $limit;
$fetchParams[] = $pg['offset'];
$fetchTypes   = $types . 'ii';

$stmt = $db->prepare($sql);
if ($fetchParams) {
    $stmt->bind_param($fetchTypes, ...$fetchParams);
}
$stmt->execute();
$sessions = $stmt->get_result();
$stmt->close();

// Status tab counts
$tabCounts = [];
foreach (['Active','Draft','Published','Closed','Archived'] as $s) {
    $tabCounts[$s] = (int)$db->query("SELECT COUNT(*) FROM feedback_sessions WHERE status='{$s}'")->fetch_row()[0];
}
?>

<?php if ($flash): ?>
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?> text-sm" data-auto-dismiss="4000">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $flash['type'] === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' ?>"/>
    </svg>
    <?= h($flash['message']) ?>
</div>
<?php endif; ?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Feedback Sessions</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5"><?= $total ?> session<?= $total !== 1 ? 's' : '' ?> total</p>
    </div>
    <a href="session_form.php"
       class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Session
    </a>
</div>

<!-- ── Status Filter Tabs ─────────────────────────────────────────────────── -->
<div class="flex flex-wrap gap-2 mb-5 border-b border-gray-200 dark:border-slate-700 pb-3">
    <?php
    $allCount = array_sum($tabCounts);
    $tabDef   = ['' => ['All', $allCount]] + array_map(fn($c) => [null, $c], $tabCounts);
    $tabLabels = ['' => 'All', 'Active' => 'Active', 'Draft' => 'Draft', 'Published' => 'Published', 'Closed' => 'Closed', 'Archived' => 'Archived'];
    foreach ($tabLabels as $val => $lbl):
        $count  = ($val === '') ? $allCount : ($tabCounts[$val] ?? 0);
        $active = ($status === $val);
        $cls    = $active ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-600';
    ?>
    <a href="?<?= http_build_query(array_filter(['status' => $val, 'q' => $search, 'year' => $year])) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors <?= $cls ?>">
        <?= $lbl ?> <span class="<?= $active ? 'bg-white/25' : 'bg-gray-200 dark:bg-slate-600' ?> text-[10px] px-1.5 py-0.5 rounded-full"><?= $count ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Search + Filter Bar ─────────────────────────────────────────────────── -->
<form method="GET" class="flex flex-col sm:flex-row gap-3 mb-5">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search sessions…"
               class="form-input w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 dark:placeholder-slate-500">
    </div>
    <input type="text" name="year" value="<?= h($year) ?>" placeholder="Acad. Year (e.g. 2025-2026)"
           class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 dark:placeholder-slate-500 w-full sm:w-52">
    <button type="submit" class="bg-gray-800 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 dark:hover:bg-slate-600 transition-colors">Filter</button>
    <?php if ($search || $year): ?>
    <a href="sessions.php<?= $status ? '?status=' . h($status) : '' ?>" class="flex items-center gap-1 text-red-500 hover:text-red-600 text-sm px-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        Clear
    </a>
    <?php endif; ?>
</form>

<!-- ── Sessions Table ─────────────────────────────────────────────────────── -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-5">
    <div class="overflow-x-auto">
        <table class="data-table w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-slate-700">
                    <th class="text-left px-5 py-3.5">Session Name</th>
                    <th class="text-left px-4 py-3.5">Audience</th>
                    <th class="text-left px-4 py-3.5">Period</th>
                    <th class="text-center px-4 py-3.5">Responses</th>
                    <th class="text-left px-4 py-3.5">Status</th>
                    <th class="text-left px-4 py-3.5">Visibility</th>
                    <th class="text-right px-5 py-3.5">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $sessions->fetch_assoc()):
                    $hasRows = true;
                    $sid = (int)$row['session_id'];
                ?>
                <tr id="session-row-<?= $sid ?>" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="session_form.php?id=<?= $sid ?>" class="font-semibold text-gray-800 dark:text-slate-200 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                            <?= h($row['session_title']) ?>
                        </a>
                        <?php if ($row['academic_year']): ?>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">
                            <?= h($row['academic_year']) ?><?= $row['semester'] ? ' · Sem ' . $row['semester'] : '' ?>
                        </p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (explode(', ', $row['target_roles'] ?? '') as $role): ?>
                            <?php if (trim($role)): ?>
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300"><?= h(trim($role)) ?></span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">
                        <?php
                        $sStart = !empty($row['start_date']) && strpos($row['start_date'], '0000') !== 0 ? strtotime($row['start_date']) : false;
                        $sEnd = !empty($row['end_date']) && strpos($row['end_date'], '0000') !== 0 ? strtotime($row['end_date']) : false;
                        if ($sStart && $sEnd): ?>
                        <p><?= date('d M Y', $sStart) ?></p>
                        <p class="text-gray-400 dark:text-slate-600">→ <?= date('d M Y', $sEnd) ?></p>
                        <?php elseif ($sStart): ?>
                        <p><?= date('d M Y', $sStart) ?></p>
                        <?php elseif ($sEnd): ?>
                        <p class="text-gray-400 dark:text-slate-600">Until <?= date('d M Y', $sEnd) ?></p>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="font-semibold text-gray-800 dark:text-slate-200"><?= $row['response_count'] ?></span>
                    </td>
                    <td class="px-4 py-3.5"><?= status_badge($row['status']) ?></td>
                    <td class="px-4 py-3.5">
                        <?php
                        $visCls = ['Private' => 'text-slate-500', 'Published' => 'text-emerald-600', 'Archived' => 'text-purple-600'][$row['result_visibility']] ?? 'text-gray-500';
                        ?>
                        <span class="text-xs font-medium <?= $visCls ?>"><?= h($row['result_visibility']) ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="session_detail.php?id=<?= $sid ?>"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors" title="Manage Questionnaires">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </a>
                            <a href="session_form.php?id=<?= $sid ?>"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-colors" title="Edit Session">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>

                            <?php if ($row['status'] === 'Draft' || $row['status'] === 'Published'): ?>
                            <button onclick="sessionAction(<?= $sid ?>, 'activate')"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors" title="Activate">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            <?php endif; ?>

                            <?php if ($row['status'] === 'Active'): ?>
                            <button onclick="sessionAction(<?= $sid ?>, 'close')"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Close Session">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                            </button>
                            <?php endif; ?>

                            <?php if ($row['status'] === 'Closed'): ?>
                            <button onclick="sessionAction(<?= $sid ?>, 'archive')"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors" title="Archive">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            </button>
                            <a href="analytics.php?session=<?= $sid ?>"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors" title="Analytics">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </a>
                            <a href="session_publish_report.php?session=<?= $sid ?>"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-colors" title="Publish Report">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                            <?php endif; ?>

                            <?php if (!in_array($row['status'], ['Archived'])): ?>
                            <button onclick="confirmAction('Delete this session and all its data? This cannot be undone.', () => sessionAction(<?= $sid ?>, 'delete'))"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if (!$hasRows): ?>
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <svg class="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-gray-500 dark:text-slate-400 font-medium">No sessions found</p>
                        <p class="text-sm text-gray-400 dark:text-slate-500 mt-1">
                            <?= $search || $status ? 'Try adjusting your filters.' : '' ?>
                            <a href="session_form.php" class="text-brand-600 dark:text-brand-400 hover:underline ml-1">Create your first session →</a>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<?php if ($pg['total_pages'] > 1): ?>
<div class="flex items-center justify-between text-sm text-gray-500 dark:text-slate-400">
    <p>Showing <?= $pg['offset'] + 1 ?>–<?= min($pg['offset'] + $limit, $total) ?> of <?= $total ?></p>
    <?= render_pagination($pg, http_build_query(array_filter(['q' => $search, 'status' => $status, 'year' => $year]))) ?>
</div>
<?php endif; ?>

<script>
function sessionAction(id, action) {
    const csrf = '<?= h(get_csrf_token()) ?>';
    afmsPost('session_actions.php', { id, action, csrf_token: csrf }, (res) => {
        showToast(res.message || 'Done!', 'success');
        if (action === 'delete') {
            const row = document.getElementById('session-row-' + id);
            if (row) row.remove();
        } else {
            setTimeout(() => location.reload(), 800);
        }
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
