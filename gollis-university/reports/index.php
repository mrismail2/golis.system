<?php
/** Reports - overview and links to the detailed reports. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$students   = (int)db_value("SELECT COUNT(*) FROM students WHERE status = 'active'", [], 0);
$lecturers  = (int)db_value("SELECT COUNT(*) FROM lecturers WHERE status = 'active'", [], 0);
$courses    = (int)db_value("SELECT COUNT(*) FROM courses", [], 0);
$billed     = (float)db_value("SELECT COALESCE(SUM(amount),0) FROM fees", [], 0);
$collected  = (float)db_value("SELECT COALESCE(SUM(amount),0) FROM payments", [], 0);
$attendance = (float)db_value(
    "SELECT ROUND(100 * SUM(status IN ('present','late')) / NULLIF(COUNT(*),0), 1) FROM attendance",
    [],
    0
);
$passRate = (float)db_value(
    "SELECT ROUND(100 * SUM(total_marks >= ?) / NULLIF(COUNT(*),0), 1) FROM results",
    [PASS_MARK],
    0
);

$pageTitle    = 'Reports';
$pageSubtitle = 'Summaries of enrollment, attendance, results and fees';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="tiles">
  <div class="tile"><div class="label">Active students</div><div class="number"><?= number_format($students) ?></div><div class="foot"><?= number_format($lecturers) ?> lecturers · <?= number_format($courses) ?> courses</div></div>
  <div class="tile gold"><div class="label">Attendance rate</div><div class="number"><?= percent($attendance, 1) ?></div><div class="foot">All recorded sessions</div></div>
  <div class="tile green"><div class="label">Pass rate</div><div class="number"><?= percent($passRate, 1) ?></div><div class="foot">Mark of <?= PASS_MARK ?> or more</div></div>
  <div class="tile red"><div class="label">Fee collection</div><div class="number"><?= percent($billed ? 100 * $collected / $billed : 0, 1) ?></div><div class="foot"><?= money($collected) ?> of <?= money($billed) ?></div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><div><h2>📊 Enrollment report</h2><p>Students by department, year and gender</p></div></div>
    <p class="hint">How many students are registered in each department, which year they are in, and the gender split.</p>
    <p><a class="btn-sm btn-blue" href="<?= url('reports/students.php') ?>">Open report</a></p>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>🗓️ Attendance report</h2><p>Attendance rate per course</p></div></div>
    <p class="hint">Sessions recorded, present, late and absent counts, and the attendance rate of every course.</p>
    <p><a class="btn-sm btn-blue" href="<?= url('reports/attendance.php') ?>">Open report</a></p>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>📈 Results report</h2><p>Grade distribution and pass rates</p></div></div>
    <p class="hint">Average marks, pass rate and the spread of grades for each course in a term.</p>
    <p><a class="btn-sm btn-blue" href="<?= url('reports/results.php') ?>">Open report</a></p>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>💵 Fees report</h2><p>Collection by department and by month</p></div></div>
    <p class="hint">Invoiced against collected amounts, outstanding balances and payment methods.</p>
    <p><a class="btn-sm btn-<?= is_admin() ? 'blue' : 'ghost' ?>" href="<?= url('reports/fees.php') ?>">Open report</a>
       <?php if (!is_admin()): ?><span class="hint">Administrators only</span><?php endif; ?></p>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
