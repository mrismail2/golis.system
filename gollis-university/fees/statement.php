<?php
/** Fees - printable statement of account for one student. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('admin', 'student');

$studentId = get_int('student_id') ?: (int)(current_student_id() ?? 0);
require_student_access($studentId);

$student = db_row(
    "SELECT s.*, d.name AS department
     FROM students s LEFT JOIN departments d ON d.id = s.department_id
     WHERE s.id = ?",
    [$studentId]
);

if (!$student) {
    flash('That student record no longer exists.', 'error');
    redirect(is_student() ? 'dashboard.php' : 'fees/index.php');
}

$invoices = db_all(
    "SELECT f.*, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id), 0) AS paid
     FROM fees f WHERE f.student_id = ?
     ORDER BY f.academic_year DESC, f.semester DESC, f.fee_type",
    [$studentId]
);

$payments = db_all(
    "SELECT p.*, f.fee_type, f.academic_year, f.semester, u.full_name AS recorded_by_name
     FROM payments p
     JOIN fees f      ON f.id = p.fee_id
     LEFT JOIN users u ON u.id = p.recorded_by
     WHERE f.student_id = ?
     ORDER BY p.paid_on DESC, p.id DESC",
    [$studentId]
);

$billed  = array_sum(array_map(static fn(array $row): float => (float)$row['amount'], $invoices));
$paid    = array_sum(array_map(static fn(array $row): float => (float)$row['paid'], $invoices));
$balance = max(0, $billed - $paid);

$pageTitle    = 'Fee statement';
$pageSubtitle = $student['full_name'] . ' · ' . $student['reg_no'];
$pageActions  = '<button class="btn-sm btn-ghost no-print" type="button" onclick="window.print()">🖨️ Print</button>';
if (is_admin()) {
    $pageActions .= '<a class="btn-sm btn-gold" href="' . url('fees/invoice_form.php?student_id=' . $studentId) . '">New invoice</a>';
}

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <div>
      <h2><?= e(APP_NAME) ?> &ndash; <?= e(APP_CAMPUS) ?></h2>
      <p>Statement of account · printed <?= e(fdate(date('Y-m-d'))) ?> · amounts in <?= e(APP_CURRENCY) ?></p>
    </div>
    <img src="<?= url('assets/images/logo.jpg') ?>" alt="logo" style="width:64px;height:64px;border-radius:50%;object-fit:cover">
  </div>

  <dl class="data-list">
    <div><dt>Student</dt><dd><?= e($student['full_name']) ?></dd></div>
    <div><dt>Registration no</dt><dd><?= e($student['reg_no']) ?></dd></div>
    <div><dt>Department</dt><dd><?= e($student['department'] ?: '-') ?></dd></div>
    <div><dt>Year of study</dt><dd>Year <?= (int)$student['year_of_study'] ?></dd></div>
  </dl>
</div>

<div class="tiles">
  <div class="tile gold"><div class="label">Invoiced</div><div class="number"><?= money($billed) ?></div><div class="foot"><?= count($invoices) ?> invoices</div></div>
  <div class="tile green"><div class="label">Paid</div><div class="number"><?= money($paid) ?></div><div class="foot"><?= count($payments) ?> payments</div></div>
  <div class="tile red"><div class="label">Balance</div><div class="number"><?= money($balance) ?></div><div class="foot"><?= $balance > 0 ? 'Outstanding' : 'Fully settled' ?></div></div>
  <div class="tile"><div class="label">Settled</div><div class="number"><?= percent($billed ? 100 * $paid / $billed : 0, 0) ?></div><div class="foot">of the invoiced total</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Invoices</h2><p>Charges raised against this student</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Term</th><th>Type</th><th>Description</th><th>Due date</th>
                 <th class="right">Amount</th><th class="right">Paid</th><th class="right">Balance</th><th>Status</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($invoices as $invoice): ?>
        <?php $invoiceBalance = fee_balance((float)$invoice['amount'], (float)$invoice['paid']); ?>
        <tr>
          <td><?= e($invoice['academic_year']) ?> · S<?= (int)$invoice['semester'] ?></td>
          <td><?= e(ucfirst($invoice['fee_type'])) ?></td>
          <td class="hint"><?= e($invoice['description'] ?: '') ?></td>
          <td><?= e(fdate($invoice['due_date'])) ?></td>
          <td class="right"><?= money($invoice['amount']) ?></td>
          <td class="right"><?= money($invoice['paid']) ?></td>
          <td class="right"><strong><?= money($invoiceBalance) ?></strong></td>
          <td><?= status_badge(fee_status((float)$invoice['amount'], (float)$invoice['paid'])) ?></td>
          <?php if (is_admin()): ?>
            <td class="no-print">
              <?php if ($invoiceBalance > 0): ?>
                <a class="btn-sm btn-gold" href="<?= url('fees/payment_form.php?fee_id=' . (int)$invoice['id']) ?>">Pay</a>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?>
        <tr><td colspan="<?= is_admin() ? 9 : 8 ?>" class="table-empty">No invoices have been raised.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($invoices): ?>
        <tfoot>
          <tr><td colspan="4">Totals</td>
              <td class="right"><?= money($billed) ?></td>
              <td class="right"><?= money($paid) ?></td>
              <td class="right"><?= money($balance) ?></td>
              <td colspan="<?= is_admin() ? 2 : 1 ?>"></td></tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Payments</h2><p>Receipts issued to this student</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt</th><th>Date</th><th>For</th><th>Method</th><th class="right">Amount</th><th>Recorded by</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <tr>
          <td><span class="pill"><?= e($payment['receipt_no']) ?></span></td>
          <td><?= e(fdate($payment['paid_on'])) ?></td>
          <td><?= e(ucfirst($payment['fee_type'])) ?> · <?= e($payment['academic_year']) ?> S<?= (int)$payment['semester'] ?></td>
          <td><?= e(strtoupper($payment['method'])) ?></td>
          <td class="right"><?= money($payment['amount']) ?></td>
          <td class="hint"><?= e($payment['recorded_by_name'] ?: '-') ?></td>
          <?php if (is_admin()): ?>
            <td class="no-print">
              <a class="btn-sm btn-danger"
                 href="<?= url('fees/payment_delete.php?id=' . (int)$payment['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete receipt <?= e($payment['receipt_no']) ?>?">Delete</a>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <tr><td colspan="<?= is_admin() ? 7 : 6 ?>" class="table-empty">No payments have been recorded.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="hint" style="margin-top:14px">
    <?= e(APP_NAME) ?> · <?= e(APP_ADDRESS) ?> · <?= e(APP_PHONE) ?> · <?= e(APP_EMAIL) ?>
  </p>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
