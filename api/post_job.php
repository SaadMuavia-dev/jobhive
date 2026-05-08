<?php
// ============================================================
//  JobHive — Post Job API
//  api/post_job.php
//
//  POST /api/post_job.php?action=submit   — User submits a job
//  GET  /api/post_job.php?action=pending  — Admin: get pending jobs
//  POST /api/post_job.php?action=approve  — Admin: approve a job
//  POST /api/post_job.php?action=reject   — Admin: reject a job
//  GET  /api/post_job.php?action=my_posts — User: see own submissions
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

$action = $_GET['action'] ?? '';

match($action) {
    'submit'    => submitJob(),
    'pending'   => getPendingJobs(),
    'approve'   => approveJob(),
    'reject'    => rejectJob(),
    'my_posts'  => myPosts(),
    default     => jsonError('Unknown action.', 404),
};

// ── Submit a new job (any logged-in user) ───────────────────
function submitJob(): void {
    $userId = requireAuth();
    $d      = input();
    required($d, ['title', 'company', 'job_type']);

    $title       = trim($d['title']);
    $company     = trim($d['company']);
    $location    = trim($d['location']     ?? '');
    $isRemote    = !empty($d['is_remote'])  ? 1 : 0;
    $jobType     = $d['job_type'];
    $salaryMin   = !empty($d['salary_min']) ? (int)$d['salary_min'] : null;
    $salaryMax   = !empty($d['salary_max']) ? (int)$d['salary_max'] : null;
    $category    = trim($d['category']     ?? '');
    $description = trim($d['description']  ?? '');
    $requirements= trim($d['requirements'] ?? '');

    $allowed = ['full-time','part-time','freelance','internship','contract'];
    if (!in_array($jobType, $allowed)) jsonError('Invalid job type.');

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO pending_jobs
           (user_id, title, company, location, is_remote, job_type,
            salary_min, salary_max, category, description, requirements)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId, $title, $company, $location, $isRemote, $jobType,
        $salaryMin, $salaryMax, $category, $description, $requirements,
    ]);

    jsonSuccess(['message' => 'Job submitted! Admin review ke baad publish hogi.'], 201);
}

// ── Admin: list all pending jobs ────────────────────────────
function getPendingJobs(): void {
    requireAdmin();
    $db   = getDB();
    $stmt = $db->query(
        'SELECT pj.*, u.name AS poster_name, u.email AS poster_email
         FROM pending_jobs pj
         JOIN users u ON u.id = pj.user_id
         ORDER BY pj.submitted_at DESC'
    );
    jsonSuccess(['jobs' => $stmt->fetchAll()]);
}

// ── Admin: approve a pending job ────────────────────────────
function approveJob(): void {
    requireAdmin();
    $d = input();
    required($d, ['id']);
    $id = (int)$d['id'];

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM pending_jobs WHERE id = ?');
    $stmt->execute([$id]);
    $pj   = $stmt->fetch();
    if (!$pj) jsonError('Job not found.', 404);

    // Move to live jobs table
    $ins = $db->prepare(
        'INSERT INTO jobs
           (title, company, location, is_remote, job_type,
            salary_min, salary_max, category, description, requirements,
            posted_by, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,1)'
    );
    $ins->execute([
        $pj['title'], $pj['company'], $pj['location'], $pj['is_remote'],
        $pj['job_type'], $pj['salary_min'], $pj['salary_max'],
        $pj['category'], $pj['description'], $pj['requirements'],
        $pj['user_id'],
    ]);

    // Mark as approved
    $upd = $db->prepare(
        'UPDATE pending_jobs SET status=\'approved\', reviewed_at=NOW() WHERE id=?'
    );
    $upd->execute([$id]);

    jsonSuccess(['message' => 'Job approved and published!']);
}

// ── Admin: reject a pending job ─────────────────────────────
function rejectJob(): void {
    requireAdmin();
    $d = input();
    required($d, ['id']);
    $id   = (int)$d['id'];
    $note = trim($d['note'] ?? '');

    $db  = getDB();
    $upd = $db->prepare(
        'UPDATE pending_jobs SET status=\'rejected\', admin_note=?, reviewed_at=NOW() WHERE id=?'
    );
    $upd->execute([$note, $id]);

    jsonSuccess(['message' => 'Job rejected.']);
}

// ── User: see own submitted jobs ────────────────────────────
function myPosts(): void {
    $userId = requireAuth();
    $db     = getDB();
    $stmt   = $db->prepare(
        'SELECT id, title, company, job_type, status, admin_note, submitted_at
         FROM pending_jobs WHERE user_id = ? ORDER BY submitted_at DESC'
    );
    $stmt->execute([$userId]);
    jsonSuccess(['posts' => $stmt->fetchAll()]);
}

// ── Helper: require admin role ───────────────────────────────
function requireAdmin(): void {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        jsonError('Admin access required.', 403);
    }
}
