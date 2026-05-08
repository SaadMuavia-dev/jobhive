<?php
// ============================================================
//  JobHive — Contact API
//  api/contact.php
//
//  POST /api/contact.php  – save a contact message
//    Body: name, email, subject (opt), message
//    Works for both logged-in and guest users.
// ============================================================

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes');
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'helpers.php';
require_once BASE_PATH . DIRECTORY_SEPARATOR . 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed.', 405);

$d = input();
required($d, ['name', 'email', 'message']);

$name    = trim($d['name']);
$email   = strtolower(trim($d['email']));
$subject = trim($d['subject'] ?? '');
$message = trim($d['message']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address.');
}
if (strlen($message) < 10) {
    jsonError('Message is too short.');
}

$userId = currentUserId(); // may be null for guests

$db   = getDB();
$stmt = $db->prepare(
    'INSERT INTO contact_messages (user_id, name, email, subject, message)
     VALUES (?,?,?,?,?)'
);
$stmt->execute([$userId, $name, $email, $subject, $message]);

jsonSuccess(['message' => "Thank you $name! We'll get back to you soon."], 201);
