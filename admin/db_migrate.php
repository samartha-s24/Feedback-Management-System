<?php
/**
 * AFMS Database Migration Runner
 * =====================================================================
 * Idempotent: safe to include on every request.
 * Uses information_schema checks before any ALTER TABLE.
 * Runs only once per PHP session via $_SESSION['afms_migrated'].
 * =====================================================================
 */
declare(strict_types=1);

function column_exists(mysqli $db, string $table, string $col): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count > 0;
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count > 0;
}

function run_migrations(): void
{
    if (!empty($_SESSION['afms_migrated'])) {
        return;
    }

    $db = get_db();
    $db->query("SET FOREIGN_KEY_CHECKS = 0");

    // ── 1. Extend feedback_sessions ──────────────────────────────────
    $session_cols = [
        'status'            => "ALTER TABLE feedback_sessions ADD COLUMN status ENUM('Draft','Published','Active','Closed','Archived') NOT NULL DEFAULT 'Draft'",
        'academic_year'     => "ALTER TABLE feedback_sessions ADD COLUMN academic_year VARCHAR(9) NULL",
        'semester'          => "ALTER TABLE feedback_sessions ADD COLUMN semester TINYINT UNSIGNED NULL",
        'start_time'        => "ALTER TABLE feedback_sessions ADD COLUMN start_time TIME NULL",
        'end_time'          => "ALTER TABLE feedback_sessions ADD COLUMN end_time TIME NULL",
        'instructions'      => "ALTER TABLE feedback_sessions ADD COLUMN instructions TEXT NULL",
        'max_attempts'      => "ALTER TABLE feedback_sessions ADD COLUMN max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1",
        'result_visibility' => "ALTER TABLE feedback_sessions ADD COLUMN result_visibility ENUM('Private','Published','Archived') NOT NULL DEFAULT 'Private'",
        'published_at'      => "ALTER TABLE feedback_sessions ADD COLUMN published_at TIMESTAMP NULL",
        'archived_at'       => "ALTER TABLE feedback_sessions ADD COLUMN archived_at TIMESTAMP NULL",
        'published_by'      => "ALTER TABLE feedback_sessions ADD COLUMN published_by INT UNSIGNED NULL",
        'archived_by'       => "ALTER TABLE feedback_sessions ADD COLUMN archived_by INT UNSIGNED NULL",
    ];
    foreach ($session_cols as $col => $sql) {
        if (!column_exists($db, 'feedback_sessions', $col)) {
            $db->query($sql);
        }
    }

    // ── 2. session_target_roles ───────────────────────────────────────
    if (!table_exists($db, 'session_target_roles')) {
        $db->query("CREATE TABLE session_target_roles (
            role_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id  INT UNSIGNED NOT NULL,
            target_role ENUM('Student','Faculty','Parent','Alumni','Employee') NOT NULL,
            CONSTRAINT fk_str_session
                FOREIGN KEY (session_id) REFERENCES feedback_sessions(session_id)
                ON DELETE CASCADE,
            UNIQUE KEY uq_session_role (session_id, target_role),
            INDEX idx_str_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 3. questionnaires ─────────────────────────────────────────────
    if (!table_exists($db, 'questionnaires')) {
        $db->query("CREATE TABLE questionnaires (
            questionnaire_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title       VARCHAR(200) NOT NULL,
            description TEXT NULL,
            session_id  INT UNSIGNED NOT NULL,
            status      ENUM('Draft','Active','Closed') NOT NULL DEFAULT 'Draft',
            created_by  INT UNSIGNED NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_q_session FOREIGN KEY (session_id) REFERENCES feedback_sessions(session_id) ON DELETE CASCADE,
            CONSTRAINT fk_q_creator FOREIGN KEY (created_by) REFERENCES users(user_id),
            INDEX idx_q_session (session_id),
            INDEX idx_q_status  (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 4. question_bank ──────────────────────────────────────────────
    if (!table_exists($db, 'question_bank')) {
        $db->query("CREATE TABLE question_bank (
            question_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_text TEXT NOT NULL,
            question_type ENUM('rating','mcq') NOT NULL DEFAULT 'rating',
            category      VARCHAR(100) NULL,
            is_active     BOOLEAN NOT NULL DEFAULT TRUE,
            created_by    INT UNSIGNED NOT NULL,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_qb_creator FOREIGN KEY (created_by) REFERENCES users(user_id),
            INDEX idx_qb_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 5. questionnaire_questions ────────────────────────────────────
    if (!table_exists($db, 'questionnaire_questions')) {
        $db->query("CREATE TABLE questionnaire_questions (
            qq_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            questionnaire_id INT UNSIGNED NOT NULL,
            question_id      INT UNSIGNED NOT NULL,
            display_order    TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_required      BOOLEAN NOT NULL DEFAULT TRUE,
            CONSTRAINT fk_qq_questionnaire FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(questionnaire_id) ON DELETE CASCADE,
            CONSTRAINT fk_qq_question      FOREIGN KEY (question_id)      REFERENCES question_bank(question_id) ON DELETE CASCADE,
            UNIQUE KEY uq_qq (questionnaire_id, question_id),
            INDEX idx_qq_q (questionnaire_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 6. submission_tokens (duplicate prevention — stores user identity ONLY) ─
    if (!table_exists($db, 'submission_tokens')) {
        $db->query("CREATE TABLE submission_tokens (
            token_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      INT UNSIGNED NOT NULL,
            session_id   INT UNSIGNED NOT NULL,
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_st_user    FOREIGN KEY (user_id)    REFERENCES users(user_id) ON DELETE CASCADE,
            CONSTRAINT fk_st_session FOREIGN KEY (session_id) REFERENCES feedback_sessions(session_id) ON DELETE CASCADE,
            UNIQUE KEY uq_st_user_session (user_id, session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Used ONLY for duplicate-submission prevention. Never joined to feedback_submissions.'");
    }

    // ── 7. feedback_submissions (anonymous — no user_id stored) ──────
    if (!table_exists($db, 'feedback_submissions')) {
        $db->query("CREATE TABLE feedback_submissions (
            submission_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            questionnaire_id INT UNSIGNED NOT NULL,
            session_id       INT UNSIGNED NOT NULL,
            submission_hash  VARCHAR(64) NOT NULL COMMENT 'SHA-256 salt hash — never linked to a user_id',
            department_id    INT UNSIGNED NULL,
            submitted_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_fsub_questionnaire FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(questionnaire_id) ON DELETE CASCADE,
            CONSTRAINT fk_fsub_session       FOREIGN KEY (session_id)       REFERENCES feedback_sessions(session_id) ON DELETE CASCADE,
            CONSTRAINT fk_fsub_department    FOREIGN KEY (department_id)    REFERENCES departments(department_id) ON DELETE SET NULL,
            INDEX idx_fsub_session (session_id),
            INDEX idx_fsub_q      (questionnaire_id),
            INDEX idx_fsub_dept   (department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        if (!column_exists($db, 'feedback_submissions', 'department_id')) {
            $db->query("ALTER TABLE feedback_submissions ADD COLUMN department_id INT UNSIGNED NULL");
            $db->query("ALTER TABLE feedback_submissions ADD CONSTRAINT fk_fsub_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL");
            $db->query("CREATE INDEX idx_fsub_dept ON feedback_submissions(department_id)");
        }
    }

    // ── 8. submission_responses ───────────────────────────────────────
    if (!table_exists($db, 'submission_responses')) {
        $db->query("CREATE TABLE submission_responses (
            response_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id INT UNSIGNED NOT NULL,
            question_id   INT UNSIGNED NOT NULL,
            rating_value  TINYINT UNSIGNED NOT NULL,
            CONSTRAINT fk_sr_submission FOREIGN KEY (submission_id) REFERENCES feedback_submissions(submission_id) ON DELETE CASCADE,
            CONSTRAINT fk_sr_question   FOREIGN KEY (question_id)   REFERENCES question_bank(question_id),
            CHECK (rating_value BETWEEN 1 AND 5),
            INDEX idx_sr_sub (submission_id),
            INDEX idx_sr_q   (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 9. submission_comments ────────────────────────────────────────
    if (!table_exists($db, 'submission_comments')) {
        $db->query("CREATE TABLE submission_comments (
            comment_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id INT UNSIGNED NOT NULL,
            comment_text  TEXT NOT NULL,
            is_hidden     BOOLEAN NOT NULL DEFAULT FALSE,
            hidden_by     INT UNSIGNED NULL,
            hidden_at     TIMESTAMP NULL,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sc_submission FOREIGN KEY (submission_id) REFERENCES feedback_submissions(submission_id) ON DELETE CASCADE,
            CONSTRAINT fk_sc_moderator  FOREIGN KEY (hidden_by)     REFERENCES users(user_id) ON DELETE SET NULL,
            INDEX idx_sc_sub (submission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 10. announcements ─────────────────────────────────────────────
    if (!table_exists($db, 'announcements')) {
        $db->query("CREATE TABLE announcements (
            announcement_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title             VARCHAR(200) NOT NULL,
            description       TEXT NULL,
            priority          ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
            linked_session_id INT UNSIGNED NULL,
            status            ENUM('Draft','Published','Expired') NOT NULL DEFAULT 'Draft',
            start_date        DATE NULL,
            end_date          DATE NULL,
            created_by        INT UNSIGNED NOT NULL,
            created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ann_session FOREIGN KEY (linked_session_id) REFERENCES feedback_sessions(session_id) ON DELETE SET NULL,
            CONSTRAINT fk_ann_creator FOREIGN KEY (created_by)        REFERENCES users(user_id),
            INDEX idx_ann_status (status),
            INDEX idx_ann_dates  (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 11. announcement_audience ─────────────────────────────────────
    if (!table_exists($db, 'announcement_audience')) {
        $db->query("CREATE TABLE announcement_audience (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            announcement_id INT UNSIGNED NOT NULL,
            target_role     ENUM('Student','Faculty','Parent','Alumni','Employee','All') NOT NULL,
            CONSTRAINT fk_aa_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
            UNIQUE KEY uq_aa (announcement_id, target_role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 12. notifications ─────────────────────────────────────────────
    if (!table_exists($db, 'notifications')) {
        $db->query("CREATE TABLE notifications (
            notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED NOT NULL,
            type            ENUM('Info','Reminder','Warning','Success') NOT NULL DEFAULT 'Info',
            title           VARCHAR(200) NOT NULL,
            body            TEXT NULL,
            is_read         BOOLEAN NOT NULL DEFAULT FALSE,
            created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_notif_user  (user_id),
            INDEX idx_notif_unread (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 13. audit_logs ────────────────────────────────────────────────
    if (!table_exists($db, 'audit_logs')) {
        $db->query("CREATE TABLE audit_logs (
            log_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id   INT UNSIGNED NOT NULL,
            action     VARCHAR(200) NOT NULL,
            table_name VARCHAR(100) NULL,
            record_id  INT UNSIGNED NULL,
            details    TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_al_admin FOREIGN KEY (admin_id) REFERENCES users(user_id),
            INDEX idx_al_admin   (admin_id),
            INDEX idx_al_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── 14. system_settings ───────────────────────────────────────────
    if (!table_exists($db, 'system_settings')) {
        $db->query("CREATE TABLE system_settings (
            setting_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key   VARCHAR(100) NOT NULL,
            setting_value TEXT NULL,
            updated_by    INT UNSIGNED NULL,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ss_updater FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
            ('institution_name', 'Autonomous College'),
            ('institution_logo', ''),
            ('app_name', 'Autonomous Feedback Management System'),
            ('academic_year', '2025-2026'),
            ('current_semester', '1'),
            ('default_scale', '5'),
            ('session_timeout', '1800'),
            ('timezone', 'Asia/Kolkata'),
            ('theme', 'light')
        ");
    }

    $db->query("SET FOREIGN_KEY_CHECKS = 1");

    // Auto-transition session statuses
    _auto_update_session_statuses($db);

    $_SESSION['afms_migrated'] = true;
}

/**
 * Automatically transitions session statuses based on wall clock time.
 *   Published  → Active  when start_date/start_time reached
 *   Active     → Closed  when end_date/end_time passed
 * Also auto-expires announcements past end_date.
 */
function _auto_update_session_statuses(mysqli $db): void
{
    $now_date = $db->real_escape_string(date('Y-m-d'));
    $now_time = $db->real_escape_string(date('H:i:s'));

    $db->query("UPDATE feedback_sessions
                SET status = 'Active', is_active = 1
                WHERE status = 'Published'
                  AND (start_date < '$now_date'
                       OR (start_date = '$now_date'
                           AND (start_time IS NULL OR start_time <= '$now_time')))");

    $db->query("UPDATE feedback_sessions
                SET status = 'Closed', is_active = 0
                WHERE status = 'Active'
                  AND (end_date < '$now_date'
                       OR (end_date = '$now_date'
                           AND end_time IS NOT NULL AND end_time <= '$now_time'))");

    $db->query("UPDATE announcements
                SET status = 'Expired'
                WHERE status = 'Published' AND end_date < '$now_date'");
}
