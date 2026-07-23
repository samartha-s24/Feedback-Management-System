<?php
/**
 * AFMS Admin — Form Builder (Create / Edit Questionnaire)
 * Supports drag-and-drop reordering, question bank picker, inline add.
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

$db    = get_db();
$isEdit = false;
$form   = null;
$linked = [];
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM questionnaires WHERE questionnaire_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$form) { header('Location: forms.php'); exit; }
    $isEdit = true;

    // Current questions on this questionnaire
    $qStmt = $db->prepare("SELECT qq.qq_id, qq.display_order, qq.is_required,
                                   qb.question_id, qb.question_text, qb.question_type, qb.category
                            FROM questionnaire_questions qq
                            JOIN question_bank qb ON qb.question_id = qq.question_id
                            WHERE qq.questionnaire_id = ?
                            ORDER BY qq.display_order");
    $qStmt->bind_param('i', $id);
    $qStmt->execute();
    $res = $qStmt->get_result();
    if ($res) {
        while ($q = $res->fetch_assoc()) {
            $linked[] = $q;
        }
    }
    $qStmt->close();
}

// Fetch active sessions for dropdown
$sessions = $db->query("SELECT session_id, session_title, status FROM feedback_sessions WHERE status IN ('Draft','Published','Active') ORDER BY created_at DESC");

// Fetch all active questions from bank
$bankQuestions = [];
$bq = $db->query("SELECT question_id, question_text, question_type, category FROM question_bank WHERE is_active=1 ORDER BY category, question_id");
while ($r = $bq->fetch_assoc()) $bankQuestions[] = $r;

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title       = sanitize_input($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $sessionId   = (int)($_POST['session_id'] ?? 0);
    $qStatus     = sanitize_input($_POST['status'] ?? 'Draft');
    $questionIds = $_POST['question_ids'] ?? [];
    $required    = $_POST['required'] ?? [];

    if (empty($title))      $errors[] = 'Title is required.';
    if ($sessionId <= 0)    $errors[] = 'Please link this questionnaire to a session.';
    if (count($questionIds) < 1) $errors[] = 'Add at least 1 question.';
    if (count($questionIds) > 10) $errors[] = 'Maximum 10 questions allowed.';

    if (empty($errors)) {
        $userId = (int)$_SESSION['user_id'];
        if ($isEdit) {
            $stmt = $db->prepare("UPDATE questionnaires SET title=?, description=?, session_id=?, status=? WHERE questionnaire_id=?");
            $stmt->bind_param('ssisi', $title, $desc, $sessionId, $qStatus, $id);
            $stmt->execute(); $stmt->close();
            
            $delStmt = $db->prepare("DELETE FROM questionnaire_questions WHERE questionnaire_id=?");
            $delStmt->bind_param('i', $id);
            $delStmt->execute(); $delStmt->close();
            
            log_audit('Updated Questionnaire', 'questionnaires', $id, $title);
        } else {
            $stmt = $db->prepare("INSERT INTO questionnaires (title, description, session_id, status, created_by) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssisi', $title, $desc, $sessionId, $qStatus, $userId);
            $stmt->execute();
            $id = (int)$db->insert_id;
            $stmt->close();
            log_audit('Created Questionnaire', 'questionnaires', $id, $title);
        }

        // Insert questions with order
        $qqStmt = $db->prepare("INSERT INTO questionnaire_questions (questionnaire_id, question_id, display_order, is_required) VALUES (?,?,?,?)");
        foreach ($questionIds as $ord => $qId) {
            $qId    = (int)$qId;
            $ord    = (int)$ord + 1;
            $isReq  = in_array((string)$qId, (array)$required) ? 1 : 0;
            $qqStmt->bind_param('iiii', $id, $qId, $ord, $isReq);
            $qqStmt->execute();
        }
        $qqStmt->close();

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Questionnaire saved successfully.'];
        header('Location: forms.php');
        exit;
    }
}

$page_title = $isEdit ? 'Edit Questionnaire' : 'Build Questionnaire';
require_once __DIR__ . '/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 mb-5">
        <a href="forms.php" class="hover:text-brand-600 dark:hover:text-brand-400">Questionnaires</a>
        <span>/</span>
        <span class="text-gray-700 dark:text-slate-200 font-medium"><?= $isEdit ? 'Edit' : 'Build New' ?></span>
    </div>

    <?php if ($errors): ?>
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
        <ul class="list-disc list-inside space-y-0.5"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="builder-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Left: Metadata -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5 space-y-4">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm border-b border-gray-100 dark:border-slate-700 pb-3">Questionnaire Details</h3>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="<?= h($form['title'] ?? ($_POST['title'] ?? '')) ?>"
                               class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Description</label>
                        <textarea name="description" rows="3"
                                  class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 resize-none"><?= h($form['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Linked Session <span class="text-red-500">*</span></label>
                        <select name="session_id" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200" required>
                            <option value="">— Select Session —</option>
                            <?php
                            $selSid = (int)($form['session_id'] ?? ($_POST['session_id'] ?? ($_GET['session_id'] ?? 0)));
                            while ($s = $sessions->fetch_assoc()):
                            ?>
                            <option value="<?= $s['session_id'] ?>" <?= $selSid === (int)$s['session_id'] ? 'selected' : '' ?>>
                                <?= h($s['session_title']) ?> [<?= $s['status'] ?>]
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-slate-400 mb-1.5">Status</label>
                        <select name="status" class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                            <?php foreach (['Draft','Active','Closed'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($form['status'] ?? 'Draft') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Question Bank Picker -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Question Bank</h3>
                    <input type="text" id="bank-search" placeholder="Filter questions…"
                           class="form-input w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-xs dark:text-slate-200 mb-3">
                    <div id="bank-list" class="space-y-1.5 max-h-64 overflow-y-auto pr-1">
                        <?php foreach ($bankQuestions as $bq): ?>
                        <div class="bank-item flex items-center gap-2 p-2 rounded-lg hover:bg-brand-50 dark:hover:bg-brand-900/20 cursor-pointer transition-colors border border-transparent hover:border-brand-200 dark:hover:border-brand-800"
                             data-id="<?= $bq['question_id'] ?>"
                             data-text="<?= h($bq['question_text']) ?>"
                             data-type="<?= h($bq['question_type']) ?>"
                             onclick="addQuestionFromBank(this)">
                            <svg class="w-3.5 h-3.5 text-brand-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-xs text-gray-700 dark:text-slate-300 line-clamp-2"><?= h($bq['question_text']) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($bankQuestions)): ?>
                        <p class="text-xs text-gray-400 dark:text-slate-500 text-center py-4">No questions in bank. <a href="question_bank.php" class="text-brand-600 hover:underline">Add some →</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Question Builder -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Question List</h3>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">Drag to reorder · <span id="q-counter">0</span>/10 questions</p>
                        </div>
                    </div>

                    <div id="question-list" class="divide-y divide-gray-50 dark:divide-slate-700/50 min-h-24">
                        <!-- Populated by JS from bank or existing data -->
                    </div>

                    <!-- Empty state -->
                    <div id="empty-state" class="px-5 py-10 text-center hidden">
                        <svg class="w-10 h-10 text-gray-200 dark:text-slate-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm text-gray-400 dark:text-slate-500">No questions added yet. Pick from the bank or create one.</p>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between">
                        <a href="forms.php" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 transition-colors">← Cancel</a>
                        <button type="submit" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition-colors shadow-sm">
                            <?= $isEdit ? 'Save Changes' : 'Create Questionnaire' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Hidden template for question row -->
<template id="q-row-tpl">
    <div class="q-row flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors cursor-grab active:cursor-grabbing" draggable="true">
        <div class="mt-1 text-gray-300 dark:text-slate-600 flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/></svg>
        </div>
        <span class="q-order text-xs font-bold text-gray-400 dark:text-slate-500 mt-1.5 w-5 flex-shrink-0"></span>
        <div class="flex-1 min-w-0">
            <p class="q-text text-sm text-gray-800 dark:text-slate-200 font-medium"></p>
            <p class="q-type text-xs text-gray-400 dark:text-slate-500 mt-0.5"></p>
        </div>
        <label class="flex items-center gap-1.5 flex-shrink-0 text-xs text-gray-500 dark:text-slate-400 mt-1 cursor-pointer select-none">
            <input type="checkbox" class="q-required w-3.5 h-3.5 accent-brand-600" checked>
            Required
        </label>
        <button type="button" class="q-remove flex-shrink-0 mt-0.5 text-gray-300 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</template>

<script>
// Pre-populate existing questions (edit mode)
const existing = <?= json_encode($linked) ?>;
const MAX_Q = 10;
let questions = []; // {id, text, type, required}

existing.forEach(q => {
    questions.push({ id: q.question_id, text: q.question_text, type: q.question_type, required: q.is_required == 1 });
});

renderList();

// ── Bank Search ───────────────────────────────────────────────────────────────
document.getElementById('bank-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.bank-item').forEach(el => {
        el.style.display = el.dataset.text.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ── Add from bank ─────────────────────────────────────────────────────────────
function addQuestionFromBank(el) {
    if (questions.length >= MAX_Q) { showToast('Maximum 10 questions allowed.', 'warning'); return; }
    const id = parseInt(el.dataset.id);
    if (questions.find(q => q.id === id)) { showToast('Question already added.', 'info'); return; }
    questions.push({ id, text: el.dataset.text, type: el.dataset.type, required: true });
    renderList();
    showToast('Question added.', 'success');
}

// ── Render ─────────────────────────────────────────────────────────────────────
function renderList() {
    const list  = document.getElementById('question-list');
    const empty = document.getElementById('empty-state');
    const counter = document.getElementById('q-counter');
    list.innerHTML = '';
    counter.textContent = questions.length;
    if (questions.length === 0) { empty.classList.remove('hidden'); return; }
    empty.classList.add('hidden');
    const tpl = document.getElementById('q-row-tpl');
    questions.forEach((q, i) => {
        const clone = tpl.content.cloneNode(true);
        const row   = clone.querySelector('.q-row');
        row.dataset.idx = i;
        clone.querySelector('.q-order').textContent = i + 1 + '.';
        clone.querySelector('.q-text').textContent  = q.text;
        clone.querySelector('.q-type').textContent  = q.type === 'rating' ? '★ Rating (1–5)' : '☰ Multiple Choice';
        const reqCb = clone.querySelector('.q-required');
        reqCb.checked = q.required;
        reqCb.addEventListener('change', () => { questions[i].required = reqCb.checked; });
        clone.querySelector('.q-remove').addEventListener('click', () => { questions.splice(i, 1); renderList(); });
        list.appendChild(clone);
    });
    attachDragDrop();
    syncHiddenInputs();
}

// ── Drag-and-Drop reorder ─────────────────────────────────────────────────────
let dragSrc = null;
function attachDragDrop() {
    document.querySelectorAll('.q-row').forEach(row => {
        row.addEventListener('dragstart', e => { dragSrc = parseInt(row.dataset.idx); e.dataTransfer.effectAllowed = 'move'; });
        row.addEventListener('dragover',  e => { e.preventDefault(); row.classList.add('bg-brand-50','dark:bg-brand-900/20'); });
        row.addEventListener('dragleave', () => row.classList.remove('bg-brand-50','dark:bg-brand-900/20'));
        row.addEventListener('drop', e => {
            e.preventDefault(); row.classList.remove('bg-brand-50','dark:bg-brand-900/20');
            const destIdx = parseInt(row.dataset.idx);
            if (dragSrc !== null && dragSrc !== destIdx) {
                const moved = questions.splice(dragSrc, 1)[0];
                questions.splice(destIdx, 0, moved);
                renderList();
            }
        });
    });
}

// ── Sync hidden inputs for form submission ────────────────────────────────────
function syncHiddenInputs() {
    document.querySelectorAll('input[name="question_ids[]"], input[name="required[]"]').forEach(el => el.remove());
    const form = document.getElementById('builder-form');
    questions.forEach(q => {
        const hidId  = document.createElement('input');
        hidId.type   = 'hidden'; hidId.name = 'question_ids[]'; hidId.value = q.id;
        form.appendChild(hidId);
        if (q.required) {
            const hidReq = document.createElement('input');
            hidReq.type = 'hidden'; hidReq.name = 'required[]'; hidReq.value = q.id;
            form.appendChild(hidReq);
        }
    });
}

// Sync before submit
document.getElementById('builder-form').addEventListener('submit', syncHiddenInputs);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
