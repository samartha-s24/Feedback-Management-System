<?php
/**
 * AFMS Admin — Announcement Actions
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}
require_csrf();

$db     = get_db();
$action = sanitize_input($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? $_POST['announcement_id'] ?? 0);

if ($action === 'delete') {
    if ($id > 0) {
        $db->query("DELETE FROM announcements WHERE announcement_id = {$id}");
        log_audit('Deleted Announcement', 'announcements', $id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Announcement deleted.'];
    }
    header('Location: announcements.php');
    exit;
}

if ($action === 'save') {
    $title   = sanitize_input($_POST['title'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $pri     = sanitize_input($_POST['priority'] ?? 'Medium');
    $status  = sanitize_input($_POST['status'] ?? 'Draft');
    $start   = sanitize_input($_POST['start_date'] ?? '');
    $end     = sanitize_input($_POST['end_date'] ?? '');
    $linkId  = (int)($_POST['linked_session_id'] ?? 0);
    $roles   = $_POST['target_roles'] ?? [];

    if (empty($title)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Title is required.'];
        header('Location: announcements.php');
        exit;
    }

    if (empty($roles)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Select at least one target audience.'];
        header('Location: announcements.php');
        exit;
    }

    $linkIdVal = $linkId > 0 ? $linkId : null;
    $startVal  = $start ?: null;
    $endVal    = $end ?: null;

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE announcements SET title=?, description=?, priority=?, linked_session_id=?, status=?, start_date=?, end_date=? WHERE announcement_id=?");
        $stmt->bind_param('sssisssi', $title, $desc, $pri, $linkIdVal, $status, $startVal, $endVal, $id);
        $stmt->execute();
        $stmt->close();
        $db->query("DELETE FROM announcement_audience WHERE announcement_id={$id}");
        log_audit('Updated Announcement', 'announcements', $id, $title);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Announcement updated.'];
    } else {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO announcements (title, description, priority, linked_session_id, status, start_date, end_date, created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssisssi', $title, $desc, $pri, $linkIdVal, $status, $startVal, $endVal, $userId);
        $stmt->execute();
        $id = (int)$db->insert_id;
        $stmt->close();
        log_audit('Created Announcement', 'announcements', $id, $title);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Announcement created.'];
    }

    $rStmt = $db->prepare("INSERT IGNORE INTO announcement_audience (announcement_id, target_role) VALUES (?,?)");
    foreach ((array)$roles as $role) {
        if (in_array($role, ['All','Student','Faculty','Parent','Alumni','Employee'])) {
            $rStmt->bind_param('is', $id, $role);
            $rStmt->execute();
        }
    }
    $rStmt->close();

    header('Location: announcements.php');
    exit;
}

header('Location: announcements.php');
