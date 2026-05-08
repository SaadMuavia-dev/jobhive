<?php
// ============================================================
//  JobHive — Profile API
//  api/profile.php
//
//  GET    /api/profile.php                      – get full profile
//  POST   /api/profile.php?section=basic        – update name/city/country/phone
//
//  POST   /api/profile.php?section=education    – add education entry
//  PUT    /api/profile.php?section=education&id=N – update
//  DELETE /api/profile.php?section=education&id=N – delete
//
//  POST   /api/profile.php?section=experience   – add experience entry
//  PUT    /api/profile.php?section=experience&id=N – update
//  DELETE /api/profile.php?section=experience&id=N – delete
//
//  POST   /api/profile.php?section=skills       – replace all skills
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

$userId  = requireAuth();
$method  = $_SERVER['REQUEST_METHOD'];
$section = $_GET['section'] ?? '';
$id      = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET full profile ─────────────────────────────────────────
if ($method === 'GET' && $section === '') {
    $db = getDB();

    $user = $db->prepare(
        'SELECT id, name, email, city, country, phone, avatar_url, created_at
         FROM users WHERE id = ?'
    );
    $user->execute([$userId]);
    $profile = $user->fetch();
    if (!$profile) jsonError('User not found.', 404);

    $edu = $db->prepare(
        'SELECT * FROM user_education WHERE user_id = ? ORDER BY end_year DESC, start_year DESC'
    );
    $edu->execute([$userId]);
    $profile['education'] = $edu->fetchAll();

    $exp = $db->prepare(
        'SELECT * FROM user_experience WHERE user_id = ? ORDER BY is_current DESC, start_date DESC'
    );
    $exp->execute([$userId]);
    $profile['experience'] = $exp->fetchAll();

    $skills = $db->prepare(
        'SELECT s.id, s.name, us.level
         FROM user_skills us JOIN skills s ON s.id = us.skill_id
         WHERE us.user_id = ?'
    );
    $skills->execute([$userId]);
    $profile['skills'] = $skills->fetchAll();

    jsonSuccess(['profile' => $profile]);
}

// ── POST / PUT / DELETE routing ──────────────────────────────
$db = getDB();
$d  = input();

if ($section === 'basic' && $method === 'POST') {
    updateBasic($db, $userId, $d);
} elseif ($section === 'education') {
    match($method) {
        'POST'   => addEducation($db, $userId, $d),
        'PUT'    => updateEducation($db, $userId, $id, $d),
        'DELETE' => deleteRow($db, 'user_education', $id, $userId),
        default  => jsonError('Method not allowed.', 405),
    };
} elseif ($section === 'experience') {
    match($method) {
        'POST'   => addExperience($db, $userId, $d),
        'PUT'    => updateExperience($db, $userId, $id, $d),
        'DELETE' => deleteRow($db, 'user_experience', $id, $userId),
        default  => jsonError('Method not allowed.', 405),
    };
} elseif ($section === 'skills' && $method === 'POST') {
    updateSkills($db, $userId, $d);
} else {
    jsonError('Unknown section or method.', 404);
}

// ── Handlers ─────────────────────────────────────────────────

function updateBasic(PDO $db, int $userId, array $d): void {
    $stmt = $db->prepare(
        'UPDATE users SET name=?, city=?, country=?, phone=? WHERE id=?'
    );
    $stmt->execute([
        trim($d['name']    ?? ''),
        trim($d['city']    ?? ''),
        trim($d['country'] ?? ''),
        trim($d['phone']   ?? ''),
        $userId,
    ]);
    jsonSuccess(['message' => 'Profile updated.']);
}

function addEducation(PDO $db, int $userId, array $d): void {
    required($d, ['degree', 'institution']);
    $stmt = $db->prepare(
        'INSERT INTO user_education
           (user_id, degree, institution, field, start_year, end_year, is_current, grade, description)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId,
        trim($d['degree']),
        trim($d['institution']),
        trim($d['field']       ?? ''),
        $d['start_year']       ?? null,
        $d['end_year']         ?? null,
        (int)($d['is_current'] ?? 0),
        trim($d['grade']       ?? ''),
        trim($d['description'] ?? ''),
    ]);
    jsonSuccess(['id' => (int) $db->lastInsertId(), 'message' => 'Education added.'], 201);
}

