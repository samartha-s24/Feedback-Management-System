<?php
/**
 * Faculty Notifications — AFMS
 */
declare(strict_types=1);

$page_title = 'Notifications';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Mark all as read when visited
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    require_csrf();
    $upStmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $upStmt->bind_param('i', $user_id);
    $upStmt->execute();
    $upStmt->close();
    header("Location: notifications.php");
    exit;
}

$notifications = [];
try {
    $stmt = $db->prepare("
        SELECT notification_id, type, title, body, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} catch (Throwable) {}

?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Updates tailored for you.</p>
        </div>
        <?php if (!empty($notifications) && $_unread > 0): ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="mark_read" value="1">
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                Mark all as read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <?php if (empty($notifications)): ?>
        <div class="p-12 text-center">
            <div class="w-20 h-20 bg-gray-50 dark:bg-slate-900 text-gray-300 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">No notifications yet</h3>
            <p class="text-gray-500 dark:text-slate-400 text-sm">When you receive notifications, they will appear here.</p>
        </div>
        <?php else: ?>
        <ul class="divide-y divide-gray-100 dark:divide-slate-700">
            <?php foreach ($notifications as $n): 
                $icon = '';
                $iconBg = '';
                switch ($n['type']) {
                    case 'Success': 
                        $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>';
                        $iconBg = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30';
                        break;
                    case 'Warning':
                        $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
                        $iconBg = 'bg-amber-100 text-amber-600 dark:bg-amber-900/30';
                        break;
                    case 'Reminder':
                        $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>';
                        $iconBg = 'bg-blue-100 text-blue-600 dark:bg-blue-900/30';
                        break;
                    default: // Info
                        $icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';
                        $iconBg = 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300';
                        break;
                }
            ?>
            <li class="p-4 sm:p-5 flex gap-4 hover:bg-gray-50 dark:hover:bg-slate-750 transition-colors <?= !$n['is_read'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' ?>">
                <div class="w-10 h-10 rounded-full <?= $iconBg ?> flex items-center justify-center flex-shrink-0 mt-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?= $icon ?></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-white mb-0.5 <?= !$n['is_read'] ? '' : 'opacity-80' ?>"><?= h($n['title']) ?></p>
                    <?php if (!empty($n['body'])): ?>
                    <p class="text-sm text-gray-600 dark:text-slate-300 mb-2 <?= !$n['is_read'] ? '' : 'opacity-80' ?>"><?= h($n['body']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 font-medium"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></p>
                </div>
                <?php if (!$n['is_read']): ?>
                <div class="w-2 h-2 rounded-full bg-brand-500 mt-2 flex-shrink-0"></div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
