<?php
/** Lecturers - delete. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id       = get_int('id');
$lecturer = db_row("SELECT * FROM lecturers WHERE id = ?", [$id]);

if (!$lecturer) {
    flash('That lecturer no longer exists.', 'error');
    redirect('lecturers/index.php');
}

$pdo = db();
$pdo->beginTransaction();

try {
    // courses keep their records, their lecturer_id is set to NULL
    $pdo->prepare("DELETE FROM lecturers WHERE id = ?")->execute([$id]);

    if ($lecturer['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'lecturer'")->execute([(int)$lecturer['user_id']]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    flash('The lecturer could not be deleted: ' . $e->getMessage(), 'error');
    redirect('lecturers/view.php?id=' . $id);
}

delete_photo($lecturer['photo']);
flash('Lecturer ' . $lecturer['full_name'] . ' was deleted.');

redirect('lecturers/index.php');
