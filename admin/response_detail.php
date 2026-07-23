<?php
/**
 * AFMS Admin — Response Detail View
 * Shows all answers for a single submission. Supports hiding comments.
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: responses.php');
    exit;
}

$page_title = "Submission #{$id}";
require_once __DIR__ . '/header.php';

$db = get_db();

// Toggle comment visibility
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['comment_id'])) {
    require_csrf();
    $action = $_POST['action'];
    $cid = (int)$_POST['comment_id'];
    if ($action === 'hide') {
        $userId = (int)$_SESSION['user_id'];
        $upStmt = $db->prepare("UPDATE submission_comments SET is_hidden=1, hidden_by=?, hidden_at=NOW() WHERE comment_id=?");
        $upStmt->bind_param('ii', $userId, $cid);
        $upStmt->execute(); $upStmt->close();
        log_audit('Hid Comment', 'submission_comments', $cid);
    } elseif ($action === 'show') {
        $upStmt = $db->prepare("UPDATE submission_comments SET is_hidden=0, hidden_by=NULL, hidden_at=NULL WHERE comment_id=?");
        $upStmt->bind_param('i', $cid);
        $upStmt->execute(); $upStmt->close();
        log_audit('Unhid Comment', 'submission_comments', $cid);
    }
    header("Location: response_detail.php?id={$id}");
    exit;
}

// Fetch submission details
$stmt = $db->prepare("SELECT fsub.submission_id, fsub.submission_hash, fsub.submitted_at,
                             fs.session_title, q.title as form_title, fs.session_id, q.questionnaire_id
                      FROM feedback_submissions fsub
                      JOIN feedback_sessions fs ON fs.session_id = fsub.session_id
                      JOIN questionnaires q ON q.questionnaire_id = fsub.questionnaire_id
                      WHERE fsub.submission_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    echo "<div class='p-6'>Submission not found.</div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Fetch ratings
$ratingsStmt = $db->prepare("SELECT sr.rating_value, qb.question_text, qb.category
                             FROM submission_responses sr
                             JOIN question_bank qb ON qb.question_id = sr.question_id
                             WHERE sr.submission_id = ?
                             ORDER BY qb.category");
$ratingsStmt->bind_param('i', $id);
$ratingsStmt->execute();
$ratingsRes = $ratingsStmt->get_result();
$ratings = [];
$sum = 0;
while ($r = $ratingsRes->fetch_assoc()) {
    $ratings[] = $r;
    $sum += (int)$r['rating_value'];
}
$ratingsStmt->close();
$avg = count($ratings) > 0 ? round($sum / count($ratings), 1) : null;

// Fetch comments
$commentsStmt = $db->prepare("SELECT comment_id, comment_text, is_hidden, created_at FROM submission_comments WHERE submission_id = ? ORDER BY created_at ASC");
$commentsStmt->bind_param('i', $id);
$commentsStmt->execute();
$comments = $commentsStmt->get_result();
$commentsStmt->close();
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400 mb-2">
        <a href="responses.php" class="hover:text-brand-600 dark:hover:text-brand-400">Responses</a>
        <span>/</span>
        <span class="text-gray-700 dark:text-slate-200 font-medium">Submission #<?= $id ?></span>
    </div>

    <!-- Header Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-5 flex flex-col md:flex-row md:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Submission #<?= $id ?></h2>
                    <span class="font-mono text-xs text-gray-400 dark:text-slate-500 bg-gray-50 dark:bg-slate-900 px-2 py-1 rounded border border-gray-100 dark:border-slate-700" title="<?= h($sub['submission_hash']) ?>">
                        <?= substr($sub['submission_hash'], 0, 16) ?>...
                    </span>
                </div>
                <div class="text-sm text-gray-600 dark:text-slate-300 space-y-1">
                    <p><span class="text-gray-400 dark:text-slate-500 w-20 inline-block">Session:</span> <a href="sessions.php?highlight=<?= $sub['session_id'] ?>" class="font-medium hover:underline hover:text-brand-600"><?= h($sub['session_title']) ?></a></p>
                    <p><span class="text-gray-400 dark:text-slate-500 w-20 inline-block">Form:</span> <span class="font-medium"><?= h($sub['form_title']) ?></span></p>
                    <p><span class="text-gray-400 dark:text-slate-500 w-20 inline-block">Date:</span> <?= date('F j, Y, g:i a', strtotime($sub['submitted_at'])) ?></p>
                </div>
            </div>
            <div class="text-center bg-gray-50 dark:bg-slate-900/50 rounded-xl px-6 py-4 border border-gray-100 dark:border-slate-700">
                <p class="text-[10px] uppercase tracking-wider font-bold text-gray-400 dark:text-slate-500 mb-1">Average Score</p>
                <?php if ($avg !== null): ?>
                <p class="text-3xl font-bold <?= sentiment_class($avg) ?>"><?= number_format($avg, 1) ?></p>
                <div class="mt-1 flex justify-center text-yellow-400"><?= star_rating($avg) ?></div>
                <?php else: ?>
                <p class="text-xl font-bold text-gray-300">—</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Ratings -->
        <div class="lg:col-span-2 space-y-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Item Ratings
            </h3>

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php
                $curCat = null;
                foreach ($ratings as $r):
                    if ($r['category'] !== $curCat):
                        $curCat = $r['category'];
                ?>
                <div class="px-5 py-2 bg-gray-50 dark:bg-slate-900/50 text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                    <?= h($curCat ?: 'Uncategorized') ?>
                </div>
                <?php endif; ?>
                <div class="p-5 flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-gray-800 dark:text-slate-200"><?= h($r['question_text']) ?></p>
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <span class="text-lg font-bold <?= sentiment_class((float)$r['rating_value']) ?>"><?= $r['rating_value'] ?></span>
                        <div class="text-yellow-400 scale-75 origin-right"><?= star_rating((float)$r['rating_value']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($ratings)): ?>
                <div class="p-6 text-center text-gray-500">No ratings submitted.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments -->
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                Additional Comments
            </h3>

            <div class="space-y-3">
                <?php
                $hasComments = false;
                while ($c = $comments->fetch_assoc()):
                    $hasComments = true;
                ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-4 <?= $c['is_hidden'] ? 'opacity-50' : '' ?>">
                    <?php if ($c['is_hidden']): ?>
                        <div class="flex items-center gap-2 text-xs text-red-500 font-semibold mb-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            Hidden by Moderator
                        </div>
                    <?php endif; ?>
                    <p class="text-sm text-gray-800 dark:text-slate-200 italic whitespace-pre-wrap">"<?= h($c['comment_text']) ?>"</p>

                    <form method="POST" class="mt-3 flex justify-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                        <?php if ($c['is_hidden']): ?>
                        <input type="hidden" name="action" value="show">
                        <button type="submit" class="text-xs text-brand-600 hover:underline">Show Comment</button>
                        <?php else: ?>
                        <input type="hidden" name="action" value="hide">
                        <button type="submit" class="text-xs text-red-500 hover:underline" onclick="return confirm('Hide this comment from reports?')">Hide Comment</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endwhile; ?>
                <?php if (!$hasComments): ?>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5 text-center text-gray-500 text-sm">
                    No comments provided.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
