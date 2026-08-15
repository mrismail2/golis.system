<?php
/** Students - delete a student and everything attached to the record. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id      = get_int('id');
$student = db_row("SELECT * FROM students WHERE id = ?", [$id]);

if (!$student) {
    flash('That student record no longer exists.', 'error');
    redirect('students/index.php');
}

$pdo = db();
$pdo->beginTransaction();

try {
    // enrollments, attendance, results, fees and payments cascade on delete
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

    if ($student['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([(int)$student['user_id']]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    flash('The student could not be deleted: ' . $e->getMessage(), 'error');
    redirect('students/view.php?id=' . $id);
}

delete_photo($student['photo']);
flash('Student ' . $student['full_name'] . ' (' . $student['reg_no'] . ') was deleted.');

redirect('students/index.php');
