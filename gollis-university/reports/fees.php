<?php
/** Reports - fee collection by department, month and payment method. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$academicYear = (string)input('academic_year', CURRENT_YEAR);

$byDepartment = db_all(
    "SELECT COALESCE(d.name, 'No department') AS department,
            COUNT(f.id) AS invoices,
            COALESCE(SUM(f.amount), 0) AS billed,
            COALESCE(SUM((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id)), 0) AS collected
     FROM fees f
     JOIN students s         ON s.id = f.student_id
     LEFT JOIN departments d ON d.id = s.department_id
     WHERE f.academic_year = ?
     GROUP BY d.id, d.name
     ORDER BY billed DESC",
    [$academicYear]
);

$byMonth = db_all(
    "SELECT DATE_FORMAT(p.paid_on, '%Y-%m') AS month,
            COUNT(*) AS payments,
            SUM(p.amount) AS collected
     FROM payments p
     JOIN fees f ON f.id = p.fee_id
     WHERE f.academic_year = ?
     GROUP BY month
     ORDER BY month",
    [$academicYear]
);

$byMethod = db_all(
    "SELECT p.method, COUNT(*) AS payments, SUM(p.amount) AS collected
     FROM payments p
     JOIN fees f ON f.id = p.fee_id
     WHERE f.academic_year = ?
     GROUP BY p.method
     ORDER BY collected DESC",
    [$academicYear]
);

// grouped in a sub-query so the outstanding balance can be filtered and sorted on
$debtors = db_all(
    "SELECT * FROM (
         SELECT s.reg_no, s.full_name, s.id AS student_id, d.name AS department,
                SUM(f.amount) AS billed,
                COALESCE(SUM((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id)), 0) AS paid
         FROM fees f
         JOIN students s         ON s.id = f.student_id
         LEFT JOIN departments d ON d.id = s.department_id
         WHERE f.academic_year = ?
         GROUP BY s.id, s.reg_no, s.full_name, d.name
     ) AS totals
     WHERE totals.billed > totals.paid
     ORDER BY (totals.billed - totals.paid) DESC
     LIMIT 15",
    [$academicYear]
);

$billed    = array_sum(array_map(static fn(array $row): float => (float)$row['billed'], $byDepartment));
$collected = array_sum(array_map(static fn(array $row): float => (float)$row['collected'], $byDepartment));
$maxMonth  = max(1.0, (float)max(array_column($byMonth, 'collected') ?: [1]));

$pageTitle    = 'Fees report';
$pageSubtitle = $academicYear . ' · amounts in ' . APP_CURRENCY;
$pageActions  = '<button class="btn-sm btn-ghost no-print" type="button" onclick="window.print()">🖨️ Print</button>'
    . '<a class="btn-sm btn-ghost" href="' . url('reports/index.php') . '">← All reports</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel no-print">
  <div class="filters">
    <form method="get" data-auto-submit>
      <div class="field">
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year">
          <?php foreach (academic_years() as $year): ?>
            <option value="<?= e($year) ?>" <?= $year === $academicYear ? 'selected' : '' ?>><?= e($year) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Apply</button></div>
    </form>
  </div>
</div>

<div class="tiles">
  <div class="tile gold"><div class="label">Invoiced</div><div class="number"><?= money($billed) ?></div><div class="foot">All invoices for the year</div></div>
  <div class="tile green"><div class="label">Collected</div><div class="number"><?= money($collected) ?></div><div class="foot"><?= percent($billed ? 100 * $collected / $billed : 0, 1) ?> of invoiced</div></div>
  <div class="tile red"><div class="label">Outstanding</div><div class="number"><?= money(max(0, $billed - $collected)) ?></div><div class="foot"><?= count($debtors) ?> students with a balance</div></div>
  <div class="tile"><div class="label">Payments</div><div class="number"><?= number_format(array_sum(array_map(static fn(array $row): int => (int)$row['payments'], $byMethod))) ?></div><div class="foot">Receipts issued</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Collection per department</h2><p>Invoiced against collected</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Department</th><th class="center">Invoices</th><th class="right">Invoiced</th>
                 <th class="right">Collected</th><th class="right">Outstanding</th><th>Collection rate</th></tr></thead>
      <tbody>
      <?php foreach ($byDepartment as $row): ?>
        <?php
        $departmentBilled    = (float)$row['billed'];
        $departmentCollected = (float)$row['collected'];
        $rate                = $departmentBilled ? 100 * $departmentCollected / $departmentBilled : 0;
        ?>
        <tr>
          <td><strong><?= e($row['department']) ?></strong></td>
          <td class="center"><?= (int)$row['invoices'] ?></td>
          <td class="right"><?= money($departmentBilled) ?></td>
          <td class="right"><?= money($departmentCollected) ?></td>
          <td class="right"><strong><?= money(max(0, $departmentBilled - $departmentCollected)) ?></strong></td>
          <td>
            <div class="bar <?= $rate >= 75 ? 'green' : ($rate >= 50 ? 'gold' : 'red') ?>">
              <span style="width:<?= (int)round($rate) ?>%"></span>
            </div>
            <small class="hint"><?= percent($rate, 1) ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byDepartment): ?>
        <tr><td colspan="6" class="table-empty">No invoices for this academic year.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($byDepartment): ?>
        <tfoot>
          <tr><td colspan="2">Totals</td>
              <td class="right"><?= money($billed) ?></td>
              <td class="right"><?= money($collected) ?></td>
              <td class="right"><?= money(max(0, $billed - $collected)) ?></td>
              <td></td></tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><div><h2>Collection by month</h2><p>When the money came in</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Month</th><th class="center">Payments</th><th class="right">Collected</th><th>Share</th></tr></thead>
        <tbody>
        <?php foreach ($byMonth as $row): ?>
          <tr>
            <td><?= e(fdate($row['month'] . '-01', 'F Y')) ?></td>
            <td class="center"><?= (int)$row['payments'] ?></td>
            <td class="right"><?= money($row['collected']) ?></td>
            <td><div class="bar green"><span style="width:<?= (int)round(100 * (float)$row['collected'] / $maxMonth) ?>%"></span></div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$byMonth): ?>
          <tr><td colspan="4" class="table-empty">No payments recorded.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>Payment methods</h2><p>How students pay</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Method</th><th class="center">Payments</th><th class="right">Collected</th></tr></thead>
        <tbody>
        <?php foreach ($byMethod as $row): ?>
          <tr>
            <td><?= e(strtoupper($row['method'])) ?></td>
            <td class="center"><?= (int)$row['payments'] ?></td>
            <td class="right"><?= money($row['collected']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$byMethod): ?>
          <tr><td colspan="3" class="table-empty">No payments recorded.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Students with outstanding balances</h2><p>Largest 15 balances</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Registration no</th><th>Student</th><th>Department</th>
                 <th class="right">Invoiced</th><th class="right">Paid</th><th class="right">Balance</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php foreach ($debtors as $row): ?>
        <tr>
          <td><span class="pill"><?= e($row['reg_no']) ?></span></td>
          <td><?= e($row['full_name']) ?></td>
          <td><?= e($row['department'] ?: '-') ?></td>
          <td class="right"><?= money($row['billed']) ?></td>
          <td class="right"><?= money($row['paid']) ?></td>
          <td class="right"><strong><?= money((float)$row['billed'] - (float)$row['paid']) ?></strong></td>
          <td class="no-print"><a class="btn-sm btn-ghost" href="<?= url('fees/statement.php?student_id=' . (int)$row['student_id']) ?>">Statement</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$debtors): ?>
        <tr><td colspan="7" class="table-empty">Every invoice for this year has been settled.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
