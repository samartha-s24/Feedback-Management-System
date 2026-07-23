<?php
/**
 * AFMS Admin Shared Helpers
 * =====================================================================
 * Common utility functions used across all admin pages.
 * Requires: config/db.php (get_db) and config/session.php already loaded.
 * =====================================================================
 */
declare(strict_types=1);

// ─── Output Escaping ─────────────────────────────────────────────────────────

/**
 * Safely escape a value for HTML output (shorthand for htmlspecialchars).
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ─── System Settings ─────────────────────────────────────────────────────────

/**
 * Returns a system setting value, loading all settings once per request.
 */
function get_setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        try {
            $db = get_db();
            $result = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row['setting_key']] = (string) $row['setting_value'];
                }
            }
        } catch (Throwable) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Persists a single setting to the database.
 */
function save_setting(string $key, string $value): void
{
    $db     = get_db();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $stmt   = $db->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                 updated_by    = VALUES(updated_by)"
    );
    $stmt->bind_param('ssi', $key, $value, $userId);
    $stmt->execute();
    $stmt->close();
}

// ─── Audit Logging ───────────────────────────────────────────────────────────

/**
 * Records an admin action to the audit_logs table.
 */
function log_audit(
    string $action,
    string $table    = '',
    int    $recordId = 0,
    string $details  = ''
): void {
    try {
        $db      = get_db();
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt    = $db->prepare(
            "INSERT INTO audit_logs (admin_id, action, table_name, record_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ississ', $adminId, $action, $table, $recordId, $details, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable) {
        // Never let audit failures break the application
    }
}

// ─── Notifications ───────────────────────────────────────────────────────────

/**
 * Returns the count of unread notifications for the current admin user.
 */
function get_unread_count(): int
{
    try {
        $db     = get_db();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $stmt   = $db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count;
    } catch (Throwable) {
        return 0;
    }
}

// ─── UI Badge Helpers ────────────────────────────────────────────────────────

/**
 * Renders a coloured status badge pill.
 */
function status_badge(string $status): string
{
    $map = [
        'Draft'     => 'bg-slate-100 text-slate-700',
        'Published' => 'bg-blue-100 text-blue-700',
        'Active'    => 'bg-emerald-100 text-emerald-700',
        'Closed'    => 'bg-red-100 text-red-700',
        'Archived'  => 'bg-purple-100 text-purple-700',
        'Expired'   => 'bg-orange-100 text-orange-700',
        'Private'   => 'bg-gray-100 text-gray-600',
        'Low'       => 'bg-slate-100 text-slate-600',
        'Medium'    => 'bg-yellow-100 text-yellow-700',
        'High'      => 'bg-red-100 text-red-700',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-700';
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {$cls}\">"
        . h($status) . '</span>';
}

/**
 * Renders a coloured dot indicator for session status (for tables).
 */
function status_dot(string $status): string
{
    $dots = [
        'Active'    => 'bg-emerald-500',
        'Draft'     => 'bg-slate-400',
        'Published' => 'bg-blue-500',
        'Closed'    => 'bg-red-500',
        'Archived'  => 'bg-purple-500',
    ];
    $dot = $dots[$status] ?? 'bg-gray-400';
    return "<span class=\"inline-block w-2 h-2 rounded-full {$dot} mr-1.5\"></span>";
}

// ─── Pagination ───────────────────────────────────────────────────────────────

/**
 * Returns pagination metadata for a dataset.
 * @return array{total:int, per_page:int, current_page:int, total_pages:int, offset:int}
 */
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => ($currentPage - 1) * $perPage,
    ];
}

/**
 * Renders pagination controls (returns HTML string).
 */
function render_pagination(array $p, string $queryString = ''): string
{
    if ($p['total_pages'] <= 1) return '';
    $qs  = $queryString ? "&{$queryString}" : '';
    $out = '<div class="flex items-center gap-1">';

    $prev = $p['current_page'] - 1;
    $next = $p['current_page'] + 1;

    $out .= $p['current_page'] > 1
        ? "<a href=\"?page={$prev}{$qs}\" class=\"px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-600\">&laquo;</a>"
        : "<span class=\"px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed\">&laquo;</span>";

    for ($i = 1; $i <= $p['total_pages']; $i++) {
        $active = ($i === $p['current_page'])
            ? 'bg-brand-600 text-white border-brand-600'
            : 'border-gray-300 hover:bg-gray-50 text-gray-600';
        $out .= "<a href=\"?page={$i}{$qs}\" class=\"px-3 py-1.5 text-sm rounded-lg border {$active}\">{$i}</a>";
    }

    $out .= $p['current_page'] < $p['total_pages']
        ? "<a href=\"?page={$next}{$qs}\" class=\"px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-600\">&raquo;</a>"
        : "<span class=\"px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed\">&raquo;</span>";

    $out .= '</div>';
    return $out;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Renders a hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(get_csrf_token()) . '">';
}

/**
 * Validates CSRF token from POST; sends 403 JSON on failure.
 */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
        exit;
    }
}

// ─── JSON Response Helper ─────────────────────────────────────────────────────

/**
 * Sends a JSON response and exits.
 */
function json_res(bool $success, string $message = '', array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ─── Rating Helpers ───────────────────────────────────────────────────────────

/**
 * Classifies an average rating into Positive / Neutral / Negative.
 */
function sentiment_label(float $avg): string
{
    if ($avg >= 4.0) return 'Positive';
    if ($avg >= 3.0) return 'Neutral';
    return 'Negative';
}

function sentiment_class(float $avg): string
{
    if ($avg >= 4.0) return 'text-emerald-600';
    if ($avg >= 3.0) return 'text-yellow-600';
    return 'text-red-600';
}

/**
 * Renders a 1–5 star rating display.
 */
function star_rating(float $avg): string
{
    $out = '<span class="flex items-center gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $avg >= $i ? '#f59e0b' : '#e5e7eb';
        $out .= "<svg width='14' height='14' viewBox='0 0 20 20' fill='{$filled}'><path d='M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z'/></svg>";
    }
    return $out . '</span>';
}
