<?php
/**
 * Faculty Form Submission — AFMS
 */
declare(strict_types=1);

$page_title = 'Submit Feedback';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];
$questionnaire_id = (int) ($_GET['id'] ?? 0);

if (!$questionnaire_id) {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-red-50 text-red-700 p-4 rounded-xl'>Invalid form ID.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// 1. Fetch Questionnaire and Session Info
$stmt = $db->prepare("
    SELECT q.title, q.description, q.session_id, q.status, 
           fs.session_title, fs.status as session_status, fs.end_date, fs.instructions
    FROM questionnaires q
    JOIN feedback_sessions fs ON q.session_id = fs.session_id
    WHERE q.questionnaire_id = ?
");
$stmt->bind_param('i', $questionnaire_id);
$stmt->execute();
$res = $stmt->get_result();
$form = $res->fetch_assoc();
$stmt->close();

if (!$form) {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-red-50 text-red-700 p-4 rounded-xl'>Form not found.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($form['session_status'] !== 'Active') {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-red-50 text-red-700 p-4 rounded-xl'>This feedback session is not active.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($form['status'] !== 'Active') {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-red-50 text-red-700 p-4 rounded-xl'>This feedback form is not currently active.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// 2. Check if faculty already submitted
$session_id = (int) $form['session_id'];
$stmt = $db->prepare("SELECT token_id FROM submission_tokens WHERE user_id = ? AND questionnaire_id = ? AND session_id = ?");
$stmt->bind_param('iii', $user_id, $questionnaire_id, $session_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-emerald-50 text-emerald-700 p-4 rounded-xl font-medium'>You have already submitted this feedback form. Thank you!</div><a href='feedback.php' class='text-brand-600 font-semibold mt-4 inline-block hover:underline'>&larr; Back to Forms</a></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}
$stmt->close();

// 3. Check Target Role (must be Faculty or All)
$stmt = $db->prepare("SELECT target_role FROM session_target_roles WHERE session_id = ? AND target_role IN ('Faculty', 'All')");
$stmt->bind_param('i', $session_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo "<div class='max-w-4xl mx-auto p-4'><div class='bg-red-50 text-red-700 p-4 rounded-xl'>You do not have permission to view this form.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}
$stmt->close();

// Fetch Questions
$questions = [];
$stmt = $db->prepare("
    SELECT qb.question_id, qb.question_text, qb.category, qq.is_required
    FROM questionnaire_questions qq
    JOIN question_bank qb ON qq.question_id = qb.question_id
    WHERE qq.questionnaire_id = ?
    ORDER BY qq.display_order ASC
");
$stmt->bind_param('i', $questionnaire_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $responses = $_POST['responses'] ?? [];
    $comments  = trim($_POST['comments'] ?? '');
    
    // Validate required questions
    $missing = false;
    foreach ($questions as $q) {
        if ($q['is_required']) {
            if (!isset($responses[$q['question_id']]) || !in_array((int)$responses[$q['question_id']], [1,2,3,4,5], true)) {
                $missing = true;
                break;
            }
        }
    }
    
    if ($missing) {
        $error = 'Please provide a valid rating (1-5) for all required questions.';
    } else {
        // Retrieve department_id for the current user to store with the submission anonymously
        $department_id = null;
        $user_type = $_SESSION['user_type'] ?? '';
        $deptStmt = null;
        if ($user_type === 'Faculty') {
            $deptStmt = $db->prepare("SELECT department_id FROM facultys WHERE user_id = ?");
        } elseif ($user_type === 'Faculty') {
            $deptStmt = $db->prepare("SELECT department_id FROM faculty WHERE user_id = ?");
        } elseif ($user_type === 'Employee') {
            $deptStmt = $db->prepare("SELECT department_id FROM employees WHERE user_id = ?");
        } elseif ($user_type === 'Alumni') {
            $deptStmt = $db->prepare("SELECT department_id FROM alumni WHERE user_id = ?");
        } elseif ($user_type === 'Parent') {
            $deptStmt = $db->prepare("SELECT s.department_id FROM parents p JOIN facultys s ON p.faculty_id = s.faculty_id WHERE p.user_id = ?");
        }
        if ($deptStmt) {
            $deptStmt->bind_param('i', $user_id);
            $deptStmt->execute();
            if ($r = $deptStmt->get_result()->fetch_assoc()) {
                $department_id = $r['department_id'] ? (int)$r['department_id'] : null;
            }
            $deptStmt->close();
        }

        $db->begin_transaction();
        try {
            // Generate anonymous hash
            $hash = hash('sha256', random_bytes(32) . time());
            
            // Insert anonymous submission
            $stmt = $db->prepare("INSERT INTO feedback_submissions (questionnaire_id, session_id, submission_hash, department_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iisi', $questionnaire_id, $session_id, $hash, $department_id);
            $stmt->execute();
            $sub_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert responses
            if (!empty($responses)) {
                $stmt = $db->prepare("INSERT INTO submission_responses (submission_id, question_id, rating_value) VALUES (?, ?, ?)");
                foreach ($responses as $q_id => $rating) {
                    $r_val = (int) $rating;
                    if ($r_val >= 1 && $r_val <= 5) {
                        $q_int = (int) $q_id;
                        $stmt->bind_param('iii', $sub_id, $q_int, $r_val);
                        $stmt->execute();
                    }
                }
                $stmt->close();
            }
            
            // Insert comments
            if ($comments !== '') {
                $stmt = $db->prepare("INSERT INTO submission_comments (submission_id, comment_text) VALUES (?, ?)");
                $stmt->bind_param('is', $sub_id, $comments);
                $stmt->execute();
                $stmt->close();
            }
            
            // Mark user as having completed it
            $stmt = $db->prepare("INSERT INTO submission_tokens (user_id, questionnaire_id, session_id) VALUES (?, ?, ?)");
            $stmt->bind_param('iii', $user_id, $questionnaire_id, $session_id);
            $stmt->execute();
            $stmt->close();
            
            $db->commit();
            
            // Show success screen instead of redirecting
            $success = true;
        } catch (Throwable $e) {
            $db->rollback();
            $error = 'An error occurred while saving your feedback. Please try again.';
        }
    }
}
?>

<?php if (!empty($success)): ?>
    <div class="max-w-3xl mx-auto mt-12 pb-10 px-4 animate-slide-up">
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-10 sm:p-14 text-center border border-emerald-100 dark:border-emerald-900/50 shadow-2xl relative overflow-hidden">
            <!-- Decorative background elements -->
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-emerald-100 dark:bg-emerald-900/30 opacity-50 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-teal-100 dark:bg-teal-900/30 opacity-50 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="mx-auto w-24 h-24 bg-gradient-to-br from-emerald-100 to-teal-100 dark:from-emerald-900/50 dark:to-teal-900/50 rounded-full flex items-center justify-center mb-6 shadow-inner ring-4 ring-emerald-50 dark:ring-slate-800">
                    <svg class="w-12 h-12 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-4 tracking-tight">Thank You!</h2>
                <p class="text-lg text-slate-500 dark:text-slate-400 mb-8 max-w-lg mx-auto leading-relaxed">
                    Your feedback for <strong class="text-slate-700 dark:text-slate-300"><?= h($form['title']) ?></strong> has been successfully submitted and recorded anonymously.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="dashboard.php" class="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold py-3.5 px-8 rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/25 hover:-translate-y-0.5">
                        Return to Dashboard
                    </a>
                    <a href="feedback.php" class="w-full sm:w-auto bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-gray-200 dark:border-slate-600 font-bold py-3.5 px-8 rounded-xl transition-all">
                        Fill More Forms
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Load Confetti & SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fire Confetti
            confetti({
                particleCount: 200,
                spread: 90,
                origin: { y: 0.5 },
                colors: ['#10b981', '#14b8a6', '#0ea5e9', '#f59e0b', '#ec4899']
            });
            
            // Show SweetAlert as requested
            Swal.fire({
                title: 'Awesome!',
                text: 'Your feedback was successfully submitted.',
                icon: 'success',
                confirmButtonText: 'Great!',
                confirmButtonColor: '#10b981',
                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a'
            });
        });
    </script>
<?php else: ?>
<div class="max-w-4xl mx-auto pb-10">
    <div class="mb-6">
        <a href="feedback.php" class="text-sm font-semibold text-brand-600 hover:text-brand-700 transition-colors inline-flex items-center gap-1 mb-3">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Available Forms
        </a>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white"><?= h($form['title']) ?></h2>
        <?php if (!empty($form['description'])): ?>
            <p class="text-sm sm:text-base text-gray-500 dark:text-slate-400 mt-2"><?= nl2br(h($form['description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-3 text-sm font-medium">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <?= h($error) ?>
    </div>
    <?php endif; ?>

    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-5 mb-8 border border-blue-100 dark:border-blue-900/50 flex gap-4">
        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-800 text-blue-600 dark:text-blue-200 rounded-full flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <h4 class="font-bold text-blue-900 dark:text-blue-100 mb-1">Privacy Guarantee</h4>
            <p class="text-sm text-blue-800 dark:text-blue-300">
                Your responses are completely anonymous. We only track that you completed the form to prevent duplicates.
            </p>
        </div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-10 text-center border border-gray-100 dark:border-slate-700 shadow-sm">
            <p class="text-gray-500 font-medium">No questions have been added to this questionnaire.</p>
        </div>
    <?php else: ?>
        <form method="POST" class="space-y-6">
            <?= csrf_field() ?>
            
            <?php 
            $current_cat = null;
            foreach ($questions as $index => $q): 
                if ($q['category'] !== $current_cat):
                    $current_cat = $q['category'];
            ?>
                <div class="pt-6 pb-2">
                    <h3 class="text-lg font-bold text-brand-600 dark:text-brand-400 uppercase tracking-wide border-b border-gray-200 dark:border-slate-700 pb-2">
                        <?= h($current_cat ?: 'General') ?>
                    </h3>
                </div>
            <?php endif; ?>
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 shadow-sm">
                <p class="font-semibold text-gray-900 dark:text-white text-base sm:text-lg mb-4 leading-snug">
                    <span class="text-gray-400 mr-2"><?= $index + 1 ?>.</span><?= h($q['question_text']) ?>
                    <?php if ($q['is_required']): ?>
                        <span class="text-red-500 ml-1" title="Required">*</span>
                    <?php endif; ?>
                </p>
                
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6 pt-2">
                    <?php 
                    $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'];
                    $colors = [
                        1 => 'hover:border-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700',
                        2 => 'hover:border-orange-500 hover:bg-orange-50 dark:hover:bg-orange-900/20 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-700',
                        3 => 'hover:border-yellow-500 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 peer-checked:border-yellow-500 peer-checked:bg-yellow-50 peer-checked:text-yellow-700',
                        4 => 'hover:border-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 peer-checked:border-emerald-400 peer-checked:bg-emerald-50 peer-checked:text-emerald-700',
                        5 => 'hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700',
                    ];
                    for ($val = 1; $val <= 5; $val++): 
                    ?>
                        <label class="relative flex-1 cursor-pointer group">
                            <input type="radio" name="responses[<?= $q['question_id'] ?>]" value="<?= $val ?>" <?= $q['is_required'] ? 'required' : '' ?> class="peer sr-only">
                            <div class="border-2 border-gray-200 dark:border-slate-700 rounded-xl p-3 text-center transition-all <?= $colors[$val] ?> text-gray-500 dark:text-slate-400 peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-brand-500 dark:peer-focus-visible:ring-offset-slate-900">
                                <span class="block text-xl font-bold mb-1"><?= $val ?></span>
                                <span class="block text-xs font-semibold uppercase tracking-wider opacity-75"><?= $labels[$val] ?></span>
                            </div>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php endforeach; ?>
            
            <!-- Additional Comments -->
            <div class="pt-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-slate-700 shadow-sm">
                    <label for="comments" class="block font-semibold text-gray-900 dark:text-white text-base sm:text-lg mb-2">
                        Additional Comments <span class="text-sm font-normal text-gray-500">(Optional)</span>
                    </label>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Any extra feedback you'd like to share regarding this session?</p>
                    <textarea name="comments" id="comments" rows="4" class="form-input w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-gray-900 dark:text-slate-200 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow resize-y" placeholder="Type your anonymous comments here..."></textarea>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end pt-4 gap-4">
                <a href="feedback.php" class="px-6 py-3 rounded-xl font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">Cancel</a>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold px-8 py-3 rounded-xl shadow-md transition-all hover:-translate-y-0.5 inline-flex items-center gap-2">
                    Submit Feedback
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Success Message Toast Trigger if returning -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'feedback_submitted') {
        window.addEventListener('DOMContentLoaded', () => {
            if (typeof window.showToast === 'function') {
                window.showToast('Feedback submitted successfully. Thank you!', 'success', 5000);
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
