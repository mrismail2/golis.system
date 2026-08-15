<?php
/** Fees - delete an invoice together with its payments. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id      = get_int('id');
$invoice = db_row("SELECT * FROM fees WHERE id = ?", [$id]);

if (!$invoice) {
    flash('That invoice no longer exists.', 'error');
    redirect('fees/index.php');
}

db_query("DELETE FROM fees WHERE id = ?", [$id]);
flash('Invoice and its payments were deleted.');

redirect('fees/statement.php?student_id=' . (int)$invoice['student_id']);
