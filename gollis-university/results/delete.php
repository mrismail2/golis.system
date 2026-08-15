<?php
/** Results - delete a single result. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id     = get_int('id');
$result = db_row("SELECT * FROM results WHERE id = ?", [$id]);

if (!$result) {
    flash('That result no longer exists.', 'error');
    redirect('results/index.php');
}

db_query("DELETE FROM results WHERE id = ?", [$id]);
flash('Result deleted.');

redirect('results/index.php?course_id=' . (int)$result['course_id']
    . '&academic_year=' . urlencode((string)$result['academic_year'])
    . '&semester=' . (int)$result['semester']);
