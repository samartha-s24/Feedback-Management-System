<?php
/**
 * AFMS Admin — Create / Edit Feedback Session
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

$db = get_db();
$isEdit  = false;
$session = null;
$targets = [];
$errors  = [];
$success = '';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM feedback_sessions WHERE session_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$session) { header('Location: sessions.php'); exit; }
    $isEdit = true;

    // Fetch current target roles
    $rStmt = $db->prepare("SELECT target_role FROM session_target_roles WHERE session_id = ?");
    $rStmt->bind_param('i', $id);
    $rStmt->execute();
    $tr = $rStmt->get_result();
    while ($r = $tr->fetch_row()) $targets[] = $r[0];
    $rStmt->close();
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $f = [
        'session_title'     => sanitize_input($_POST['session_title'] ?? ''),
        'academic_year'     => sanitize_input($_POST['academic_year'] ?? ''),
        'semester'          => (int)($_POST['semester'] ?? 0),
        'start_date'        => sanitize_input($_POST['start_date'] ?? ''),
        'start_time'        => sanitize_input($_POST['start_time'] ?? ''),
        'end_date'          => sanitize_input($_POST['end_date'] ?? ''),
        'end_time'          => sanitize_input($_POST['end_time'] ?? ''),
        'status'            => sanitize_input($_POST['status'] ?? 'Draft'),
        'instructions'      => trim($_POST['instructions'] ?? ''),
        'max_attempts'      => max(1, (int)($_POST['max_attempts'] ?? 1)),
        'result_visibility' => sanitize_input($_POST['result_visibility'] ?? 'Private'),
        'feedback_type'     => sanitize_input($_POST['feedback_type'] ?? 'Student'),
    ];
    $selectedRoles = $_POST['target_roles'] ?? [];
    if (is_string($selectedRoles)) $selectedRoles = [$selectedRoles];

    // Validation
    if (empty($f['session_title'])) $errors[] = 'Session name is required.';
    if (empty($f['start_date'])) $errors[] = 'Start date is required.';
    if (empty($f['end_date'])) $errors[] = 'End date is required.';
    if (!empty($f['start_date']) && !empty($f['end_date']) && $f['end_date'] < $f['start_date']) {
        $errors[] = 'End date must be after start date.';
    }
    if (empty($selectedRoles)) $errors[] = 'Select at least one target audience.';
    $allowedStatuses = ['Draft','Published','Active','Closed','Archived'];
    if (!in_array($f['status'], $allowedStatuses)) $f['status'] = 'Draft';

    if (empty($errors)) {
        $isActive = ($f['status'] === 'Active') ? 1 : 0;

        if ($isEdit) {
            $stmt = $db->prepare(
                "UPDATE feedback_sessions SET
                    session_title=?, feedback_type=?, status=?, academic_year=?, semester=?,
                    start_date=?, start_time=?, end_date=?, end_time=?,
                    instructions=?, max_attempts=?, result_visibility=?, is_active=?
                 WHERE session_id=?"
            );
            $sem = $f['semester'] ?: null;
            $sDate = $f['start_date'] ?: null;
            $sTime = $f['start_time'] ?: null;
            $eDate = $f['end_date'] ?: null;
            $eTime = $f['end_time'] ?: null;
            $stmt->bind_param('ssssisssssisii',
                $f['session_title'], $f['feedback_type'], $f['status'],
                $f['academic_year'], $sem,
                $sDate, $sTime,
                $eDate, $eTime,
                $f['instructions'], $f['max_attempts'], $f['result_visibility'],
                $isActive, $id
            );
            $stmt->execute(); $stmt->close();

            // Sync target roles
            $delStmt = $db->prepare("DELETE FROM session_target_roles WHERE session_id=?");
            $delStmt->bind_param('i', $id);
            $delStmt->execute();
            $delStmt->close();

            log_audit('Updated Feedback Session', 'feedback_sessions', $id, $f['session_title']);
        } else {
            $userId = (int)$_SESSION['user_id'];
            $stmt   = $db->prepare(
                "INSERT INTO feedback_sessions
                    (session_title, feedback_type, status, academic_year, semester,
                     start_date, start_time, end_date, end_time,
                     instructions, max_attempts, result_visibility, is_active, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $sem = $f['semester'] ?: null;
            $sDate = $f['start_date'] ?: null;
            $sTime = $f['start_time'] ?: null;
            $eDate = $f['end_date'] ?: null;
            $eTime = $f['end_time'] ?: null;
            $stmt->bind_param('ssssisssssisii',
                $f['session_title'], $f['feedback_type'], $f['status'],
                $f['academic_year'], $sem,
                $sDate, $sTime,
                $eDate, $eTime,
                $f['instructions'], $f['max_attempts'], $f['result_visibility'],
                $isActive, $userId
            );
            $stmt->execute();
            $id = (int)$db->insert_id;
            $stmt->close();

            log_audit('Created Feedback Session', 'feedback_sessions', $id, $f['session_title']);
            $isEdit = true;
        }

        // Insert target roles
        $roleStmt = $db->prepare("INSERT IGNORE INTO session_target_roles (session_id, target_role) VALUES (?,?)");
        foreach ($selectedRoles as $role) {
            $allowed = ['Student','Faculty','Parent','Alumni','Employee'];
            if (in_array($role, $allowed)) {
                $roleStmt->bind_param('is', $id, $role);
                $roleStmt->execute();
            }
        }
        $roleStmt->close();

        $_SESSION['flash'] = ['type' => 'success', 'message' => $isEdit ? 'Session updated successfully.' : 'Session created successfully.'];
        header("Location: sessions.php");
        exit;
    }
}

// Pre-fill from DB record
$f = $f ?? [];
if ($session) {
    $f += $session;
    $f['session_title']     = $session['session_title'];
    $f['academic_year']     = $session['academic_year'] ?? '';
    $f['semester']          = $session['semester'] ?? '';
    $f['start_date']        = $session['start_date'] ?? '';
    $f['start_time']        = $session['start_time'] ?? '';
    $f['end_date']          = $session['end_date'] ?? '';
    $f['end_time']          = $session['end_time'] ?? '';
    $f['status']            = $session['status'] ?? 'Draft';
    $f['instructions']      = $session['instructions'] ?? '';
    $f['max_attempts']      = $session['max_attempts'] ?? 1;
    $f['result_visibility'] = $session['result_visibility'] ?? 'Private';
    $f['feedback_type']     = $session['feedback_type'] ?? 'Student';
}
$selectedRoles = $selectedRoles ?? $targets;

$page_title = $isEdit ? 'Edit Session' : 'New Feedback Session';
require_once __DIR__ . '/header.php';
?>

<div class="max-w-3xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 mb-5">
        <a href="sessions.php" class="hover:text-brand-600 dark:hover:text-brand-400">Sessions</a>
        <span>/</span>
        <span class="text-gray-700 dark:text-slate-200 font-medium"><?= $isEdit ? 'Edit Session' : 'New Session' ?></span>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <!-- Card Header -->
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-brand-600 to-brand-800">
            <h2 class="text-lg font-semibold text-white"><?= $isEdit ? 'Edit Feedback Session' : 'Create New Feedback Session' ?></h2>
            <p class="text-brand-200 text-xs mt-0.5">Configure session details, schedule, and target audience</p>
        </div>

        <!-- Validation Errors -->
        <?php if ($errors): ?>
        <div class="mx-6 mt-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" class="px-6 py-5 space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <!-- Session Name -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Session Name <span class="text-red-500">*</span></label>
                <input type="text" name="session_title" value="<?= h($f['session_title'] ?? '') ?>"
                       class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200"
                       placeholder="e.g. Faculty Feedback 2025 – Semester I" required>
            </div>

            <!-- Academic Year + Semester -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Academic Year</label>
                    <input type="text" name="academic_year" value="<?= h($f['academic_year'] ?? '') ?>"
                           class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200"
                           placeholder="2025-2026" pattern="\d{4}-\d{4}">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Semester</label>
                    <select name="semester" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <option value="">— Select —</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= ($f['semester'] ?? '') == $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Schedule: Start Date/Time + End Date/Time -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Start Date <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" value="<?= h($f['start_date'] ?? '') ?>" required
                           class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    <input type="time" name="start_time" value="<?= h(substr($f['start_time'] ?? '', 0, 5)) ?>"
                           class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 mt-2">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" value="<?= h($f['end_date'] ?? '') ?>" required
                           class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    <input type="time" name="end_time" value="<?= h(substr($f['end_time'] ?? '', 0, 5)) ?>"
                           class="form-input w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 mt-2">
                </div>
            </div>

            <!-- Target Audience -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Target Audience <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['Student','Faculty','Parent','Alumni','Employee'] as $role): ?>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="target_roles[]" value="<?= $role ?>"
                               <?= in_array($role, $selectedRoles) ? 'checked' : '' ?>
                               class="w-4 h-4 accent-brand-600 rounded">
                        <span class="text-sm text-gray-700 dark:text-slate-300"><?= $role ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status + Visibility -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Status</label>
                    <select name="status" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <?php foreach (['Draft','Published','Active','Closed','Archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($f['status'] ?? 'Draft') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Result Visibility</label>
                    <select name="result_visibility" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <?php foreach (['Private','Published','Archived'] as $v): ?>
                        <option value="<?= $v ?>" <?= ($f['result_visibility'] ?? 'Private') === $v ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Max Attempts + Feedback Type -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Max Attempts</label>
                    <input type="number" name="max_attempts" value="<?= (int)($f['max_attempts'] ?? 1) ?>" min="1" max="5"
                           class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Feedback Type</label>
                    <select name="feedback_type" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <?php foreach (['Student','Faculty','Alumni','Parent','Employee','Course','Subject','Infrastructure','Academic'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($f['feedback_type'] ?? 'Student') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Instructions -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Instructions <span class="text-gray-400 font-normal">(shown to respondents)</span></label>
                <textarea name="instructions" rows="3"
                          class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 resize-none"
                          placeholder="Please rate each item honestly on a scale of 1 (Poor) to 5 (Excellent)."><?= h($f['instructions'] ?? '') ?></textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-slate-700">
                <a href="sessions.php" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">← Cancel</a>
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition-colors shadow-sm">
                    <?= $isEdit ? 'Update Session' : 'Create Session' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
