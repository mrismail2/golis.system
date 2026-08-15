<?php
/** Fees - delete a payment receipt. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();
verify_csrf();

$id      = get_int('id');
$payment = db_row(
    "SELECT p.*, f.student_id FROM payments p JOIN fees f ON f.id = p.fee_id WHERE p.id = ?",
    [$id]
);

if (!$payment) {
    flash('That payment no longer exists.', 'error');
    redirect('fees/index.php');
}

db_query("DELETE FROM payments WHERE id = ?", [$id]);
flash('Receipt ' . $payment['receipt_no'] . ' deleted.');

redirect('fees/statement.php?student_id=' . (int)$payment['student_id']);
