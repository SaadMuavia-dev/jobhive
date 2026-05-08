<?php
// ============================================================
//  JobHive — Admin API
//  api/admin.php
//
//  GET  ?action=stats            – dashboard stats
//  GET  ?action=jobs             – all jobs list
//  POST ?action=add_job          – new job post karna
//  POST ?action=toggle_job       – job active/inactive
//  POST ?action=delete_job       – job delete
//  GET  ?action=applications     – all applications
//  GET  ?action=application&id=N – single application detail
//  POST ?action=update_status    – application status update
//  POST ?action=reply            – applicant ko reply
//  GET  ?action=messages         – contact messages
//  POST ?action=mark_read        – message read mark
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

// Admin check
function requireAdmin(): int {
    $id = currentUserId();
    if ($id === null) jsonError('Authentication required.', 401);
    if (($_SESSION['role'] ?? '') !== 'admin') jsonError('Admin access only.', 403);
    return $id;
}

$action = $_GET['action'] ?? '';
requireAdmin();

match($action) {
    'stats'          => getStats(),
    'jobs'           => getJobs(),
    'add_job'        => addJob(),
    'toggle_job'     => toggleJob(),
    'delete_job'     => deleteJob(),
    'applications'   => getApplications(),
    'application'    => getSingleApplication(),
    'update_status'  => updateStatus(),
    'reply'          => replyToApplicant(),
    'messages'       => getMessages(),
    'mark_read'      => markRead(),
    default          => jsonError('Unknown action.', 404),
};

// ── Dashboard Stats ──────────────────────────────────────────
function getStats(): void {
    $db = getDB();
    $stats = [];

    $stats['total_jobs']     = $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $stats['total_users']    = $db->query('SELECT COUNT(*) FROM users WHERE role != "admin"')->fetchColumn();
    $stats['total_apps']     = $db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    $stats['pending_apps']   = $db->query('SELECT COUNT(*) FROM applications WHERE status="pending"')->fetchColumn();
    $stats['unread_msgs']    = $db->query('SELECT COUNT(*) FROM contact_messages WHERE is_read=0')->fetchColumn();

    jsonSuccess(['stats' => $stats]);
}

// ── All Jobs ─────────────────────────────────────────────────
function getJobs(): void {
    $db = getDB();
    $stmt = $db->query(
        'SELECT j.*, 
                (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) as app_count
         FROM jobs j 
         ORDER BY j.created_at DESC'
    );
    jsonSuccess(['jobs' => $stmt->fetchAll()]);
}

// ── New Job Add ───────────────────────────────────────────────
function addJob(): void {
    $d = input();
    required($d, ['title', 'company', 'job_type']);

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO jobs 
           (title, company, company_logo, location, is_remote, job_type,
            salary_min, salary_max, salary_currency, description, requirements, 
            category, is_featured, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
    );
    $stmt->execute([
        trim($d['title']),
        trim($d['company']),
        trim($d['company_logo']    ?? ''),
        trim($d['location']        ?? ''),
        isset($d['is_remote']) && $d['is_remote'] ? 1 : 0,
        $d['job_type'],
        isset($d['salary_min']) && $d['salary_min'] !== '' ? (float)$d['salary_min'] : null,
        isset($d['salary_max']) && $d['salary_max'] !== '' ? (float)$d['salary_max'] : null,
        $d['salary_currency'] ?? 'USD',
        trim($d['description']     ?? ''),
        trim($d['requirements']    ?? ''),
        trim($d['category']        ?? ''),
        isset($d['is_featured']) && $d['is_featured'] ? 1 : 0,
    ]);

    jsonSuccess(['job_id' => (int)$db->lastInsertId(), 'message' => 'Job posted successfully!'], 201);
}

// ── Toggle Job Active/Inactive ───────────────────────────────
function toggleJob(): void {
    $d  = input();
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonError('Job ID required.');

    $db = getDB();
    $db->prepare('UPDATE jobs SET is_active = NOT is_active WHERE id=?')->execute([$id]);
    jsonSuccess(['message' => 'Job status updated.']);
}

// ── Delete Job ───────────────────────────────────────────────
function deleteJob(): void {
    $d  = input();
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonError('Job ID required.');

    $db = getDB();
    $db->prepare('DELETE FROM jobs WHERE id=?')->execute([$id]);
    jsonSuccess(['message' => 'Job deleted.']);
}

