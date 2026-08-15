<?php
/** Attendance - delete a single record. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id     = get_int('id');
$record = db_row("SELECT * FROM attendance WHERE id = ?", [$id]);

if (!$record) {
    flash('That attendance record no longer exists.', 'error');
    redirect('attendance/index.php');
}

db_query("DELETE FROM attendance WHERE id = ?", [$id]);
flash('Attendance record deleted.');

redirect('attendance/index.php?course_id=' . (int)$record['course_id']);
