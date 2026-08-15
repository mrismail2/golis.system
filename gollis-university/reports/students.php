<?php
/** Reports - enrollment by department, year and gender. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$byDepartment = db_all(
    "SELECT d.name AS department,
            COUNT(s.id) AS total,
            SUM(s.gender = 'male')   AS males,
            SUM(s.gender = 'female') AS females,
            SUM(s.year_of_study = 1) AS year1,
            SUM(s.year_of_study = 2) AS year2,
            SUM(s.year_of_study = 3) AS year3,
            SUM(s.year_of_study = 4) AS year4
     FROM departments d
     LEFT JOIN students s ON s.department_id = d.id AND s.status = 'active'
     GROUP BY d.id, d.name
     ORDER BY total DESC, d.name"
);

$byStatus = db_all(
    "SELECT status, COUNT(*) AS total FROM students GROUP BY status ORDER BY total DESC"
);

$noDepartment = (int)db_value(
    "SELECT COUNT(*) FROM students WHERE department_id IS NULL AND status = 'active'",
    [],
    0
);

$total   = array_sum(array_map(static fn(array $row): int => (int)$row['total'], $byDepartment)) + $noDepartment;
$maxRows = max(1, (int)max(array_column($byDepartment, 'total') ?: [1]));

$pageTitle    = 'Enrollment report';
$pageSubtitle = 'Active students by department, year and gender';
$pageActions  = '<button class="btn-sm btn-ghost no-print" type="button" onclick="window.print()">🖨️ Print</button>'
    . '<a class="btn-sm btn-ghost" href="' . url('reports/index.php') . '">← All reports</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head"><div><h2><?= number_format($total) ?> active students</h2><p>Breakdown per department</p></div></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Department</th><th class="center">Students</th><th class="center">Male</th><th class="center">Female</th>
            <th class="center">Yr 1</th><th class="center">Yr 2</th><th class="center">Yr 3</th><th class="center">Yr 4</th><th>Share</th></tr>
      </thead>
      <tbody>
      <?php foreach ($byDepartment as $row): ?>
        <tr>
          <td><strong><?= e($row['department']) ?></strong></td>
          <td class="center"><?= (int)$row['total'] ?></td>
          <td class="center"><?= (int)$row['males'] ?></td>
          <td class="center"><?= (int)$row['females'] ?></td>
          <td class="center"><?= (int)$row['year1'] ?></td>
          <td class="center"><?= (int)$row['year2'] ?></td>
          <td class="center"><?= (int)$row['year3'] ?></td>
          <td class="center"><?= (int)$row['year4'] ?></td>
          <td>
            <div class="bar"><span style="width:<?= (int)round(100 * (int)$row['total'] / $maxRows) ?>%"></span></div>
            <small class="hint"><?= percent($total ? 100 * (int)$row['total'] / $total : 0, 1) ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($noDepartment): ?>
        <tr>
          <td><em>No department assigned</em></td>
          <td class="center"><?= $noDepartment ?></td>
          <td colspan="7"></td>
        </tr>
      <?php endif; ?>
      <?php if (!$byDepartment): ?>
        <tr><td colspan="9" class="table-empty">No departments have been created yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Students by status</h2><p>Every student on record</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Status</th><th class="center">Students</th><th>Share</th></tr></thead>
      <tbody>
      <?php
      $statusTotal = array_sum(array_map(static fn(array $row): int => (int)$row['total'], $byStatus));
      ?>
      <?php foreach ($byStatus as $row): ?>
        <tr>
          <td><?= status_badge($row['status']) ?></td>
          <td class="center"><?= (int)$row['total'] ?></td>
          <td>
            <div class="bar <?= $row['status'] === 'active' ? 'green' : 'gold' ?>">
              <span style="width:<?= $statusTotal ? (int)round(100 * (int)$row['total'] / $statusTotal) : 0 ?>%"></span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byStatus): ?>
        <tr><td colspan="3" class="table-empty">No students on record.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
