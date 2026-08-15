<?php
/** Administration - delete a campus notice. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id     = get_int('id');
$notice = db_row("SELECT * FROM notices WHERE id = ?", [$id]);

if (!$notice) {
    flash('That notice no longer exists.', 'error');
    redirect('admin/notices.php');
}

db_query("DELETE FROM notices WHERE id = ?", [$id]);
flash('Notice "' . $notice['title'] . '" deleted.');

redirect('admin/notices.php');
