<?php
/**
 * Faculty Profile Update Handler — AFMS
 */
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
require_login();
require_role('Faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$db = get_db();
$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $_SESSION['profile_err'] = "All password fields are required.";
        header('Location: profile.php');
        exit;
    }

    if ($new !== $confirm) {
        $_SESSION['profile_err'] = "New passwords do not match.";
        header('Location: profile.php');
        exit;
    }

    if (strlen($new) < 8) {
        $_SESSION['profile_err'] = "New password must be at least 8 characters long.";
        header('Location: profile.php');
        exit;
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password_hash'] ?? '';
    $stmt->close();

    if (!password_verify($current, $hash)) {
        $_SESSION['profile_err'] = "Current password is incorrect.";
        header('Location: profile.php');
        exit;
    }

    // Update password
    $new_hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param('si', $new_hash, $user_id);
    if ($stmt->execute()) {
        $_SESSION['profile_msg'] = "Password updated successfully.";
    } else {
        $_SESSION['profile_err'] = "Failed to update password. Please try again.";
    }
    $stmt->close();
    header('Location: profile.php');
    exit;
}

header('Location: profile.php');
exit;
