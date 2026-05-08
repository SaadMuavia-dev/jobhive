<?php
// ============================================================
//  JobHive — Jobs API  (read-only, public)
//  api/jobs.php
//
//  GET /api/jobs.php                  – list / search jobs
//  GET /api/jobs.php?id=N             – single job detail
//
//  Query params for listing:
//    q         – full-text keyword
//    type      – full-time | part-time | freelance | internship | contract
//    remote    – 1
//    category  – Development, Design, …
//    page      – default 1
//    per_page  – default 12 (max 50)
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed.', 405);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$id ? getSingleJob($id) : listJobs();

// ── Single job ───────────────────────────────────────────────
function getSingleJob(int $id): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id, title, company, company_logo, location, is_remote,
                job_type, salary_min, salary_max, salary_currency,
                description, requirements, category, is_featured, created_at
         FROM jobs WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([$id]);
    $job = $stmt->fetch();
    if (!$job) jsonError('Job not found.', 404);
    jsonSuccess(['job' => $job]);
}

// ── List / search jobs ────────────────────────────────────────
function listJobs(): void {
    $db = getDB();

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 12)));
    $offset  = ($page - 1) * $perPage;

    $where  = ['j.is_active = 1'];
    $params = [];

    if (!empty($_GET['q'])) {
        $where[]  = 'MATCH(j.title, j.company, j.description, j.requirements)
                     AGAINST(? IN BOOLEAN MODE)';
        $params[] = trim($_GET['q']) . '*';
    }
    if (!empty($_GET['type'])) {
        $where[]  = 'j.job_type = ?';
        $params[] = $_GET['type'];
    }
    if (!empty($_GET['remote'])) {
        $where[]  = 'j.is_remote = 1';
    }
    if (!empty($_GET['category'])) {
        $where[]  = 'j.category = ?';
        $params[] = $_GET['category'];
    }

    $whereSQL = implode(' AND ', $where);

    // Total count
    $cnt = $db->prepare("SELECT COUNT(*) FROM jobs j WHERE $whereSQL");
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();

    // Page of results
    $sql = "SELECT j.id, j.title, j.company, j.company_logo, j.location,
                   j.is_remote, j.job_type, j.salary_min, j.salary_max,
                   j.salary_currency, j.category, j.is_featured, j.created_at
            FROM jobs j
            WHERE $whereSQL
            ORDER BY j.is_featured DESC, j.created_at DESC
            LIMIT $perPage OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonSuccess([
        'jobs'       => $stmt->fetchAll(),
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'last_page'  => (int) ceil($total / $perPage),
    ]);
}
