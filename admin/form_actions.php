<?php
/**
 * AFMS Admin — Questionnaire AJAX Actions
 * Handles: delete questionnaire
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

if ($id <= 0) json_res(false, 'Invalid questionnaire ID.');

$stmt = $db->prepare("SELECT title FROM questionnaires WHERE questionnaire_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$form = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$form) json_res(false, 'Questionnaire not found.');

switch ($action) {
    case 'delete':
        $delStmt = $db->prepare("DELETE FROM questionnaires WHERE questionnaire_id = ?");
        $delStmt->bind_param('i', $id);
        $delStmt->execute();
        $delStmt->close();
        log_audit('Deleted Questionnaire', 'questionnaires', $id, $form['title']);
        json_res(true, 'Questionnaire deleted successfully.');
        break;
    default:
        json_res(false, 'Invalid action.');
}
