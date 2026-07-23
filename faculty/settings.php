<?php
/**
 * Faculty Settings — AFMS
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();
require_role('Faculty');

$db = get_db();
$user_id = (int) $_SESSION['user_id'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Fetch current user details
$stmt = $db->prepare("SELECT email, mobile_number FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();
$current_email = $userData['email'] ?? '';
$current_mobile = $userData['mobile_number'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        
        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'All fields are required.'];
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'New passwords do not match.'];
        } elseif (strlen($new_pass) < 8) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'New password must be at least 8 characters long.'];
        } else {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_row()[0] ?? '';
            $stmt->close();
            
            if (!password_verify($current_pass, $hash)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Current password is incorrect.'];
            } else {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param('si', $new_hash, $user_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password updated successfully.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update password. Please try again.'];
                }
                $stmt->close();
            }
        }
        header("Location: settings.php");
        exit;

    } elseif ($action === 'update_email') {
        $current_pass = $_POST['email_password'] ?? '';
        $new_email = sanitize_input($_POST['new_email'] ?? '');

        if (empty($current_pass) || empty($new_email)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password and new email are required.'];
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid email address format.'];
        } else {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_row()[0] ?? '';
            $stmt->close();

            if (!password_verify($current_pass, $hash)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password is incorrect.'];
            } else {
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $checkStmt->bind_param('si', $new_email, $user_id);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'This email is already in use.'];
                } else {
                    $stmt = $db->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                    $stmt->bind_param('si', $new_email, $user_id);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Email address updated successfully.'];
                    } else {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update email. Please try again.'];
                    }
                    $stmt->close();
                }
                $checkStmt->close();
            }
        }
        header("Location: settings.php");
        exit;

    } elseif ($action === 'update_mobile') {
        $current_pass = $_POST['mobile_password'] ?? '';
        $new_mobile = sanitize_input($_POST['new_mobile'] ?? '');

        if (empty($current_pass) || empty($new_mobile)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password and new mobile number are required.'];
        } else {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_row()[0] ?? '';
            $stmt->close();

            if (!password_verify($current_pass, $hash)) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password is incorrect.'];
            } else {
                $stmt = $db->prepare("UPDATE users SET mobile_number = ? WHERE user_id = ?");
                $stmt->bind_param('si', $new_mobile, $user_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Mobile number updated successfully.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update mobile number. Please try again.'];
                }
                $stmt->close();
            }
        }
        header("Location: settings.php");
        exit;
    }
}

$page_title = 'Settings';
require_once __DIR__ . '/header.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Manage your account security and preferences.</p>
    </div>

    <?php if ($flash): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?php else: ?>
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <?php endif; ?>
        <?= h($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Left Column: Security Settings -->
        <div class="space-y-6">
            
            <!-- Update Email -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                <form method="POST" class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_email">
                    
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Change Email Address
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">New Email Address</label>
                        <input type="email" name="new_email" value="<?= h($current_email) ?>" required class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Current Password</label>
                        <input type="password" name="email_password" required class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200" placeholder="Verify it's you">
                    </div>
                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors shadow-sm text-sm">Update Email</button>
                    </div>
                </form>
            </div>

            <!-- Update Mobile Number -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                <form method="POST" class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_mobile">
                    
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Change Mobile Number
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">New Mobile Number</label>
                        <input type="text" name="new_mobile" value="<?= h($current_mobile) ?>" required class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Current Password</label>
                        <input type="password" name="mobile_password" required class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200" placeholder="Verify it's you">
                    </div>
                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors shadow-sm text-sm">Update Mobile</button>
                    </div>
                </form>
            </div>
            
            <!-- Update Password -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                <form method="POST" class="p-6 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_password">
                    
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Change Password
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">New Password</label>
                        <input type="password" name="new_password" required minlength="8" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                        <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" class="form-input w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm dark:text-slate-200">
                    </div>
                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors shadow-sm text-sm">Update Password</button>
                    </div>
                </form>
            </div>
            
        </div>
        
        <!-- Right Column: Preferences -->
        <div class="space-y-6">
            
            <!-- Theme Customization -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        Theme Customization
                    </h3>
                    <p class="text-xs text-gray-500 mb-4">Choose a color theme. These beautifully adapt to both light and dark modes.</p>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" onclick="setTheme('ocean')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="ocean">
                            <div class="w-8 h-8 rounded-full bg-[#2563EB] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Ocean</span>
                        </button>
                        <button type="button" onclick="setTheme('emerald')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="emerald">
                            <div class="w-8 h-8 rounded-full bg-[#10B981] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Emerald</span>
                        </button>
                        <button type="button" onclick="setTheme('amethyst')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="amethyst">
                            <div class="w-8 h-8 rounded-full bg-[#8B5CF6] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Amethyst</span>
                        </button>
                        <button type="button" onclick="setTheme('sunset')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="sunset">
                            <div class="w-8 h-8 rounded-full bg-[#F59E0B] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Sunset</span>
                        </button>
                        <button type="button" onclick="setTheme('rose')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="rose">
                            <div class="w-8 h-8 rounded-full bg-[#F43F5E] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Rose</span>
                        </button>
                        <button type="button" onclick="setTheme('cyan')" class="theme-btn relative group flex flex-col items-center gap-2 rounded-xl border-2 border-transparent hover:border-gray-300 dark:hover:border-slate-600 transition-all p-2" data-theme="cyan">
                            <div class="w-8 h-8 rounded-full bg-[#06B6D4] shadow-sm flex items-center justify-center text-white"><svg class="w-4 h-4 hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
                            <span class="text-xs font-medium text-gray-700 dark:text-slate-300">Cyan</span>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
    // Theme setter matching admin UI
    function setTheme(themeName) {
        localStorage.setItem('afms-custom-theme', themeName);
        location.reload(); // Quickest way to apply without complex JS reloading
    }

    // Highlight active theme button
    document.addEventListener('DOMContentLoaded', () => {
        const active = localStorage.getItem('afms-custom-theme') || 'ocean';
        const btn = document.querySelector(`.theme-btn[data-theme="${active}"]`);
        if (btn) {
            btn.classList.add('border-brand-500', 'bg-brand-50', 'dark:bg-brand-900/20');
            btn.querySelector('.check-icon').classList.remove('hidden');
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
