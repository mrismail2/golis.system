<?php
/**
 * Gollis University - shared helper functions.
 * Loaded automatically by config/database.php.
 */

declare(strict_types=1);

/** Escape a value for safe output in HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an application URL: url('students/index.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Send a redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Value of a GET/POST field with a default. */
function input(string $key, mixed $default = ''): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $value;
}

function post_int(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

function get_int(string $key, int $default = 0): int
{
    return (int)($_GET[$key] ?? $default);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------

/** Queue a message shown once on the next page. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/** Read and clear queued flash messages. */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every form that changes data. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request when the submitted token does not match. */
function verify_csrf(): void
{
    $sent = (string)($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        exit('Security token expired. Go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

function money(float|string|null $amount): string
{
    return '$' . number_format((float)$amount, 2);
}

function fdate(?string $date, string $format = 'd M Y'): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '-';
    }

    $ts = strtotime($date);

    return $ts === false ? '-' : date($format, $ts);
}

function ftime(?string $time): string
{
    if (!$time) {
        return '-';
    }

    $ts = strtotime($time);

    return $ts === false ? '-' : date('g:i A', $ts);
}

function percent(float $value, int $decimals = 0): string
{
    return number_format($value, $decimals) . '%';
}

/** Coloured pill used across the tables. */
function status_badge(string $status): string
{
    $map = [
        'active'    => 'ok',   'present'   => 'ok',   'paid'     => 'ok',
        'pass'      => 'ok',   'published' => 'ok',   'open'     => 'ok',
        'pending'   => 'warn', 'late'      => 'warn', 'partial'  => 'warn',
        'upcoming'  => 'warn', 'excused'   => 'warn', 'draft'    => 'warn',
        'absent'    => 'bad',  'fail'      => 'bad',  'unpaid'   => 'bad',
        'suspended' => 'bad',  'withdrawn' => 'bad',  'inactive' => 'bad',
    ];
    $key   = strtolower($status);
    $class = $map[$key] ?? 'info';

    return '<span class="status ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

// ---------------------------------------------------------------------
// Academic helpers
// ---------------------------------------------------------------------

/**
 * Convert total marks into the campus grade and grade points.
 *
 * @return array{grade:string, points:float, remark:string}
 */
function grade_for(float $marks): array
{
    $scale = [
        [90, 'A+', 4.00], [85, 'A', 4.00], [80, 'A-', 3.70],
        [75, 'B+', 3.30], [65, 'B', 3.00], [60, 'C+', 2.50],
        [55, 'C',  2.00], [50, 'D', 1.00],
    ];

    foreach ($scale as [$min, $grade, $points]) {
        if ($marks >= $min) {
            return ['grade' => $grade, 'points' => $points, 'remark' => 'Pass'];
        }
    }

    return ['grade' => 'F', 'points' => 0.00, 'remark' => 'Fail'];
}

/** List of academic years offered in the filter drop-downs. */
function academic_years(): array
{
    $start = (int)substr(CURRENT_YEAR, 0, 4);
    $years = [];
    for ($i = $start + 1; $i >= $start - 3; $i--) {
        $years[] = $i . '/' . ($i + 1);
    }

    return $years;
}

function semesters(): array
{
    return [1 => 'Semester 1', 2 => 'Semester 2'];
}

/** Outstanding balance of a fee invoice. */
function fee_balance(float $amount, float $paid): float
{
    return round($amount - $paid, 2);
}

/** Paid / Partial / Unpaid for a fee invoice. */
function fee_status(float $amount, float $paid): string
{
    if ($paid >= $amount && $amount > 0) {
        return 'paid';
    }

    return $paid > 0 ? 'partial' : 'unpaid';
}

/** Next receipt number, e.g. RCP-2026-0008. */
function next_receipt_no(): string
{
    $year = date('Y');
    $last = (string)db_value(
        "SELECT receipt_no FROM payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1",
        ['RCP-' . $year . '-%'],
        ''
    );
    $next = $last === '' ? 1 : ((int)substr($last, -4) + 1);

    return sprintf('RCP-%s-%04d', $year, $next);
}

/** Next student registration number, e.g. GU-GAB-1009. */
function next_reg_no(): string
{
    $last = (string)db_value(
        "SELECT reg_no FROM students WHERE reg_no LIKE 'GU-GAB-%' ORDER BY id DESC LIMIT 1",
        [],
        ''
    );
    $next = $last === '' ? 1001 : ((int)substr($last, -4) + 1);

    return 'GU-GAB-' . $next;
}

/** Next staff number, e.g. TC-005. */
function next_staff_no(): string
{
    $last = (string)db_value(
        "SELECT staff_no FROM lecturers WHERE staff_no LIKE 'TC-%' ORDER BY id DESC LIMIT 1",
        [],
        ''
    );
    $next = $last === '' ? 1 : ((int)substr($last, -3) + 1);

    return sprintf('TC-%03d', $next);
}

// ---------------------------------------------------------------------
// Look-ups used by the forms
// ---------------------------------------------------------------------

function all_departments(): array
{
    return db_all("SELECT * FROM departments ORDER BY name");
}

function all_lecturers(): array
{
    return db_all("SELECT id, staff_no, full_name FROM lecturers WHERE status = 'active' ORDER BY full_name");
}

function all_students(): array
{
    return db_all("SELECT id, reg_no, full_name FROM students WHERE status = 'active' ORDER BY reg_no");
}

/** Courses, optionally restricted to one lecturer. */
function all_courses(?int $lecturerId = null): array
{
    if ($lecturerId !== null) {
        return db_all(
            "SELECT id, code, title FROM courses WHERE lecturer_id = ? ORDER BY code",
            [$lecturerId]
        );
    }

    return db_all("SELECT id, code, title FROM courses ORDER BY code");
}

/** <option> list helper. */
function options(array $rows, string $valueKey, string $labelKey, mixed $selected = null): string
{
    $html = '';
    foreach ($rows as $row) {
        $value    = (string)$row[$valueKey];
        $isChosen = (string)$selected === $value;
        $html .= '<option value="' . e($value) . '"' . ($isChosen ? ' selected' : '') . '>'
            . e($row[$labelKey]) . '</option>';
    }

    return $html;
}

// ---------------------------------------------------------------------
// Photo uploads
// ---------------------------------------------------------------------

/**
 * Store an uploaded photo in assets/uploads and return its file name.
 *
 * @return array{0:?string,1:?string} [file name, error message]
 */
function save_photo(string $field, string $prefix = 'photo'): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The photo could not be uploaded (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [null, 'The photo must be 2 MB or smaller.'];
    }

    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $type    = @exif_imagetype($file['tmp_name']);
    if ($type === false || !isset($allowed[$type])) {
        return [null, 'Only JPG, PNG or WEBP images are accepted.'];
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
        return [null, 'The assets/uploads folder is missing and could not be created.'];
    }

    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$type];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return [null, 'The photo could not be saved to assets/uploads.'];
    }

    return [$name, null];
}

/** Delete a stored photo, ignoring anything outside assets/uploads. */
function delete_photo(?string $name): void
{
    if (!$name) {
        return;
    }

    $path = UPLOAD_DIR . '/' . basename($name);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** URL of a person photo, falling back to the campus logo. */
function photo_url(?string $name): string
{
    return $name && is_file(UPLOAD_DIR . '/' . basename($name))
        ? url('assets/uploads/' . rawurlencode(basename($name)))
        : url('assets/images/logo.jpg');
}
