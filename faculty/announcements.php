<?php
/**
 * Faculty Announcements — AFMS
 */
declare(strict_types=1);

$page_title = 'Announcements';
require_once __DIR__ . '/header.php';

$db = get_db();

$announcements = [];
try {
    $now_date = date('Y-m-d');
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("
        SELECT a.announcement_id, a.title, a.description, a.priority, a.created_at, a.linked_session_id,
               (SELECT q.questionnaire_id 
                FROM questionnaires q 
                WHERE q.session_id = a.linked_session_id AND q.status = 'Active' 
                  AND NOT EXISTS (SELECT 1 FROM submission_tokens st WHERE st.user_id = ? AND st.questionnaire_id = q.questionnaire_id)
                LIMIT 1) as pending_q_id
        FROM announcements a
        JOIN announcement_audience aa ON a.announcement_id = aa.announcement_id
        WHERE a.status = 'Published'
          AND (a.start_date IS NULL OR a.start_date <= ?)
          AND (a.end_date IS NULL OR a.end_date >= ?)
          AND aa.target_role IN ('Faculty', 'All')
        ORDER BY 
          CASE a.priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END,
          a.created_at DESC
    ");
    $stmt->bind_param('iss', $user_id, $now_date, $now_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
} catch (Throwable) {}

?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Announcements</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Latest news and updates from the institution.</p>
    </div>

    <?php if (empty($announcements)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center border border-gray-100 dark:border-slate-700 shadow-sm">
        <div class="w-20 h-20 bg-blue-50 dark:bg-blue-900/30 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No new announcements</h3>
        <p class="text-gray-500 dark:text-slate-400">You're all caught up on the latest news.</p>
    </div>
    <?php else: ?>
    
    <div class="space-y-4">
        <?php foreach ($announcements as $a): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
            
            <!-- Priority Indicator Line -->
            <?php
                $lineColor = 'bg-gray-400';
                if ($a['priority'] === 'High') $lineColor = 'bg-red-500';
                elseif ($a['priority'] === 'Medium') $lineColor = 'bg-yellow-500';
            ?>
            <div class="absolute left-0 top-0 bottom-0 w-1 <?= $lineColor ?>"></div>
            
            <div class="flex items-start justify-between gap-4 mb-3">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-brand-600 transition-colors"><?= h($a['title']) ?></h3>
                <span class="flex-shrink-0 text-xs text-gray-400 dark:text-slate-500 bg-gray-50 dark:bg-slate-900 px-2 py-1 rounded-md">
                    <?= date('d M Y', strtotime($a['created_at'])) ?>
                </span>
            </div>
            
            <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-slate-300">
                <?= nl2br(h($a['description'] ?? '')) ?>
            </div>
            
            <?php if (!empty($a['linked_session_id']) && !empty($a['pending_q_id'])): ?>
                <div class="mt-5 border-t border-gray-100 dark:border-slate-700 pt-4">
                    <a href="form_submit.php?id=<?= $a['pending_q_id'] ?>&session_id=<?= $a['linked_session_id'] ?>" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-sm text-sm">
                        Go to Feedback Session
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
