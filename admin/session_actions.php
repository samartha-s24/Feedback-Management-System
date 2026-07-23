<?php
/**
 * AFMS Admin — Session AJAX Actions
 * Handles: activate, publish, close, archive, delete
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_res(false, 'Method not allowed.');
}

require_csrf();

$db     = get_db();
$id     = (int)($_POST['id'] ?? 0);
$action = sanitize_input($_POST['action'] ?? '');

if ($id <= 0) json_res(false, 'Invalid session ID.');

// Verify session exists
$stmt = $db->prepare("SELECT session_id, session_title, status FROM feedback_sessions WHERE session_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$sess = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sess) json_res(false, 'Session not found.');

$allowedTransitions = [
    'activate' => ['Draft','Published'],
    'publish'  => ['Draft'],
    'close'    => ['Active'],
    'archive'  => ['Closed'],
    'delete'   => ['Draft','Published','Closed','Archived'],
];

if (!array_key_exists($action, $allowedTransitions)) {
    json_res(false, 'Invalid action.');
}

if (!in_array($sess['status'], $allowedTransitions[$action])) {
    json_res(false, "Cannot {$action} a session with status: {$sess['status']}.");
}

switch ($action) {
    case 'publish':
        $upStmt = $db->prepare("UPDATE feedback_sessions SET status='Published', is_active=0 WHERE session_id=?");
        $upStmt->bind_param('i', $id);
        $upStmt->execute(); $upStmt->close();
        log_audit('Published Feedback Session', 'feedback_sessions', $id, $sess['session_title']);
        json_res(true, 'Session published successfully.');

    case 'activate':
        $actStmt = $db->prepare("UPDATE feedback_sessions SET status='Active', is_active=1 WHERE session_id=?");
        $actStmt->bind_param('i', $id);
        $actStmt->execute(); $actStmt->close();
        
        $qStmt = $db->prepare("UPDATE questionnaires SET status='Active' WHERE session_id=? AND status='Draft'");
        $qStmt->bind_param('i', $id);
        $qStmt->execute(); $qStmt->close();
        
        log_audit('Activated Feedback Session', 'feedback_sessions', $id, $sess['session_title']);
        json_res(true, 'Session is now Active.');

    case 'close':
        $clsStmt = $db->prepare("UPDATE feedback_sessions SET status='Closed', is_active=0 WHERE session_id=?");
        $clsStmt->bind_param('i', $id);
        $clsStmt->execute(); $clsStmt->close();
        log_audit('Closed Feedback Session', 'feedback_sessions', $id, $sess['session_title']);
        json_res(true, 'Session closed successfully.');

    case 'archive':
        $userId = (int)$_SESSION['user_id'];
        $arcStmt = $db->prepare("UPDATE feedback_sessions SET status='Archived', is_active=0, archived_at=NOW(), archived_by=? WHERE session_id=?");
        $arcStmt->bind_param('ii', $userId, $id);
        $arcStmt->execute(); $arcStmt->close();
        log_audit('Archived Feedback Session', 'feedback_sessions', $id, $sess['session_title']);
        json_res(true, 'Session archived.');

    case 'delete':
        $delStmt = $db->prepare("DELETE FROM feedback_sessions WHERE session_id=?");
        $delStmt->bind_param('i', $id);
        $delStmt->execute(); $delStmt->close();
        log_audit('Deleted Feedback Session', 'feedback_sessions', $id, $sess['session_title']);
        json_res(true, 'Session deleted.');
}
