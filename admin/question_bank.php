<?php
/**
 * AFMS Admin — Question Bank
 * Inline creation and list management for all feedback questions.
 */
declare(strict_types=1);

$page_title = 'Question Bank';
require_once __DIR__ . '/header.php';

$db = get_db();

// ── Categories & Stats ────────────────────────────────────────────────────────
$stats = $db->query("SELECT
                        COUNT(*) as total,
                        SUM(is_active=1) as active,
                        SUM(question_type='rating') as rating_count,
                        SUM(question_type='mcq') as mcq_count
                     FROM question_bank")->fetch_assoc();

$catRes = $db->query("SELECT category, COUNT(*) as c FROM question_bank GROUP BY category ORDER BY category");
$categories = [];
while ($c = $catRes->fetch_assoc()) {
    if (trim($c['category'])) $categories[] = $c;
}

// ── Filters & Query ───────────────────────────────────────────────────────────
$search = sanitize_input($_GET['q'] ?? '');
$type   = sanitize_input($_GET['type'] ?? '');
$cat    = sanitize_input($_GET['cat'] ?? '');
$status = sanitize_input($_GET['status'] ?? '1'); // Default to active
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = 'q.question_text LIKE ?';
    $params[] = "%{$search}%";
    $types   .= 's';
}
if ($type !== '') {
    $where[] = 'q.question_type = ?';
    $params[] = $type;
    $types   .= 's';
}
if ($cat !== '') {
    $where[] = 'q.category = ?';
    $params[] = $cat;
    $types   .= 's';
}
if ($status !== '') {
    $where[] = 'q.is_active = ?';
    $params[] = (int)$status;
    $types   .= 'i';
}

$whereStr = implode(' AND ', $where);

// Total count
$cSql = "SELECT COUNT(*) FROM question_bank q WHERE {$whereStr}";
if ($params) {
    $cStmt = $db->prepare($cSql);
    $cStmt->bind_param($types, ...$params);
    $cStmt->execute();
    $total = (int)$cStmt->get_result()->fetch_row()[0];
    $cStmt->close();
} else {
    $total = (int)$db->query($cSql)->fetch_row()[0];
}
$pg = paginate($total, $limit, $page);

// Fetch questions + usage count
$sql = "SELECT q.*, u.full_name as author_name,
               (SELECT COUNT(DISTINCT questionnaire_id) FROM questionnaire_questions qq WHERE qq.question_id = q.question_id) as usage_count
        FROM question_bank q
        JOIN users u ON u.user_id = q.created_by
        WHERE {$whereStr}
        ORDER BY q.category ASC, q.created_at DESC
        LIMIT ? OFFSET ?";

$fParams = $params;
$fParams[] = $limit;
$fParams[] = $pg['offset'];
$fTypes  = $types . 'ii';

$stmt = $db->prepare($sql);
if ($fParams) {
    $stmt->bind_param($fTypes, ...$fParams);
}
$stmt->execute();
$questions = $stmt->get_result();
$stmt->close();
?>

<!-- Add Question Modal (Hidden by default) -->
<div id="add-modal" class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform" id="add-modal-panel">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 dark:text-white" id="modal-title">Add to Question Bank</h3>
            <button onclick="toggleModal(false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="add-q-form" class="px-6 py-5 space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="question_id" id="edit-id" value="">

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Question Text <span class="text-red-500">*</span></label>
                <textarea name="question_text" id="inp-text" rows="2" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 resize-none" required></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Category</label>
                    <input type="text" name="category" id="inp-cat" list="cat-list" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200" placeholder="e.g. Course Delivery">
                    <datalist id="cat-list">
                        <?php foreach ($categories as $c): ?><option value="<?= h($c['category']) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Response Type</label>
                    <select name="question_type" id="inp-type" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <option value="rating">1-5 Star Rating</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="toggleModal(false)" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors shadow-sm">Save Question</button>
            </div>
        </form>
    </div>
</div>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Question Bank</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5"><?= $stats['active'] ?? 0 ?> active questions available for questionnaires</p>
    </div>
    <button onclick="openAddModal()" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        New Question
    </button>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700">
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $stats['total'] ?? 0 ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Total Questions</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700">
        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $stats['active'] ?? 0 ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Active</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700">
        <p class="text-2xl font-bold text-amber-500 dark:text-amber-400"><?= $stats['rating_count'] ?? 0 ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Rating Type</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700">
        <p class="text-2xl font-bold text-brand-600 dark:text-brand-400"><?= count($categories) ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Categories</p>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" class="flex flex-col sm:flex-row gap-3 mb-5">
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search questions…"
               class="form-input w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200">
    </div>
    <select name="cat" class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 max-w-[200px]">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= h($c['category']) ?>" <?= $cat === $c['category'] ? 'selected' : '' ?>><?= h($c['category']) ?> (<?= $c['c'] ?>)</option>
        <?php endforeach; ?>
    </select>
    <select name="type" class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 max-w-[150px]">
        <option value="">All Types</option>
        <option value="rating" <?= $type === 'rating' ? 'selected' : '' ?>>Rating</option>
        <option value="mcq" <?= $type === 'mcq' ? 'selected' : '' ?>>MCQ</option>
    </select>
    <select name="status" class="form-input px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm dark:text-slate-200 max-w-[150px]">
        <option value="">All Status</option>
        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active Only</option>
        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive Only</option>
    </select>
    <button type="submit" class="bg-gray-800 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-700 transition-colors">Filter</button>
    <?php if ($search || $cat || $type || $status !== '1'): ?>
    <a href="question_bank.php" class="flex items-center gap-1 text-red-500 hover:text-red-600 text-sm px-2">Clear</a>
    <?php endif; ?>
