<?php
/**
 * AFMS Admin — AJAX Questionnaires
 * Fetches questionnaires for a given session.
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = get_db();
$session_id = (int)($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'questionnaires' => []]);
    exit;
}

$sql = "SELECT questionnaire_id, title FROM questionnaires WHERE session_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $session_id);
$stmt->execute();
$res = $stmt->get_result();

$questionnaires = [];
while ($row = $res->fetch_assoc()) {
    $questionnaires[] = [
        'id' => (int)$row['questionnaire_id'],
        'title' => $row['title']
    ];
}
$stmt->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'questionnaires' => $questionnaires]);
exit;
