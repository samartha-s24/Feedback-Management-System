<?php
/**
 * Faculty Dashboard — AFMS
 */
declare(strict_types=1);

$page_title = 'Dashboard';
require_once __DIR__ . '/header.php';

$db = get_db();
$user_id = (int) $_SESSION['user_id'];

// Get faculty specifics
$facultyInfo = [
    'department' => 'Not Assigned',
    'designation' => 'Faculty',
    'qualification' => 'N/A'
];
$assignedSubjects = [];
try {
    $stmt = $db->prepare("
        SELECT d.department_name, f.designation, f.qualification, f.faculty_id
        FROM faculty f
        LEFT JOIN departments d ON f.department_id = d.department_id
        WHERE f.user_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $facultyInfo['department'] = $row['department_name'] ?? 'Not Assigned';
        $facultyInfo['designation'] = $row['designation'] ?? 'Faculty';
        $facultyInfo['qualification'] = $row['qualification'] ?? 'N/A';
        $faculty_id = $row['faculty_id'];

        // Get subjects
        $stmt_sub = $db->prepare("
            SELECT s.subject_name, s.subject_code 
            FROM faculty_subject_assignments fsa
            JOIN subjects s ON fsa.subject_id = s.subject_id
            WHERE fsa.faculty_id = ?
        ");
        $stmt_sub->bind_param('i', $faculty_id);
        $stmt_sub->execute();
        $res_sub = $stmt_sub->get_result();
        while ($sub = $res_sub->fetch_assoc()) {
            $assignedSubjects[] = $sub;
        }
        $stmt_sub->close();
    }
    $stmt->close();
} catch (Throwable) {}

// Get pending feedback count
$pendingCount = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(q.questionnaire_id) 
        FROM feedback_sessions fs
        JOIN session_target_roles str ON fs.session_id = str.session_id
        JOIN questionnaires q ON fs.session_id = q.session_id
        WHERE fs.status = 'Active' 
          AND q.status = 'Active'
          AND str.target_role IN ('Faculty', 'All')
          AND NOT EXISTS (
              SELECT 1 FROM submission_tokens 
              WHERE user_id = ? AND questionnaire_id = q.questionnaire_id AND session_id = fs.session_id
          )
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $pendingCount = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
} catch (Throwable) {}

// Get published reports count (mocking relevance by assuming all are visible or those specific to department)
$reportsCount = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE department_id = (SELECT department_id FROM faculty WHERE user_id = ?) OR department_id IS NULL");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $reportsCount = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
} catch (Throwable) {}

