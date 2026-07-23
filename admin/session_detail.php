<?php
/**
 * AFMS Admin — Session Detail
 * Manage questionnaires within a specific session.
 */
declare(strict_types=1);

$page_title = 'Session Details';
require_once __DIR__ . '/header.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: sessions.php');
    exit;
}

// Fetch session details
$stmt = $db->prepare("SELECT * FROM feedback_sessions WHERE session_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    header('Location: sessions.php');
    exit;
}

// Fetch target roles
$targets = [];
$rStmt = $db->prepare("SELECT target_role FROM session_target_roles WHERE session_id = ?");
$rStmt->bind_param('i', $id);
$rStmt->execute();
$tr = $rStmt->get_result();
while ($r = $tr->fetch_row()) $targets[] = $r[0];
$rStmt->close();
$rolesStr = empty($targets) ? 'None' : implode(', ', $targets);

// Fetch questionnaires linked to this session
$qStmt = $db->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM questionnaire_questions qq WHERE qq.questionnaire_id = q.questionnaire_id) AS question_count,
           (SELECT COUNT(*) FROM feedback_submissions fsub WHERE fsub.questionnaire_id = q.questionnaire_id) AS response_count
    FROM questionnaires q
    WHERE q.session_id = ?
    ORDER BY q.created_at DESC
");
$qStmt->bind_param('i', $id);
$qStmt->execute();
$questionnaires = $qStmt->get_result();
$qStmt->close();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusColors = [
    'Active' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    'Draft' => 'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-slate-300',
    'Published' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    'Closed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    'Archived' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
];
?>

<?php if ($flash): ?>
<div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?> text-sm" data-auto-dismiss="4000">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $flash['type'] === 'success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' ?>"/>
    </svg>
    <?= h($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <a href="sessions.php" class="text-gray-400 hover:text-brand-600 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white"><?= h($session['session_title']) ?></h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $statusColors[$session['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                <?= h($session['status']) ?>
            </span>
        </div>
        <p class="text-sm text-gray-500 dark:text-slate-400 ml-8">Manage questionnaires and settings for this session.</p>
    </div>
    <div class="flex gap-2">
        <a href="session_form.php?id=<?= $session['session_id'] ?>" class="bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-200 text-sm font-semibold px-4 py-2 rounded-xl border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors shadow-sm">
            Edit Session
        </a>
        <a href="form_builder.php?session_id=<?= $session['session_id'] ?>" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Questionnaire
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Left: Session Info -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-slate-700">
            <h3 class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-slate-700 pb-3 mb-4">Session Details</h3>
            
            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-slate-400 font-semibold mb-1">Academic Info</p>
                    <p class="text-gray-800 dark:text-slate-200">
                        <?= h($session['academic_year'] ?? 'N/A') ?> 
                        <?php if ($session['semester']): ?> (Sem <?= $session['semester'] ?>)<?php endif; ?>
                    </p>
                </div>
                
                <div>
                    <p class="text-xs text-gray-500 dark:text-slate-400 font-semibold mb-1">Target Audience</p>
                    <p class="text-gray-800 dark:text-slate-200"><?= h($rolesStr) ?></p>
                </div>
                
                <div>
                    <p class="text-xs text-gray-500 dark:text-slate-400 font-semibold mb-1">Duration</p>
                    <p class="text-gray-800 dark:text-slate-200">
                        <?= date('M d, Y', strtotime($session['start_date'])) ?> 
                        - <?= date('M d, Y', strtotime($session['end_date'])) ?>
                    </p>
                </div>
                
                <?php if (!empty($session['instructions'])): ?>
                <div>
                    <p class="text-xs text-gray-500 dark:text-slate-400 font-semibold mb-1">Instructions</p>
                    <p class="text-gray-800 dark:text-slate-200 bg-gray-50 dark:bg-slate-900/50 p-3 rounded-xl border border-gray-100 dark:border-slate-700"><?= nl2br(h($session['instructions'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right: Questionnaires List -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-5">
            <h3 class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-slate-700 pb-3 mb-4">Questionnaires</h3>
            
            <?php if ($questionnaires->num_rows === 0): ?>
                <div class="text-center py-10">
                    <div class="w-16 h-16 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-gray-500 dark:text-slate-400 font-medium">No questionnaires found.</p>
                    <p class="text-xs text-gray-400 mt-1 mb-4">Add a questionnaire to start collecting feedback.</p>
                    <a href="form_builder.php?session_id=<?= $session['session_id'] ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                        + Add Questionnaire
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php while ($q = $questionnaires->fetch_assoc()): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 dark:border-slate-700 hover:border-brand-300 transition-colors group bg-gray-50/50 dark:bg-slate-800/50">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <a href="form_builder.php?id=<?= $q['questionnaire_id'] ?>" class="font-bold text-gray-900 dark:text-white hover:text-brand-600 transition-colors text-base">
                                    <?= h($q['title']) ?>
                                </a>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide <?= $statusColors[$q['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                                    <?= h($q['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($q['description'])): ?>
                                <p class="text-xs text-gray-500 dark:text-slate-400 mb-2 line-clamp-1"><?= h($q['description']) ?></p>
                            <?php endif; ?>
                            <div class="flex items-center gap-4 text-xs font-semibold text-gray-500 dark:text-slate-400">
                                <span class="flex items-center gap-1.5" title="Questions">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= number_format($q['question_count']) ?>
                                </span>
                                <span class="flex items-center gap-1.5" title="Responses">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
                                    <?= number_format($q['response_count']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="form_builder.php?id=<?= $q['questionnaire_id'] ?>" class="px-3 py-1.5 bg-white dark:bg-slate-700 text-gray-700 dark:text-slate-200 text-xs font-semibold rounded border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                Edit
                            </a>
                            <form action="form_actions.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this questionnaire? This will also delete any responses it has received.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $q['questionnaire_id'] ?>">
                                <button type="submit" class="px-3 py-1.5 bg-white dark:bg-slate-700 text-red-600 dark:text-red-400 text-xs font-semibold rounded border border-gray-200 dark:border-slate-600 hover:bg-red-50 dark:hover:bg-slate-600 transition-colors shadow-sm">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
