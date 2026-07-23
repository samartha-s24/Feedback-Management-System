<?php
/**
 * AFMS Admin — Announcements
 * Manage system-wide announcements with audience targeting.
 */
declare(strict_types=1);

$page_title = 'Announcements';
require_once __DIR__ . '/header.php';

$db = get_db();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusFilter = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;

$where = "1=1";
if ($statusFilter !== '') {
    $where .= " AND a.status = '" . $db->real_escape_string($statusFilter) . "'";
}

// Total count
$total = (int)$db->query("SELECT COUNT(*) FROM announcements a WHERE {$where}")->fetch_row()[0];
$pg = paginate($total, $limit, $page);

// Fetch announcements
$sql = "SELECT a.*, u.full_name as author_name, fs.session_title,
               (SELECT GROUP_CONCAT(target_role SEPARATOR ', ') FROM announcement_audience aa WHERE aa.announcement_id = a.announcement_id) as roles
        FROM announcements a
        JOIN users u ON u.user_id = a.created_by
        LEFT JOIN feedback_sessions fs ON fs.session_id = a.linked_session_id
        WHERE {$where}
        ORDER BY a.created_at DESC
        LIMIT {$limit} OFFSET {$pg['offset']}";
$announcements = $db->query($sql);

$sessions = $db->query("SELECT session_id, session_title FROM feedback_sessions WHERE status IN ('Draft','Published','Active') ORDER BY created_at DESC");
?>

<?php if ($flash): ?>
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?> border text-sm" data-auto-dismiss="4000">
    <?= h($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div id="add-modal" class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden transform scale-95 transition-transform" id="add-modal-panel">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 dark:text-white" id="modal-title">Create Announcement</h3>
            <button onclick="toggleModal(false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="add-form" action="announcement_actions.php" method="POST" class="px-6 py-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="announcement_id" id="inp-id" value="">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="inp-title" required class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Description</label>
                <textarea name="description" id="inp-desc" rows="3" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 resize-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Priority</label>
                    <select name="priority" id="inp-pri" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Status</label>
                    <select name="status" id="inp-status" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <option value="Draft">Draft</option>
                        <option value="Published">Published</option>
                        <option value="Expired">Expired</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Start Date</label>
                    <input type="date" name="start_date" id="inp-start" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">End Date</label>
                    <input type="date" name="end_date" id="inp-end" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Link to Session (Optional)</label>
                <select name="linked_session_id" id="inp-session" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    <option value="">— None —</option>
                    <?php while ($s = $sessions->fetch_assoc()): ?>
                    <option value="<?= $s['session_id'] ?>"><?= h($s['session_title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Target Audience <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['All','Student','Faculty','Parent','Alumni','Employee'] as $role): ?>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="target_roles[]" value="<?= $role ?>" class="inp-role w-4 h-4 accent-brand-600 rounded">
                        <span class="text-sm text-gray-700 dark:text-slate-300"><?= $role ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="toggleModal(false)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors shadow-sm">Save Announcement</button>
            </div>
        </form>
    </div>
</div>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Announcements</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage system-wide alerts and notifications.</p>
    </div>
    <button onclick="openAddModal()" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Announcement
    </button>
</div>

<!-- Filters -->
<form method="GET" class="flex gap-3 mb-5">
    <select name="status" class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200">
        <option value="">All Statuses</option>
        <?php foreach (['Draft','Published','Expired'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bg-gray-800 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 transition-colors">Filter</button>
    <?php if ($statusFilter): ?>
    <a href="announcements.php" class="flex items-center gap-1 text-red-500 hover:text-red-600 text-sm px-2">Clear</a>
    <?php endif; ?>
</form>

<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">
                    <th class="px-5 py-3">Title</th>
                    <th class="px-4 py-3">Audience</th>
                    <th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Priority</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $announcements->fetch_assoc()):
                    $hasRows = true;
                    $id = (int)$row['announcement_id'];
                    $data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr id="ann-row-<?= $id ?>" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-gray-800 dark:text-slate-200"><?= h($row['title']) ?></p>
                        <?php if ($row['session_title']): ?>
                        <p class="text-[10px] text-gray-500 dark:text-slate-400 mt-1">🔗 <?= h($row['session_title']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-600 dark:text-slate-400"><?= h($row['roles'] ?: '—') ?></td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">
                        <?php
                        $aStart = !empty($row['start_date']) && strpos($row['start_date'], '0000') !== 0 ? strtotime($row['start_date']) : false;
                        $aEnd = !empty($row['end_date']) && strpos($row['end_date'], '0000') !== 0 ? strtotime($row['end_date']) : false;
                        if ($aStart && $aEnd) {
                            echo date('d M', $aStart) . ' – ' . date('d M Y', $aEnd);
                        } elseif ($aStart) {
                            echo 'From ' . date('d M Y', $aStart);
                        } elseif ($aEnd) {
                            echo 'Until ' . date('d M Y', $aEnd);
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td class="px-4 py-3.5"><?= status_badge($row['priority']) ?></td>
                    <td class="px-4 py-3.5"><?= status_badge($row['status']) ?></td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditModal(<?= $data ?>)" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="announcement_actions.php" class="inline" onsubmit="return confirm('Delete this announcement?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$hasRows): ?>
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500 font-medium">No announcements found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['status' => $statusFilter]))) ?>
</div>
<?php endif; ?>

<script>
const modal = document.getElementById('add-modal');
const panel = document.getElementById('add-modal-panel');

function toggleModal(show) {
    if (show) {
        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        panel.classList.remove('scale-95');
    } else {
        modal.classList.add('opacity-0');
        panel.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 200);
    }
}

function openAddModal() {
    document.getElementById('add-form').reset();
    document.getElementById('inp-id').value = '';
    document.getElementById('modal-title').textContent = 'Create Announcement';
    toggleModal(true);
}

function openEditModal(data) {
    document.getElementById('inp-id').value = data.announcement_id;
    document.getElementById('inp-title').value = data.title;
    document.getElementById('inp-desc').value = data.description || '';
    document.getElementById('inp-pri').value = data.priority;
    document.getElementById('inp-status').value = data.status;
    document.getElementById('inp-start').value = data.start_date || '';
    document.getElementById('inp-end').value = data.end_date || '';
    document.getElementById('inp-session').value = data.linked_session_id || '';
    
    document.querySelectorAll('.inp-role').forEach(cb => cb.checked = false);
    if (data.roles) {
        const roles = data.roles.split(', ');
        document.querySelectorAll('.inp-role').forEach(cb => {
            if (roles.includes(cb.value)) cb.checked = true;
        });
    }
    
    document.getElementById('modal-title').textContent = 'Edit Announcement';
    toggleModal(true);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