function updateEducation(PDO $db, int $userId, ?int $id, array $d): void {
    if (!$id) jsonError('Missing id.');
    required($d, ['degree', 'institution']);
    $stmt = $db->prepare(
        'UPDATE user_education
         SET degree=?, institution=?, field=?, start_year=?, end_year=?,
             is_current=?, grade=?, description=?
         WHERE id=? AND user_id=?'
    );
    $stmt->execute([
        trim($d['degree']),
        trim($d['institution']),
        trim($d['field']       ?? ''),
        $d['start_year']       ?? null,
        $d['end_year']         ?? null,
        (int)($d['is_current'] ?? 0),
        trim($d['grade']       ?? ''),
        trim($d['description'] ?? ''),
        $id, $userId,
    ]);
    jsonSuccess(['message' => 'Education updated.']);
}

function addExperience(PDO $db, int $userId, array $d): void {
    required($d, ['job_title', 'company']);
    $stmt = $db->prepare(
        'INSERT INTO user_experience
           (user_id, job_title, company, location, employment_type,
            start_date, end_date, is_current, description)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId,
        trim($d['job_title']),
        trim($d['company']),
        trim($d['location']        ?? ''),
        $d['employment_type']      ?? 'full-time',
        $d['start_date']           ?? null,
        $d['end_date']             ?? null,
        (int)($d['is_current']     ?? 0),
        trim($d['description']     ?? ''),
    ]);
    jsonSuccess(['id' => (int) $db->lastInsertId(), 'message' => 'Experience added.'], 201);
}

function updateExperience(PDO $db, int $userId, ?int $id, array $d): void {
    if (!$id) jsonError('Missing id.');
    required($d, ['job_title', 'company']);
    $stmt = $db->prepare(
        'UPDATE user_experience
         SET job_title=?, company=?, location=?, employment_type=?,
             start_date=?, end_date=?, is_current=?, description=?
         WHERE id=? AND user_id=?'
    );
    $stmt->execute([
        trim($d['job_title']),
        trim($d['company']),
        trim($d['location']    ?? ''),
        $d['employment_type']  ?? 'full-time',
        $d['start_date']       ?? null,
        $d['end_date']         ?? null,
        (int)($d['is_current'] ?? 0),
        trim($d['description'] ?? ''),
        $id, $userId,
    ]);
    jsonSuccess(['message' => 'Experience updated.']);
}

function updateSkills(PDO $db, int $userId, array $d): void {
    // $d['skills'] = [['name'=>'PHP','level'=>'advanced'], ...]
    $incoming = $d['skills'] ?? [];
    if (!is_array($incoming)) jsonError('skills must be an array.');

    $db->beginTransaction();
    try {
        // Remove old skills for this user
        $db->prepare('DELETE FROM user_skills WHERE user_id = ?')->execute([$userId]);

        foreach ($incoming as $sk) {
            $name  = trim($sk['name']  ?? '');
            $level = $sk['level'] ?? 'intermediate';
            if ($name === '') continue;

            // Insert skill if new
            $db->prepare(
                'INSERT IGNORE INTO skills (name) VALUES (?)'
            )->execute([$name]);

            $skillId = $db->query(
                "SELECT id FROM skills WHERE name = " . $db->quote($name)
            )->fetchColumn();

            $db->prepare(
                'INSERT INTO user_skills (user_id, skill_id, level) VALUES (?,?,?)'
            )->execute([$userId, $skillId, $level]);
        }
        $db->commit();
        jsonSuccess(['message' => 'Skills updated.']);
    } catch (Throwable $e) {
        $db->rollBack();
        jsonError('Could not update skills: ' . $e->getMessage(), 500);
    }
}

function deleteRow(PDO $db, string $table, ?int $id, int $userId): void {
    if (!$id) jsonError('Missing id.');
    // Whitelist table names to prevent SQL injection
    $allowed = ['user_education', 'user_experience'];
    if (!in_array($table, $allowed, true)) jsonError('Invalid table.', 400);
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) jsonError('Record not found or not yours.', 404);
    jsonSuccess(['message' => 'Deleted.']);
}
