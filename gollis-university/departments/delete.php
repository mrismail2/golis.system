<?php
/** Departments - delete. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id         = get_int('id');
$department = db_row("SELECT * FROM departments WHERE id = ?", [$id]);

if (!$department) {
    flash('That department no longer exists.', 'error');
    redirect('departments/index.php');
}

db_query("DELETE FROM departments WHERE id = ?", [$id]);
flash('Department "' . $department['name'] . '" deleted. Related students, lecturers and courses were kept.');

redirect('departments/index.php');
