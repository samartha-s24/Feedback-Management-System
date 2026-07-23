<?php
/**
 * Faculty Profile — AFMS
 */
declare(strict_types=1);

$page_title = 'My Profile';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Get faculty details
$facultyInfo = [];
$assignedSubjects = [];
try {
    $stmt = $db->prepare("
        SELECT u.login_id, u.full_name, u.email, u.mobile_number, u.profile_picture, u.status as user_status,
               f.faculty_id, f.designation, f.qualification, f.gender, f.joining_date, f.status as faculty_status,
               d.department_name
        FROM users u
        LEFT JOIN faculty f ON u.user_id = f.user_id
        LEFT JOIN departments d ON f.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $facultyInfo = $row;
        
        if (!empty($row['faculty_id'])) {
            $stmt_sub = $db->prepare("
                SELECT s.subject_name, s.subject_code 
                FROM faculty_subject_assignments fsa
                JOIN subjects s ON fsa.subject_id = s.subject_id
                WHERE fsa.faculty_id = ?
            ");
            $stmt_sub->bind_param('i', $row['faculty_id']);
            $stmt_sub->execute();
            $res_sub = $stmt_sub->get_result();
            while ($sub = $res_sub->fetch_assoc()) {
                $assignedSubjects[] = $sub;
            }
            $stmt_sub->close();
        }
    }
    $stmt->close();
} catch (Throwable) {}

$msg = $_SESSION['profile_msg'] ?? '';
$err = $_SESSION['profile_err'] ?? '';
unset($_SESSION['profile_msg'], $_SESSION['profile_err']);
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">My Profile</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Manage your account settings and view institutional information.</p>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400" role="alert"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert"><?= h($err) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Left Col: Profile Picture & Settings -->
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm text-center relative overflow-hidden">
                <div class="absolute inset-0 h-24 bg-gradient-to-r from-brand-600 to-brand-500 z-0"></div>
                
                <div class="relative z-10 pt-8">
                    <?php if (!empty($facultyInfo['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/uploads/avatars/<?= h($facultyInfo['profile_picture']) ?>" alt="Avatar" class="w-24 h-24 rounded-full mx-auto border-4 border-white dark:border-slate-800 shadow-md object-cover bg-white">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-brand-100 dark:bg-brand-900 text-brand-600 flex items-center justify-center text-3xl font-bold mx-auto border-4 border-white dark:border-slate-800 shadow-md">
                            <?= $_initial ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-white"><?= h($facultyInfo['full_name'] ?? 'Faculty Name') ?></h3>
                    <p class="text-sm font-medium text-brand-600 dark:text-brand-400"><?= h($facultyInfo['designation'] ?? 'Designation') ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= h($facultyInfo['department_name'] ?? 'Department') ?></p>
                </div>
            </div>

        </div>

        <!-- Right Col: Institutional Info -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Institutional Information</h3>
                    <span class="bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300 text-xs px-2.5 py-1 rounded-lg font-medium">Read Only</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Faculty ID</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?= h($facultyInfo['login_id'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Email Address</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?= h($facultyInfo['email'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Gender</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?= h($facultyInfo['gender'] ?? 'Not Specified') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Qualification</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?= h($facultyInfo['qualification'] ?? 'Not Specified') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Joining Date</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white"><?= !empty($facultyInfo['joining_date']) ? date('F j, Y', strtotime($facultyInfo['joining_date'])) : 'Not Specified' ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-1">Status</p>
                        <?php if (($facultyInfo['user_status'] ?? '') === 'Active'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($assignedSubjects)): ?>
                <div class="mt-8 pt-6 border-t border-gray-100 dark:border-slate-700">
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-3">Assigned Subjects</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($assignedSubjects as $sub): ?>
                            <span class="bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300 border border-brand-100 dark:border-brand-800 px-3 py-1.5 rounded-lg text-sm font-medium shadow-sm">
                                <?= h($sub['subject_name']) ?> <span class="opacity-60 ml-1 text-xs">(<?= h($sub['subject_code']) ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
