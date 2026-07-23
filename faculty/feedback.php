<?php
/**
 * Faculty Feedback List — AFMS
 */
declare(strict_types=1);

$page_title = 'Available Forms';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Get pending feedback sessions
$sessions = [];
try {
    $now_date = date('Y-m-d');
    $now_time = date('H:i:s');
    $stmt = $db->prepare("
        SELECT fs.*, q.questionnaire_id, q.title as form_title, q.description as form_desc
        FROM feedback_sessions fs
        JOIN session_target_roles str ON fs.session_id = str.session_id
        JOIN questionnaires q ON fs.session_id = q.session_id
        WHERE fs.status = 'Active' 
          AND q.status = 'Active'
          AND str.target_role IN ('Faculty', 'All')
          AND NOT EXISTS (
              SELECT 1 FROM submission_tokens 
              WHERE user_id = ? AND questionnaire_id = q.questionnaire_id AND session_id = fs.session_id
          )
        ORDER BY fs.end_date ASC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    $stmt->close();
} catch (Throwable) {}

?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Pending Feedback Forms</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Please complete the following feedback questionnaires.</p>
        </div>
    </div>

    <?php if (empty($sessions)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-gray-100 dark:border-slate-700 shadow-sm">
        <div class="w-20 h-20 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">All caught up!</h3>
        <p class="text-gray-500 dark:text-slate-400 max-w-md mx-auto">You have no pending feedback forms at the moment. Check back later when new sessions are published.</p>
    </div>
    <?php else: ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <?php foreach ($sessions as $s): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col hover:border-brand-300 dark:hover:border-brand-700 transition-colors">
            <div class="p-5 flex-1">
                <div class="flex items-start justify-between mb-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 ring-1 ring-red-200">
                        Due: <?= date('d M Y', strtotime($s['end_date'])) ?>
                    </span>
                    <span class="text-xs font-medium text-gray-500">
                        <?= h($s['academic_year'] . ' • Sem ' . $s['semester']) ?>
                    </span>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1"><?= h($s['form_title']) ?></h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 line-clamp-2 mb-4">
                    <?= h($s['form_desc'] ?: $s['session_title']) ?>
                </p>
            </div>
            
            <div class="p-4 bg-gray-50 dark:bg-slate-900/50 border-t border-gray-100 dark:border-slate-700 flex justify-end">
                <!-- We would redirect to a form submission page here -->
                <a href="form_submit.php?id=<?= $s['questionnaire_id'] ?>&session_id=<?= $s['session_id'] ?>" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm inline-flex items-center gap-2">
                    Start Survey
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
