<?php
/** Administration - delete a portal account. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id   = get_int('id');
$user = db_row("SELECT * FROM users WHERE id = ?", [$id]);

if (!$user) {
    flash('That account no longer exists.', 'error');
    redirect('admin/index.php');
}

if ($id === user_id()) {
    flash('You cannot delete the account you are signed in with.', 'error');
    redirect('admin/index.php');
}

// students.user_id and lecturers.user_id are set to NULL by the foreign keys
db_query("DELETE FROM users WHERE id = ?", [$id]);
flash('Account ' . $user['username'] . ' deleted. The student or lecturer record was kept.');

redirect('admin/index.php');
