<?php
/**
 * Gollis University - Gabiley Campus
 * Application settings and database connection (PDO / MySQL).
 *
 * Edit the DB_* values below to match your server, or set the matching
 * environment variables (GU_DB_HOST, GU_DB_NAME, GU_DB_USER, GU_DB_PASS).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Database credentials
// ---------------------------------------------------------------------
define('DB_HOST', getenv('GU_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('GU_DB_NAME') ?: 'gollis_university');
define('DB_USER', getenv('GU_DB_USER') ?: 'root');
define('DB_PASS', getenv('GU_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Campus / application settings
// ---------------------------------------------------------------------
define('APP_NAME',        'Gollis University');
define('APP_CAMPUS',      'Gabiley Campus');
define('APP_ADDRESS',     'Gabiley, Somaliland');
define('APP_PHONE',       '+252 63 1234567');
define('APP_EMAIL',       'info@golisuniversity.edu');
define('APP_CURRENCY',    'USD');
define('CURRENT_YEAR',    '2025/2026');   // default academic year
define('CURRENT_SEMESTER', 1);            // default semester
define('PASS_MARK',       50);            // minimum total marks to pass

/** Absolute path of the application root (no trailing slash). */
define('APP_ROOT', str_replace('\\', '/', dirname(__DIR__)));

/** Where student / lecturer photos are stored. */
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads');

/**
 * URL prefix of the application, e.g. "/gollis-university" when the project
 * sits in htdocs/gollis-university, or "" when it is the document root.
 */
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $prefix  = '';
    if ($docRoot !== '' && str_starts_with(APP_ROOT, $docRoot)) {
        $prefix = rtrim(substr(APP_ROOT, strlen($docRoot)), '/');
    }
    define('BASE_URL', $prefix);
}

// ---------------------------------------------------------------------
// Error reporting - switch APP_DEBUG off on a live server
// ---------------------------------------------------------------------
define('APP_DEBUG', true);
error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set('Africa/Djibouti'); // EAT, same offset as Somaliland

/**
 * Shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        $hint = 'Import database/gollis_university.sql and check the DB_* values in config/database.php.';
        if (APP_DEBUG) {
            exit('<h3>Database connection failed</h3><p>' . htmlspecialchars($e->getMessage()) . '</p><p>' . $hint . '</p>');
        }
        exit('<h3>Database connection failed</h3><p>' . $hint . '</p>');
    }

    return $pdo;
}

/** Run a query with bound parameters and return the statement. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/** First row of a query, or null. */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/** All rows of a query. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** First column of the first row (counts, sums, ...). */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_query($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

require_once APP_ROOT . '/includes/functions.php';
