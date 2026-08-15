<?php
/** Examinations - remove a sitting from the timetable. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id   = get_int('id');
$exam = db_row("SELECT * FROM exams WHERE id = ?", [$id]);

if (!$exam) {
    flash('That examination no longer exists.', 'error');
    redirect('courses/exams.php');
}

db_query("DELETE FROM exams WHERE id = ?", [$id]);
flash('Examination removed from the timetable.');

redirect('courses/exams.php?academic_year=' . urlencode((string)$exam['academic_year']) . '&semester=' . (int)$exam['semester']);
