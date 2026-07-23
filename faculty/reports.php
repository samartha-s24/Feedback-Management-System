<?php
/**
 * Faculty Published Reports — AFMS
 */
declare(strict_types=1);

$page_title = 'Published Reports';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Get faculty department and subjects
$department_id = null;
$subject_ids = [];
try {
    $stmt = $db->prepare("SELECT department_id, faculty_id FROM faculty WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $department_id = $row['department_id'];
        
        $stmt_sub = $db->prepare("SELECT subject_id FROM faculty_subject_assignments WHERE faculty_id = ?");
        $stmt_sub->bind_param('i', $row['faculty_id']);
        $stmt_sub->execute();
        $res_sub = $stmt_sub->get_result();
        while ($sub = $res_sub->fetch_assoc()) {
            $subject_ids[] = $sub['subject_id'];
        }
        $stmt_sub->close();
    }
    $stmt->close();
} catch (Throwable) {}

// Fetch reports relevant to this faculty
$reports = [];
try {
    // If no department or subjects, we fetch none, or maybe just department
    if ($department_id) {
        $subject_in = empty($subject_ids) ? "0" : implode(',', $subject_ids);
        
        $sql = "
            SELECT r.*, d.department_name, s.subject_name
            FROM reports r
            LEFT JOIN departments d ON r.department_id = d.department_id
            LEFT JOIN subjects s ON r.subject_id = s.subject_id
            WHERE r.department_id = ? OR r.subject_id IN ($subject_in) OR r.report_type = 'Consolidated'
            ORDER BY r.generated_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();
    }
} catch (Throwable) {}

?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Published Reports</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">View performance analytics and anonymous feedback for your courses and department.</p>
    </div>

    <?php if (empty($reports)): ?>
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-gray-100 dark:border-slate-700 shadow-sm">
            <div class="w-20 h-20 bg-blue-50 dark:bg-blue-900/30 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No Reports Available</h3>
            <p class="text-gray-500 dark:text-slate-400 max-w-md mx-auto">There are no published reports for your department or subjects at this time.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($reports as $r): ?>
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col hover:-translate-y-1 hover:border-brand-300 transition-all duration-300">
                <div class="flex items-start justify-between mb-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300 border border-brand-100 dark:border-brand-800">
                        <?= h($r['report_type']) ?>
                    </span>
                    <span class="text-xs font-medium text-gray-500">
                        <?= date('d M Y', strtotime($r['generated_at'])) ?>
                    </span>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1"><?= h($r['report_title']) ?></h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">
                    <?= h($r['department_name'] ?? 'All Departments') ?> 
                    <?= $r['subject_name'] ? ' • ' . h($r['subject_name']) : '' ?>
                </p>
                
                <div class="mt-auto pt-4 flex gap-3 border-t border-gray-100 dark:border-slate-700">
                    <a href="report_view.php?id=<?= $r['report_id'] ?>" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors text-center inline-flex justify-center items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View Interactive Report
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
