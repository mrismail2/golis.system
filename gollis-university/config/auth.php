<?php
/**
 * Gollis University - session handling, sign in and role checks.
 *
 * Every protected page starts with:
 *     require_once __DIR__ . '/../config/auth.php';
 *     require_login();               // or require_role('admin');
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('GOLLISSESS');
    session_start();
}

// ---------------------------------------------------------------------
// Sign in / sign out
// ---------------------------------------------------------------------

/**
 * Check the credentials and open a session.
 *
 * @return string|null null on success, otherwise the error message.
 */
function attempt_login(string $username, string $password): ?string
{
    $user = db_row(
        "SELECT * FROM users WHERE username = ? LIMIT 1",
        [$username]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'Wrong username or password.';
    }

    if (!(int)$user['is_active']) {
        return 'This account has been disabled. Contact the campus administrator.';
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];

    db_query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

    return null;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// Who is signed in
// ---------------------------------------------------------------------

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function user_role(): string
{
    return current_user()['role'] ?? 'guest';
}

function user_id(): int
{
    return (int)(current_user()['id'] ?? 0);
}

function is_admin(): bool
{
    return user_role() === 'admin';
}

function is_lecturer(): bool
{
    return user_role() === 'lecturer';
}

function is_student(): bool
{
    return user_role() === 'student';
}

/** True when the signed in user holds any of the given roles. */
function has_role(string ...$roles): bool
{
    return in_array(user_role(), $roles, true);
}

/** Row id of the lecturer profile linked to the signed in user. */
function current_lecturer_id(): ?int
{
    static $id = false;

    if ($id === false) {
        $id = is_lecturer()
            ? (int)db_value("SELECT id FROM lecturers WHERE user_id = ?", [user_id()], 0) ?: null
            : null;
    }

    return $id;
}

/** Row id of the student profile linked to the signed in user. */
function current_student_id(): ?int
{
    static $id = false;

    if ($id === false) {
        $id = is_student()
            ? (int)db_value("SELECT id FROM students WHERE user_id = ?", [user_id()], 0) ?: null
            : null;
    }

    return $id;
}

// ---------------------------------------------------------------------
// Guards
// ---------------------------------------------------------------------

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('Please sign in to continue.', 'warn');
        redirect('login.php');
    }
}

/** Allow only the given roles through. */
function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        http_response_code(403);
        flash('You do not have permission to open that page.', 'error');
        redirect('dashboard.php');
    }
}

/** Shortcut for admin-only screens. */
function require_admin(): void
{
    require_role('admin');
}

/** Staff = admin or lecturer. */
function require_staff(): void
{
    require_role('admin', 'lecturer');
}

/**
 * A lecturer may only touch their own courses; an admin may touch any.
 */
function can_use_course(int $courseId): bool
{
    if (is_admin()) {
        return true;
    }

    if (is_lecturer()) {
        return (int)db_value(
            "SELECT COUNT(*) FROM courses WHERE id = ? AND lecturer_id = ?",
            [$courseId, (int)current_lecturer_id()],
            0
        ) > 0;
    }

    return false;
}

/** Stop the request when the signed in staff member does not own the course. */
function require_course_access(int $courseId): void
{
    if (!can_use_course($courseId)) {
        http_response_code(403);
        flash('That course is not assigned to you.', 'error');
        redirect('dashboard.php');
    }
}

/** A student may only read their own record. */
function require_student_access(int $studentId): void
{
    if (is_admin() || is_lecturer()) {
        return;
    }

    if (current_student_id() !== $studentId) {
        http_response_code(403);
        flash('You can only view your own records.', 'error');
        redirect('dashboard.php');
    }
}