// ── All Applications ─────────────────────────────────────────
function getApplications(): void {
    $db     = getDB();
    $status = $_GET['status'] ?? '';
    $jobId  = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

    $where  = ['1=1'];
    $params = [];

    if ($status) {
        $where[]  = 'a.status = ?';
        $params[] = $status;
    }
    if ($jobId) {
        $where[]  = 'a.job_id = ?';
        $params[] = $jobId;
    }

    $sql = 'SELECT a.id, a.status, a.applied_at, a.degree, a.experience_years,
                   a.age, a.gender, a.city, a.country,
                   COALESCE(j.title, a.job_title_manual) AS job_title,
                   j.company,
                   u.name AS applicant_name, u.email AS applicant_email
            FROM applications a
            LEFT JOIN jobs  j ON j.id = a.job_id
            LEFT JOIN users u ON u.id = a.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.applied_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess(['applications' => $stmt->fetchAll()]);
}

// ── Single Application Detail ─────────────────────────────────
function getSingleApplication(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) jsonError('Application ID required.');

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT a.*,
                COALESCE(j.title, a.job_title_manual) AS job_title,
                j.company, j.location, j.job_type, j.salary_min, j.salary_max,
                u.name AS applicant_name, u.email AS applicant_email,
                u.phone AS applicant_phone, u.city AS user_city, u.country AS user_country
         FROM applications a
         LEFT JOIN jobs  j ON j.id = a.job_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) jsonError('Application not found.', 404);
    jsonSuccess(['application' => $app]);
}

// ── Update Application Status ────────────────────────────────
function updateStatus(): void {
    $d      = input();
    $id     = (int)($d['id'] ?? 0);
    $status = $d['status'] ?? '';

    $allowed = ['pending','reviewed','shortlisted','rejected','hired'];
    if (!$id || !in_array($status, $allowed)) jsonError('Invalid data.');

    $db = getDB();
    $db->prepare('UPDATE applications SET status=? WHERE id=?')->execute([$status, $id]);
    jsonSuccess(['message' => 'Status updated.']);
}

// ── Reply to Applicant ────────────────────────────────────────
// Note: Yeh admin_replies table use karta hai
// Agar table nahi hai to SQL schema mein add karo
function replyToApplicant(): void {
    $d      = input();
    $appId  = (int)($d['application_id'] ?? 0);
    $msg    = trim($d['message'] ?? '');

    if (!$appId || $msg === '') jsonError('Application ID and message required.');

    $db = getDB();

    // Application exist check
    $stmt = $db->prepare('SELECT a.id, u.email, u.name, COALESCE(j.title, a.job_title_manual) AS job_title 
                          FROM applications a 
                          LEFT JOIN users u ON u.id=a.user_id 
                          LEFT JOIN jobs j ON j.id=a.job_id
                          WHERE a.id=?');
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
    if (!$app) jsonError('Application not found.', 404);

    // Reply save karo
    try {
        $db->prepare(
            'INSERT INTO admin_replies (application_id, message) VALUES (?,?)'
        )->execute([$appId, $msg]);
    } catch (\PDOException $e) {
        // Table nahi hai to create karo
        $db->exec('CREATE TABLE IF NOT EXISTS admin_replies (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            message        TEXT NOT NULL,
            sent_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app (application_id)
        ) ENGINE=InnoDB');
        $db->prepare(
            'INSERT INTO admin_replies (application_id, message) VALUES (?,?)'
        )->execute([$appId, $msg]);
    }

    jsonSuccess([
        'message'   => 'Reply saved.',
        'to_email'  => $app['email'],
        'to_name'   => $app['name'],
        'job_title' => $app['job_title'],
    ]);
}

// ── Contact Messages ─────────────────────────────────────────
function getMessages(): void {
    $db = getDB();
    $stmt = $db->query(
        'SELECT id, name, email, subject, message, is_read, sent_at
         FROM contact_messages
         ORDER BY sent_at DESC'
    );
    jsonSuccess(['messages' => $stmt->fetchAll()]);
}

// ── Mark Message Read ─────────────────────────────────────────
function markRead(): void {
    $d  = input();
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonError('Message ID required.');

    $db = getDB();
    $db->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
    jsonSuccess(['message' => 'Marked as read.']);
}
