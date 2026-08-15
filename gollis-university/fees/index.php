<?php
/** Fees - invoices and their balances. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_role('admin', 'student');

$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);
$statusFilter = (string)input('status', '');
$search       = (string)input('q', '');

$sql = "SELECT f.*, s.reg_no, s.full_name, s.id AS student_id, d.name AS department,
               COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id), 0) AS paid
        FROM fees f
        JOIN students s         ON s.id = f.student_id
        LEFT JOIN departments d ON d.id = s.department_id
        WHERE f.academic_year = ? AND f.semester = ?";
$params = [$academicYear, $semester];

if ($search !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.reg_no LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
}
if (is_student()) {
    $sql .= " AND f.student_id = ?";
    $params[] = (int)current_student_id();
}

$sql .= " ORDER BY s.reg_no";
$invoices = db_all($sql, $params);

if ($statusFilter !== '') {
    $invoices = array_values(array_filter(
        $invoices,
        static fn(array $row): bool => fee_status((float)$row['amount'], (float)$row['paid']) === $statusFilter
    ));
}

$billed    = array_sum(array_map(static fn(array $row): float => (float)$row['amount'], $invoices));
$collected = array_sum(array_map(static fn(array $row): float => (float)$row['paid'], $invoices));
$fullyPaid = count(array_filter(
    $invoices,
    static fn(array $row): bool => fee_status((float)$row['amount'], (float)$row['paid']) === 'paid'
));

$pageTitle    = is_student() ? 'My fees' : 'Student fees';
$pageSubtitle = $academicYear . ' · Semester ' . $semester . ' · amounts in ' . APP_CURRENCY;
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('fees/invoice_form.php') . '">➕ New invoice</a>'
      . '<a class="btn-sm btn-gold" href="' . url('fees/payment_form.php') . '">💵 Record payment</a>'
    : '<a class="btn-sm btn-ghost" href="' . url('fees/statement.php') . '">My statement</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="tiles">
  <div class="tile"><div class="label">Invoices</div><div class="number"><?= number_format(count($invoices)) ?></div><div class="foot"><?= number_format($fullyPaid) ?> fully paid</div></div>
  <div class="tile gold"><div class="label">Invoiced</div><div class="number"><?= money($billed) ?></div><div class="foot">Total charged this term</div></div>
  <div class="tile green"><div class="label">Collected</div><div class="number"><?= money($collected) ?></div><div class="foot"><?= percent($billed ? 100 * $collected / $billed : 0, 1) ?> of invoiced</div></div>
  <div class="tile red"><div class="label">Outstanding</div><div class="number"><?= money(max(0, $billed - $collected)) ?></div><div class="foot">Still to be collected</div></div>
</div>

<div class="panel">
  <div class="filters">
    <form method="get">
      <div class="field">
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year">
          <?php foreach (academic_years() as $year): ?>
            <option value="<?= e($year) ?>" <?= $year === $academicYear ? 'selected' : '' ?>><?= e($year) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= $value === $semester ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Payment status</label>
        <select id="status" name="status">
          <option value="">All</option>
          <?php foreach (['paid' => 'Paid', 'partial' => 'Partly paid', 'unpaid' => 'Unpaid'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!is_student()): ?>
        <div class="field" style="min-width:210px">
          <label for="q">Search student</label>
          <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or registration no">
        </div>
      <?php endif; ?>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
      <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('fees/index.php') ?>">Reset</a></div>
    </form>
  </div>

  <div class="panel-head"><div><h2><?= count($invoices) ?> invoices</h2><p>Tuition and other charges</p></div></div>

  <div class="table-wrap">
    <table id="feeTable">
      <thead>
        <tr>
          <?php if (!is_student()): ?><th>Student</th><th>Department</th><?php endif; ?>
          <th>Type</th><th>Due date</th><th class="right">Amount</th><th class="right">Paid</th>
          <th class="right">Balance</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($invoices as $invoice): ?>
        <?php
        $paid    = (float)$invoice['paid'];
        $amount  = (float)$invoice['amount'];
        $balance = fee_balance($amount, $paid);
        ?>
        <tr>
          <?php if (!is_student()): ?>
            <td><a href="<?= url('students/view.php?id=' . (int)$invoice['student_id']) ?>"><?= e($invoice['full_name']) ?></a><br>
                <small class="hint"><?= e($invoice['reg_no']) ?></small></td>
            <td><?= e($invoice['department'] ?: '-') ?></td>
          <?php endif; ?>
          <td><?= e(ucfirst($invoice['fee_type'])) ?><br><small class="hint"><?= e($invoice['description'] ?: '') ?></small></td>
          <td><?= e(fdate($invoice['due_date'])) ?></td>
          <td class="right"><?= money($amount) ?></td>
          <td class="right"><?= money($paid) ?></td>
          <td class="right"><strong><?= money($balance) ?></strong></td>
          <td><?= status_badge(fee_status($amount, $paid)) ?></td>
          <td>
            <a class="btn-sm btn-ghost" href="<?= url('fees/statement.php?student_id=' . (int)$invoice['student_id']) ?>">Statement</a>
            <?php if (is_admin()): ?>
              <a class="btn-sm btn-gold" href="<?= url('fees/payment_form.php?fee_id=' . (int)$invoice['id']) ?>">Pay</a>
              <a class="btn-sm btn-blue" href="<?= url('fees/invoice_form.php?id=' . (int)$invoice['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('fees/delete.php?id=' . (int)$invoice['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete this invoice and its payments?">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?>
        <tr data-empty><td colspan="9" class="table-empty">No invoices match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($invoices): ?>
        <tfoot>
          <tr>
            <td colspan="<?= is_student() ? 2 : 4 ?>">Totals</td>
            <td class="right"><?= money($billed) ?></td>
            <td class="right"><?= money($collected) ?></td>
            <td class="right"><?= money(max(0, $billed - $collected)) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
