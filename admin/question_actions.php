<?php
/**
 * AFMS Admin — Question Bank AJAX Actions
 * Handles: save (create/edit), toggle_active, delete
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_res(false, 'Method not allowed.');
}

require_csrf();

$db     = get_db();
$action = sanitize_input($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? $_POST['question_id'] ?? 0);

switch ($action) {
    case 'save':
        $text = sanitize_input($_POST['question_text'] ?? '');
        $cat  = sanitize_input($_POST['category'] ?? '');
        $type = sanitize_input($_POST['question_type'] ?? 'rating');

        if (empty($text)) json_res(false, 'Question text is required.');
        if (!in_array($type, ['rating', 'mcq'])) $type = 'rating';

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE question_bank SET question_text=?, category=?, question_type=? WHERE question_id=?");
            $stmt->bind_param('sssi', $text, $cat, $type, $id);
            $stmt->execute(); $stmt->close();
            log_audit('Updated Question Bank Item', 'question_bank', $id);
            json_res(true, 'Question updated successfully.');
        } else {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $db->prepare("INSERT INTO question_bank (question_text, category, question_type, created_by) VALUES (?,?,?,?)");
            $stmt->bind_param('sssi', $text, $cat, $type, $userId);
            $stmt->execute(); $stmt->close();
            log_audit('Created Question Bank Item', 'question_bank', (int)$db->insert_id);
            json_res(true, 'Question added to bank.');
        }
        break;

    case 'toggle_active':
        if ($id <= 0) json_res(false, 'Invalid ID.');
        $state = (int)($_POST['state'] ?? 0) === 1 ? 1 : 0;
        $upStmt = $db->prepare("UPDATE question_bank SET is_active=? WHERE question_id=?");
        $upStmt->bind_param('ii', $state, $id);
        $upStmt->execute(); $upStmt->close();
        log_audit("Toggled Question Active Status to {$state}", 'question_bank', $id);
        json_res(true, 'Status updated.');
        break;

    case 'delete':
        if ($id <= 0) json_res(false, 'Invalid ID.');
        // Ensure not in use
        $chk1 = $db->prepare("SELECT COUNT(*) FROM questionnaire_questions WHERE question_id=?");
        $chk1->bind_param('i', $id); $chk1->execute();
        $inUseQQ = (int)$chk1->get_result()->fetch_row()[0]; $chk1->close();
        
        $chk2 = $db->prepare("SELECT COUNT(*) FROM submission_responses WHERE question_id=?");
        $chk2->bind_param('i', $id); $chk2->execute();
        $inUseSR = (int)$chk2->get_result()->fetch_row()[0]; $chk2->close();
        
        if ($inUseQQ > 0 || $inUseSR > 0) {
            json_res(false, 'Cannot delete: Question is tied to existing questionnaires or student responses.');
        }

        $delStmt = $db->prepare("DELETE FROM question_bank WHERE question_id=?");
        $delStmt->bind_param('i', $id);
        $delStmt->execute(); $delStmt->close();
        log_audit('Deleted Question', 'question_bank', $id);
        json_res(true, 'Question deleted.');
        break;

    default:
        json_res(false, 'Invalid action.');
}
