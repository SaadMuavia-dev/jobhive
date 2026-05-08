<?php
// ============================================================
//  JobHive — Database Configuration
//  includes/db.php
//  ⚠  Put this file OUTSIDE your web root, or protect it with
//     .htaccess  "Deny from all" if it must live inside.
// ============================================================

define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'jobhive');
define('DB_USER',     'root');        // ← change to your MySQL user
define('DB_PASS',     '');            // ← change to your MySQL password
define('DB_CHARSET',  'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * Throws PDOException on failure (caught by callers).
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
