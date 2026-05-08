<?php
// ============================================================
//  JobHive — Shared Helpers
//  includes/helpers.php
// ============================================================

// ── Security headers ────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Allow the frontend origin (adjust for production)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Response helpers ────────────────────────────────────────
function jsonSuccess(array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ── Input helper ────────────────────────────────────────────
function input(): array {
    $body = file_get_contents('php://input');
    $json = json_decode($body, true);
    if (is_array($json)) return $json;
    return $_POST ?: [];
}

function required(array $data, array $fields): void {
    foreach ($fields as $f) {
        if (empty(trim($data[$f] ?? ''))) {
            jsonError("Field '$f' is required.");
        }
    }
}

// ── Session-based auth ───────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function requireAuth(): int {
    $id = currentUserId();
    if ($id === null) jsonError('Authentication required.', 401);
    return $id;
}
