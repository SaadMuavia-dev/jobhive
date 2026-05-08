<?php
// ============================================================
//  JobHive — Auth API
//  api/auth.php
//
//  POST /api/auth.php?action=register
//  POST /api/auth.php?action=login
//  POST /api/auth.php?action=logout
//  GET  /api/auth.php?action=me
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

$action = $_GET['action'] ?? '';

match($action) {
    'register' => register(),
    'login'    => login(),
    'logout'   => logout(),
    'me'       => me(),
    default    => jsonError('Unknown action.', 404),
};

// ── Register ────────────────────────────────────────────────
function register(): void {
    $d = input();
    required($d, ['name', 'email', 'password']);

    $name     = trim($d['name']);
    $email    = strtolower(trim($d['email']));
    $password = $d['password'];
    $city     = trim($d['city']    ?? '');
    $country  = trim($d['country'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email address.');
    }
    if (strlen($password) < 6) {
        jsonError('Password must be at least 6 characters.');
    }

    $db = getDB();

    // Duplicate check
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonError('Email already registered.', 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, city, country)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash, $city, $country]);
    $userId = (int) $db->lastInsertId();

    jsonSuccess(['message' => 'Account created. Please log in.', 'user_id' => $userId], 201);
}

// ── Login ───────────────────────────────────────────────────
function login(): void {
    $d = input();
    required($d, ['email', 'password']);

    $email    = strtolower(trim($d['email']));
    $password = $d['password'];

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id, name, email, password_hash, city, country, role
         FROM users WHERE email = ? AND is_active = 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Invalid email or password.', 401);
    }

    // Start session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];

    unset($user['password_hash']);
    jsonSuccess(['user' => $user]);
}

// ── Logout ──────────────────────────────────────────────────
function logout(): void {
    $_SESSION = [];
    session_destroy();
    jsonSuccess(['message' => 'Logged out.']);
}

// ── Current user ────────────────────────────────────────────
function me(): void {
    $userId = requireAuth();
    $db     = getDB();

    $stmt = $db->prepare(
        'SELECT id, name, email, city, country, phone, avatar_url, role, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) jsonError('User not found.', 404);

    jsonSuccess(['user' => $user]);
}
