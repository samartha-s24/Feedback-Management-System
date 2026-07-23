<?php
/**
 * Faculty Feedback History — AFMS
 */
declare(strict_types=1);

$page_title = 'Submission History';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Get submission history
$history = [];
try {
    $stmt = $db->prepare("
        SELECT st.submitted_at, fs.session_title, fs.academic_year, fs.semester, q.title as form_title
        FROM submission_tokens st
        JOIN feedback_sessions fs ON st.session_id = fs.session_id
        LEFT JOIN questionnaires q ON fs.session_id = q.session_id
        WHERE st.user_id = ?
        ORDER BY st.submitted_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
} catch (Throwable) {}

?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Submission History</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Record of all your completed feedback forms.</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <?php if (empty($history)): ?>
        <div class="p-10 text-center">
            <div class="w-16 h-16 bg-gray-50 dark:bg-slate-700 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-gray-500 font-medium">No past submissions found.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700">
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Date Submitted</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Session</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Questionnaire</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Academic Term</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($history as $h): ?>
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-750 transition-colors group">
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-medium whitespace-nowrap">
                            <?= date('d M Y, h:i A', strtotime($h['submitted_at'])) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-slate-300">
                            <?= h($h['session_title']) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-slate-300">
                            <?= h($h['form_title'] ?: '—') ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400 whitespace-nowrap">
                            <?= h($h['academic_year'] . ' • Sem ' . $h['semester']) ?>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Completed
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 rounded-xl text-sm flex gap-3 border border-blue-100 dark:border-blue-900/50">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p><strong>Privacy Note:</strong> Your feedback is stored anonymously. This history only indicates <em>that</em> you submitted the form, not <em>what</em> you submitted. Your responses cannot be traced back to your faculty ID.</p>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
