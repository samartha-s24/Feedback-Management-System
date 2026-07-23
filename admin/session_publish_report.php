<?php
/**
 * Admin — Publish Session Report
 */
declare(strict_types=1);

$page_title = 'Publish Session Report';
require_once __DIR__ . '/header.php';

$db = get_db();
$session_id = (int)($_GET['session'] ?? 0);

if (!$session_id) {
    header('Location: sessions.php');
    exit;
}

// Fetch session info
$stmt = $db->prepare("SELECT * FROM feedback_sessions WHERE session_id = ?");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    header('Location: sessions.php');
    exit;
}

// Fetch departments
$departments = [];
$res = $db->query("SELECT * FROM departments ORDER BY department_name");
while ($row = $res->fetch_assoc()) {
    $departments[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $report_title = sanitize_input($_POST['report_title'] ?? '');
    $selected_depts = $_POST['departments'] ?? [];
    
    if (empty($report_title)) {
        $error = "Report title is required.";
    } elseif (empty($selected_depts)) {
        $error = "Please select at least one department.";
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO reports 
                (report_title, report_type, department_id, session_id, date_from, date_to, generated_by, generated_at) 
                VALUES (?, 'Department-wise', ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                report_title = VALUES(report_title),
                date_from = VALUES(date_from),
                date_to = VALUES(date_to),
                generated_by = VALUES(generated_by),
                generated_at = NOW()
            ");
            
            $user_id = (int) $_SESSION['user_id'];
            $date_from = $session['start_date'] ?: date('Y-m-d');
            $date_to = $session['end_date'] ?: date('Y-m-d');
            
            foreach ($selected_depts as $dept_id) {
                $d_id = (int) $dept_id;
                $stmt->bind_param('siissi', $report_title, $d_id, $session_id, $date_from, $date_to, $user_id);
                $stmt->execute();
            }
            $stmt->close();
            
            $db->commit();
            
            if (function_exists('log_audit')) {
                log_audit("Published report for session ID {$session_id} to " . count($selected_depts) . " departments.", 'info');
            }
            
            $success_message = "🎉 Woohoo! Reports published successfully! Faculty can now see them!";
        } catch (Exception $e) {
            $db->rollback();
            $error = "Failed to publish reports: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Publish Report</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                Session: <span class="font-semibold text-gray-800 dark:text-slate-300"><?= h($session['session_title']) ?></span>
            </p>
        </div>
        <a href="sessions.php" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-300 transition-colors">
            &larr; Back to Sessions
        </a>
    </div>

    <?php if (!empty($error)): ?>
    <div class="mb-6 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl border border-red-200 dark:border-red-800 text-sm flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= h($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-8 sm:p-12 border border-emerald-100 dark:border-emerald-900/50 shadow-sm text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 mb-6">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2"><?= h($success_message) ?></h3>
        <p class="text-gray-500 dark:text-slate-400 mb-8 max-w-lg mx-auto">The report has been successfully generated and published. The selected departments can now view the interactive analytics.</p>
        <a href="sessions.php" class="inline-flex items-center justify-center gap-2 bg-gray-900 hover:bg-gray-800 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold px-6 py-3 rounded-xl transition-colors shadow-sm">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Return to Sessions
        </a>
    </div>
    <?php else: ?>
    <form method="POST" class="bg-white dark:bg-slate-800 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-slate-700 shadow-sm">
        <?= csrf_field() ?>
        
        <div class="space-y-6">
            <!-- Report Title -->
            <div>
                <label for="report_title" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Report Title</label>
                <input type="text" id="report_title" name="report_title" 
                       value="<?= h($_POST['report_title'] ?? $session['session_title'] . ' - Final Report') ?>" 
                       class="form-input w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200 focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
            </div>

            <!-- Department Selection -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3">Target Departments</label>
                <p class="text-xs text-gray-500 dark:text-slate-400 mb-4">Select which departments should receive this report. Faculty in these departments will be able to view the interactive performance charts for this session.</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto p-2 bg-gray-50 dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-700">
                    <?php foreach ($departments as $dept): ?>
                    <label class="flex items-start gap-3 p-3 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 cursor-pointer hover:border-brand-300 dark:hover:border-brand-700 transition-colors">
                        <div class="flex items-center h-5">
                            <input type="checkbox" name="departments[]" value="<?= $dept['department_id'] ?>" class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500"
                                <?= (in_array($dept['department_id'], $_POST['departments'] ?? [])) ? 'checked' : '' ?>>
                        </div>
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-white"><?= h($dept['department_name']) ?></span>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5"><?= h($dept['department_code']) ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="pt-4 border-t border-gray-100 dark:border-slate-700">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Publish Report
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
