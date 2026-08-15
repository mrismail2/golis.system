<?php
/** Courses - delete. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id     = get_int('id');
$course = db_row("SELECT * FROM courses WHERE id = ?", [$id]);

if (!$course) {
    flash('That course no longer exists.', 'error');
    redirect('courses/index.php');
}

// enrollments, attendance, results and exams cascade with the course
db_query("DELETE FROM courses WHERE id = ?", [$id]);
flash('Course ' . $course['code'] . ' and its enrollments, attendance and results were deleted.');

redirect('courses/index.php');
