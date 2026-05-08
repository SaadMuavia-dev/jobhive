<?php
// ============================================================
//  JobHive — Applications API
//  api/apply.php
//
//  POST /api/apply.php                 – submit application
//  GET  /api/apply.php                 – list current user's applications
//  GET  /api/apply.php?id=N            – get single application detail
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method === 'GET') {
    $id ? getApplication($userId, $id) : listApplications($userId);
} elseif ($method === 'POST') {
    submitApplication($userId);
} else {
    jsonError('Method not allowed.', 405);
}

// ── Submit application ───────────────────────────────────────
function submitApplication(int $userId): void {
    $d  = input();
    $db = getDB();

    $jobId     = isset($d['job_id']) ? (int) $d['job_id'] : null;
    $jobManual = trim($d['job_title'] ?? '');  // freestyle title from modal

    if (!$jobId && $jobManual === '') {
        jsonError('Provide job_id or job_title.');
    }

    // Prevent duplicate application to same job
    if ($jobId) {
        $dup = $db->prepare(
            'SELECT id FROM applications WHERE user_id=? AND job_id=?'
        );
        $dup->execute([$userId, $jobId]);
        if ($dup->fetch()) {
            jsonError('You have already submitted an application for this position.', 409);
        }
    }

    // Pull user defaults for city/country if not supplied
    $user = $db->prepare('SELECT city, country FROM users WHERE id=?');
    $user->execute([$userId]);
    $u = $user->fetch() ?: [];

    $stmt = $db->prepare(
        'INSERT INTO applications
           (user_id, job_id, job_title_manual, degree, experience_years,
            age, gender, city, country, cover_letter)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId,
        $jobId,
        $jobManual ?: null,
        trim($d['degree']      ?? ''),
        isset($d['experience']) ? (int) $d['experience'] : null,
        isset($d['age'])        ? (int) $d['age']        : null,
        $d['gender']            ?? null,
        trim($d['city']         ?? $u['city']    ?? ''),
        trim($d['country']      ?? $u['country'] ?? ''),
        trim($d['cover_letter'] ?? ''),
    ]);

    jsonSuccess([
        'application_id' => (int) $db->lastInsertId(),
        'message'        => 'Application submitted successfully.',
    ], 201);
}

// ── List my applications ─────────────────────────────────────
function listApplications(int $userId): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT a.id, a.job_id,
                COALESCE(j.title, a.job_title_manual) AS job_title,
                COALESCE(j.company, \'\') AS company,
                a.status, a.applied_at
         FROM applications a
         LEFT JOIN jobs j ON j.id = a.job_id
         WHERE a.user_id = ?
         ORDER BY a.applied_at DESC'
    );
    $stmt->execute([$userId]);
    jsonSuccess(['applications' => $stmt->fetchAll()]);
}

// ── Single application detail ────────────────────────────────
function getApplication(int $userId, int $id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT a.*,
                COALESCE(j.title, a.job_title_manual) AS job_title,
                j.company, j.location, j.job_type, j.salary_min, j.salary_max
         FROM applications a
         LEFT JOIN jobs j ON j.id = a.job_id
         WHERE a.id = ? AND a.user_id = ?'
    );
    $stmt->execute([$id, $userId]);
    $app = $stmt->fetch();
    if (!$app) jsonError('Application not found.', 404);
    jsonSuccess(['application' => $app]);
}
