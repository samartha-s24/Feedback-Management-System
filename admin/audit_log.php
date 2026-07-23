<?php
/**
 * AFMS Admin — Audit Log
 * View system activity and administrative actions.
 */
declare(strict_types=1);

$page_title = 'Audit Log';
require_once __DIR__ . '/header.php';

$db = get_db();

// Filtering
$search = sanitize_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (al.action LIKE ? OR u.full_name LIKE ? OR al.table_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

// Total count
$count_sql = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.admin_id = u.user_id WHERE {$where}";
$stmt = $db->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();

$pg = paginate($total, $limit, $page);

// Fetch logs
$sql = "SELECT al.*, u.full_name as admin_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.admin_id = u.user_id 
        WHERE {$where} 
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$types .= "ii";
$params[] = $limit;
$params[] = $pg['offset'];
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Audit Log</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track administrative actions and system events.</p>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="flex gap-3 mb-5">
    <div class="relative flex-1 max-w-md">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by action, admin, or table..." class="form-input w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
    </div>
    <button type="submit" class="bg-gray-800 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 transition-colors">Search</button>
    <?php if ($search): ?>
    <a href="audit_log.php" class="flex items-center gap-1 text-red-500 hover:text-red-600 text-sm px-2">Clear</a>
    <?php endif; ?>
</form>

<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">
                    <th class="px-5 py-3 w-40">Date & Time</th>
                    <th class="px-4 py-3">Admin</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Target</th>
                    <th class="px-4 py-3">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $logs->fetch_assoc()):
                    $hasRows = true;
                    $details_data = json_decode($row['details'] ?? '', true);
                    $details = is_array($details_data) ? $details_data : (string)($row['details'] ?? '');
                    $target = $row['table_name'] ? h($row['table_name']) . ($row['record_id'] ? ' (#' . $row['record_id'] . ')' : '') : '—';
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3 text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">
                        <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-slate-200">
                        <?= h($row['admin_name'] ?: 'System') ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-gray-800 dark:text-slate-200 font-medium"><?= h($row['action']) ?></span>
                        <?php if ($details && is_array($details)): ?>
                        <div class="mt-1 text-[11px] text-gray-500 dark:text-slate-400 font-mono bg-gray-50 dark:bg-slate-900 p-1.5 rounded-md inline-block">
                            <?= h(json_encode($details, JSON_UNESCAPED_SLASHES)) ?>
                        </div>
                        <?php elseif ($details): ?>
                        <div class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">
                            <?= h($details) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-slate-400 font-mono">
                        <?= $target ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-slate-400">
                        <?= h($row['ip_address'] ?: '—') ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$hasRows): ?>
                <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500 font-medium">No audit logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['search' => $search]))) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