</form>

<!-- Question Table -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden mb-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">
                    <th class="px-5 py-3">Question</th>
                    <th class="px-4 py-3 w-32">Type</th>
                    <th class="px-4 py-3 w-40">Category</th>
                    <th class="px-4 py-3 text-center w-24">Usage</th>
                    <th class="px-4 py-3 text-center w-24">Status</th>
                    <th class="px-5 py-3 text-right w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $hasRows = false;
                while ($row = $questions->fetch_assoc()):
                    $hasRows = true;
                    $qid = (int)$row['question_id'];
                    $qData = htmlspecialchars(json_encode([
                        'id' => $qid, 'text' => $row['question_text'], 'cat' => $row['category'], 'type' => $row['question_type']
                    ]), ENT_QUOTES, 'UTF-8');
                ?>
                <tr id="q-row-<?= $qid ?>" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-gray-800 dark:text-slate-200 leading-snug"><?= h($row['question_text']) ?></p>
                        <p class="text-[10px] text-gray-400 dark:text-slate-500 mt-1">Added by <?= h($row['author_name']) ?> on <?= date('M j, Y', strtotime($row['created_at'])) ?></p>
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="inline-flex items-center gap-1 text-xs font-medium <?= $row['question_type'] === 'rating' ? 'text-amber-600' : 'text-blue-600' ?>">
                            <?= $row['question_type'] === 'rating' ? '★ Rating (1-5)' : '☰ MCQ' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-600 dark:text-slate-400"><?= h($row['category'] ?: '—') ?></td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($row['usage_count'] > 0): ?>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-300 rounded text-xs font-bold"><?= $row['usage_count'] ?></span>
                        <?php else: ?>
                        <span class="text-gray-300 dark:text-slate-600 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <button onclick="toggleActive(<?= $qid ?>, <?= $row['is_active'] ? 0 : 1 ?>)" class="focus:outline-none" title="Click to toggle status">
                            <?php if ($row['is_active']): ?>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                            </span>
                            <?php endif; ?>
                        </button>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditModal(<?= $qData ?>)" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <?php if ($row['usage_count'] == 0): ?>
                            <button onclick="confirmAction('Delete this question permanently?', () => deleteQuestion(<?= $qid ?>))" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <?php else: ?>
                            <button onclick="showToast('Cannot delete question because it is used in <?= $row['usage_count'] ?> questionnaire(s). Deactivate it instead.', 'warning')" class="p-1.5 rounded-lg text-gray-300 dark:text-slate-600 cursor-not-allowed" title="In Use">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if (!$hasRows): ?>
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center">
                        <p class="text-gray-500 font-medium">No questions found</p>
                        <p class="text-sm text-gray-400 mt-1">Try adjusting filters or add a new question.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pg['total_pages'] > 1): ?>
<div class="flex justify-center">
    <?= render_pagination($pg, http_build_query(array_filter(['q' => $search, 'cat' => $cat, 'type' => $type, 'status' => $status]))) ?>
</div>
<?php endif; ?>

<script>
const modal = document.getElementById('add-modal');
const panel = document.getElementById('add-modal-panel');

function toggleModal(show) {
    if (show) {
        modal.classList.remove('hidden');
        // Trigger reflow
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
    document.getElementById('add-q-form').reset();
    document.getElementById('edit-id').value = '';
    document.getElementById('modal-title').textContent = 'Add to Question Bank';
    toggleModal(true);
}

function openEditModal(data) {
    document.getElementById('edit-id').value = data.id;
    document.getElementById('inp-text').value = data.text;
    document.getElementById('inp-cat').value = data.cat;
    document.getElementById('inp-type').value = data.type;
    document.getElementById('modal-title').textContent = 'Edit Question';
    toggleModal(true);
}

document.getElementById('add-q-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        action: 'save',
        question_id: document.getElementById('edit-id').value,
        question_text: document.getElementById('inp-text').value,
        category: document.getElementById('inp-cat').value,
        question_type: document.getElementById('inp-type').value,
        csrf_token: '<?= h(get_csrf_token()) ?>'
    };
    afmsPost('question_actions.php', data, res => {
        showToast(res.message, 'success');
        toggleModal(false);
        setTimeout(() => location.reload(), 800);
    });
});

function toggleActive(id, newState) {
    afmsPost('question_actions.php', { action: 'toggle_active', id, state: newState, csrf_token: '<?= h(get_csrf_token()) ?>' }, res => {
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    });
}

function deleteQuestion(id) {
    afmsPost('question_actions.php', { action: 'delete', id, csrf_token: '<?= h(get_csrf_token()) ?>' }, res => {
        showToast(res.message, 'success');
        document.getElementById('q-row-' + id)?.remove();
    });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
