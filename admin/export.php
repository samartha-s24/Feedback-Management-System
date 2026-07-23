<?php
/**
 * AFMS Admin — Export Report to CSV
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}
require_csrf();

$db = get_db();

$deptFilter = (int)($_POST['department'] ?? 0);
$ayFilter   = sanitize_input($_POST['academic_year'] ?? '');
$semFilter  = (int)($_POST['semester'] ?? 0);
$sessId     = (int)($_POST['session'] ?? 0);
$formId     = (int)($_POST['questionnaire'] ?? 0);
$groupBy    = sanitize_input($_POST['groupby'] ?? 'question');

$where  = ["1=1"];
$params = [];
$types  = '';

if ($deptFilter > 0) { $where[] = "fsub.department_id = ?"; $params[] = $deptFilter; $types .= 'i'; }
if ($sessId > 0) { $where[] = "fsub.session_id = ?"; $params[] = $sessId; $types .= 'i'; }
if ($formId > 0) { $where[] = "fsub.questionnaire_id = ?"; $params[] = $formId; $types .= 'i'; }
if ($ayFilter !== '') { $where[] = "fs.academic_year = ?"; $params[] = $ayFilter; $types .= 's'; }
if ($semFilter > 0) { $where[] = "fs.semester = ?"; $params[] = $semFilter; $types .= 'i'; }

$whereStr = implode(" AND ", $where);

if ($groupBy === 'question') {
    $sql = "SELECT qb.question_text, qb.category, qb.question_type,
                   COUNT(sr.response_id) as total_responses,
                   ROUND(AVG(sr.rating_value),2) as avg_score,
                   SUM(sr.rating_value >= 4) as pos_count,
                   SUM(sr.rating_value <= 2) as neg_count
            FROM submission_responses sr
            JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
            LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
            JOIN question_bank qb ON qb.question_id = sr.question_id
            WHERE {$whereStr}
            GROUP BY qb.question_id
            ORDER BY qb.category, avg_score DESC";
} else {
    $sql = "SELECT qb.category,
                   COUNT(sr.response_id) as total_responses,
                   ROUND(AVG(sr.rating_value),2) as avg_score,
                   SUM(sr.rating_value >= 4) as pos_count,
                   SUM(sr.rating_value <= 2) as neg_count
            FROM submission_responses sr
            JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id
            LEFT JOIN feedback_sessions fs ON fsub.session_id = fs.session_id
            JOIN question_bank qb ON qb.question_id = sr.question_id
            WHERE {$whereStr}
            GROUP BY qb.category
            ORDER BY avg_score DESC";
}

$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$filename = "AFMS_Report_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 support
fputs($output, "\xEF\xBB\xBF");

if ($groupBy === 'question') {
    fputcsv($output, ['Question', 'Category', 'Type', 'Responses', 'Avg Score', 'Positive (4-5)', 'Negative (1-2)']);
    while ($r = $res->fetch_assoc()) {
        fputcsv($output, [
            $r['question_text'],
            $r['category'] ?: 'Uncategorized',
            $r['question_type'],
            $r['total_responses'],
            $r['avg_score'],
            $r['pos_count'],
            $r['neg_count']
        ]);
    }
} else {
    fputcsv($output, ['Category', 'Responses', 'Avg Score', 'Positive (4-5)', 'Negative (1-2)']);
    while ($r = $res->fetch_assoc()) {
        fputcsv($output, [
            $r['category'] ?: 'Uncategorized',
            $r['total_responses'],
            $r['avg_score'],
            $r['pos_count'],
            $r['neg_count']
        ]);
    }
}

fclose($output);
$stmt->close();
log_audit('Exported CSV Report', 'feedback_submissions');
exit;