// Get active announcements count and top 3 latest
$announceCount = 0;
$recent_announcements = [];
try {
    $now_date = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT DISTINCT a.* 
        FROM announcements a
        JOIN announcement_audience aa ON a.announcement_id = aa.announcement_id
        WHERE a.status = 'Published' 
          AND (a.start_date IS NULL OR a.start_date <= ?)
          AND (a.end_date IS NULL OR a.end_date >= ?)
          AND aa.target_role IN ('Faculty', 'All')
        ORDER BY a.priority = 'High' DESC, a.created_at DESC
    ");
    $stmt->bind_param('ss', $now_date, $now_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $announceCount = $res->num_rows;
    while ($row = $res->fetch_assoc()) {
        if (count($recent_announcements) < 3) {
            $recent_announcements[] = $row;
        }
    }
    $stmt->close();
} catch (Throwable) {}

// Fetch Top 3 Recent Reports and Latest Report Analytics
$recent_reports = [];
$latest_report = null;
$barLabels = [];
$barData = [];
$sentiments = ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
$averageRating = '0.0';

try {
    // 1. Fetch recent reports
    $dept_id_filter = $facultyInfo['department'] !== 'Not Assigned' ? "(r.department_id = (SELECT department_id FROM faculty WHERE user_id = {$user_id}) OR r.department_id IS NULL)" : "1=1";
    $repStmt = $db->prepare("
        SELECT r.*, d.department_name, s.subject_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN subjects s ON r.subject_id = s.subject_id
        WHERE $dept_id_filter
        ORDER BY r.generated_at DESC
    ");
    $repStmt->execute();
    $repRes = $repStmt->get_result();
    $reportsCount = $repRes->num_rows;
    
    while ($row = $repRes->fetch_assoc()) {
        if (count($recent_reports) < 3) $recent_reports[] = $row;
    }
    $repStmt->close();

    // 2. Fetch analytics for the LATEST report if exists
    if (!empty($recent_reports)) {
        $latest_report = $recent_reports[0];
        $sess_id = $latest_report['session_id'];
        $dept_id = $latest_report['department_id'];

        // Avg Rating
        $kpiStmt = $db->prepare("SELECT ROUND(AVG(sr.rating_value), 2) as avg_rating FROM feedback_submissions fsub LEFT JOIN submission_responses sr ON sr.submission_id = fsub.submission_id WHERE fsub.session_id = ? AND (fsub.department_id = ? OR ? IS NULL)");
        $kpiStmt->bind_param('iii', $sess_id, $dept_id, $dept_id);
        $kpiStmt->execute();
        $kpiRes = $kpiStmt->get_result()->fetch_assoc();
        if ($kpiRes && $kpiRes['avg_rating']) $averageRating = number_format((float)$kpiRes['avg_rating'], 1);
        $kpiStmt->close();

        // Bar Chart (Category Performance)
        $catStmt = $db->prepare("SELECT qb.category, ROUND(AVG(sr.rating_value) / 5 * 100, 2) as score_pct FROM submission_responses sr JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id JOIN question_bank qb ON qb.question_id = sr.question_id WHERE fsub.session_id = ? AND (fsub.department_id = ? OR ? IS NULL) AND sr.rating_value > 0 GROUP BY qb.category ORDER BY score_pct DESC");
        $catStmt->bind_param('iii', $sess_id, $dept_id, $dept_id);
        $catStmt->execute();
        $catRes = $catStmt->get_result();
        while ($c = $catRes->fetch_assoc()) {
            $barLabels[] = $c['category'] ?: 'Uncategorized';
            $barData[] = (float)$c['score_pct'];
        }
        $catStmt->close();
        if (empty($barLabels)) { $barLabels = ['No Data']; $barData = [0]; }

        // Sentiment Distribution
        $sentStmt = $db->prepare("SELECT SUM(rating_value >= 4) AS pos, SUM(rating_value = 3) AS neu, SUM(rating_value <= 2) AS neg FROM submission_responses sr JOIN feedback_submissions fsub ON fsub.submission_id = sr.submission_id WHERE fsub.session_id = ? AND (fsub.department_id = ? OR ? IS NULL) AND sr.rating_value > 0");
        $sentStmt->bind_param('iii', $sess_id, $dept_id, $dept_id);
        $sentStmt->execute();
        $sentRes = $sentStmt->get_result()->fetch_assoc();
        if ($sentRes) {
            $pos = (int)$sentRes['pos']; $neu = (int)$sentRes['neu']; $neg = (int)$sentRes['neg'];
            $sum = $pos + $neu + $neg;
            if ($sum > 0) {
                $sentiments['Positive'] = round(($pos / $sum) * 100);
                $sentiments['Neutral'] = round(($neu / $sum) * 100);
                $sentiments['Negative'] = round(($neg / $sum) * 100);
            }
        }
        $sentStmt->close();
    }
} catch (Throwable $e) {}

?>

<div class="max-w-6xl mx-auto space-y-6">
    
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-brand-700 to-brand-500 rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div class="absolute top-0 right-0 -mt-8 -mr-8 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="absolute bottom-0 right-20 -mb-8 w-32 h-32 bg-brand-300 opacity-20 rounded-full blur-xl"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center gap-6 justify-between">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center flex-shrink-0 border border-white/30 shadow-inner text-2xl font-bold">
                    <?= $_initial ?>
                </div>
                <div>
                    <p class="text-brand-100 text-sm font-semibold uppercase tracking-wider mb-1">Faculty Dashboard</p>
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-2"><?= $_faculty_name ?></h2>
                    <p class="text-brand-50 font-medium"><?= h($facultyInfo['designation']) ?> • <?= h($facultyInfo['department']) ?></p>
                </div>
            </div>
            
            <?php if (!empty($assignedSubjects)): ?>
            <div class="bg-white/10 rounded-2xl p-4 backdrop-blur border border-white/20 text-sm w-full md:w-auto mt-4 md:mt-0">
                <p class="text-brand-100 font-semibold mb-2 uppercase text-xs tracking-wider">Assigned Subjects</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($assignedSubjects as $sub): ?>
                        <span class="bg-white/20 px-2 py-1 rounded-lg font-medium text-xs border border-white/10">
                            <?= h($sub['subject_code']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Pending -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-4 hover:-translate-y-1 transition-transform duration-300">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-500 flex-shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-slate-400">Pending Feedbacks</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= $pendingCount ?></p>
            </div>
        </div>
        <!-- Reports -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-4 hover:-translate-y-1 transition-transform duration-300">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-500 flex-shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-slate-400">Published Reports</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= $reportsCount ?></p>
            </div>
        </div>
        <!-- Announcements -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-4 hover:-translate-y-1 transition-transform duration-300">
            <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-500 flex-shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-slate-400">Announcements</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= $announceCount ?></p>
            </div>
        </div>
        <!-- Rating -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-4 hover:-translate-y-1 transition-transform duration-300">
            <div class="w-14 h-14 rounded-2xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-purple-500 flex-shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-slate-400">Average Rating</p>
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= $averageRating ?><span class="text-lg text-gray-400">/5</span></p>
            </div>
        </div>
    </div>
    
    <!-- Analytics Charts (Only show if there is a report) -->
    <?php if (!empty($recent_reports)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Question-wise Performance -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col h-[350px]">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Latest Report Performance (%)</h3>
            <div class="relative flex-1 min-h-0">
                <canvas id="barChart"></canvas>
            </div>
        </div>
        <!-- Sentiment Distribution -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col h-[350px]">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Latest Session Sentiment</h3>
            <div class="relative flex-1 flex items-center justify-center min-h-0 pb-2">
                <div class="relative w-full max-w-[240px] h-full max-h-[240px]">
                    <canvas id="doughnutChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tertiary Grid: Activity & Widgets -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Announcements Widget -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col h-full">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Announcements</h3>
                <a href="announcements.php" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline bg-brand-50 dark:bg-brand-900/30 px-3 py-1.5 rounded-lg transition-colors">View All</a>
            </div>
            <div class="flex-1 space-y-4">
                <?php if (empty($recent_announcements)): ?>
                    <p class="text-sm text-gray-500 dark:text-slate-400 italic">No new announcements.</p>
                <?php else: ?>
                    <?php foreach ($recent_announcements as $ann): ?>
                    <?php $isHigh = ($ann['priority'] === 'High' || $ann['priority'] === 'Urgent'); ?>
                    <div class="border-l-4 <?= $isHigh ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/10' : 'border-brand-500 bg-gray-50 dark:bg-slate-900/50' ?> rounded-r-xl p-3 shadow-sm">
                        <div class="flex justify-between items-start gap-2 mb-1">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white line-clamp-1"><?= h($ann['title']) ?></h4>
                            <?php if ($isHigh): ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 rounded-md">Urgent</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-slate-400 line-clamp-2"><?= strip_tags(html_entity_decode($ann['description'])) ?></p>
                        <p class="text-[10px] text-gray-400 mt-2 font-medium"><?= date('M d, Y', strtotime($ann['created_at'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reports Widget -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col h-full">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Recent Reports</h3>
                <a href="reports.php" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline bg-brand-50 dark:bg-brand-900/30 px-3 py-1.5 rounded-lg transition-colors">View All</a>
            </div>
            <div class="flex-1 space-y-4">
                <?php if (empty($recent_reports)): ?>
                    <p class="text-sm text-gray-500 dark:text-slate-400 italic">No recent reports available for display.</p>
                <?php else: ?>
                    <?php foreach ($recent_reports as $rep): ?>
                    <a href="report_view.php?id=<?= $rep['report_id'] ?>" class="block group p-3 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-xl hover:border-brand-300 dark:hover:border-brand-700 transition-colors shadow-sm">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors line-clamp-1"><?= h($rep['report_title']) ?></h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-md border border-emerald-100 dark:border-emerald-800">
                                <?= h($rep['department_name'] ?? 'All') ?>
                            </span>
                            <span class="text-xs text-gray-500"><?= date('M d', strtotime($rep['generated_at'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar Widget -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-gray-100 dark:border-slate-700 shadow-sm flex flex-col h-full">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Calendar</h3>
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="w-full max-w-xs bg-gray-50 dark:bg-slate-900 rounded-3xl p-5 border border-gray-100 dark:border-slate-700 shadow-inner">
                    <div class="flex justify-between items-center mb-4">
                        <button id="cal-prev" class="text-gray-400 hover:text-brand-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span id="cal-month-year" class="text-sm font-bold text-gray-800 dark:text-slate-200 uppercase tracking-wide"></span>
                        <button id="cal-next" class="text-gray-400 hover:text-brand-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-gray-500 mb-2">
                        <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                    </div>
                    <div id="cal-days" class="grid grid-cols-7 gap-1 text-center text-sm font-medium text-gray-700 dark:text-slate-300">
                        <!-- JS populated -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($recent_reports)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';

        // Bar Chart
        const barCtx = document.getElementById('barChart');
        if (barCtx) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($barLabels) ?>,
                    datasets: [{
                        label: 'Score (%)',
                        data: <?= json_encode($barData) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { color: textColor } },
                        x: { grid: { display: false }, ticks: { color: textColor, maxRotation: 0, minRotation: 0 } }
                    }
                }
            });
        }

        // Doughnut Chart
        const doughCtx = document.getElementById('doughnutChart');
        if (doughCtx) {
            new Chart(doughCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Positive', 'Neutral', 'Negative'],
                    datasets: [{
                        data: <?= json_encode(array_values($sentiments)) ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.9)', // Emerald
                            'rgba(245, 158, 11, 0.9)', // Amber
                            'rgba(239, 68, 68, 0.9)'   // Red
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: textColor, padding: 20, font: { family: 'Inter, sans-serif' } } }
                    }
                }
            });
        }

        // Calendar Logic
        const calMonthYear = document.getElementById('cal-month-year');
        const calDays = document.getElementById('cal-days');
        const calPrev = document.getElementById('cal-prev');
        const calNext = document.getElementById('cal-next');

        if (calMonthYear && calDays) {
            let currentDate = new Date();
            let today = new Date();

            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                const firstDayIndex = new Date(year, month, 1).getDay();
                const lastDay = new Date(year, month + 1, 0).getDate();

                const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                calMonthYear.textContent = `${monthNames[month]} ${year}`;

                let daysHTML = "";
                for (let i = 0; i < firstDayIndex; i++) {
                    daysHTML += `<div class="p-1.5 opacity-0"></div>`;
                }

                for (let i = 1; i <= lastDay; i++) {
                    if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                        daysHTML += `<div class="p-1.5 bg-brand-600 text-white rounded-full shadow-md shadow-brand-500/30">${i}</div>`;
                    } else {
                        daysHTML += `<div class="p-1.5 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-full cursor-pointer transition-colors">${i}</div>`;
                    }
                }
                calDays.innerHTML = daysHTML;
            }

            calPrev.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });

            calNext.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });

            renderCalendar();
        }
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
