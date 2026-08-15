<?php
/** Reports - attendance rate per course and the students most often absent. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$from = (string)input('from', date('Y-m-d', strtotime('-90 days')));
$to   = (string)input('to', date('Y-m-d'));

$scope  = '';
$params = [$from, $to];
if (is_lecturer()) {
    $scope    = " AND c.lecturer_id = ?";
    $params[] = (int)current_lecturer_id();
}

$byCourse = db_all(
    "SELECT c.code, c.title, l.full_name AS lecturer,
            COUNT(a.id) AS sessions,
            SUM(a.status = 'present') AS present,
            SUM(a.status = 'late')    AS late,
            SUM(a.status = 'absent')  AS absent,
            SUM(a.status = 'excused') AS excused
     FROM courses c
     LEFT JOIN attendance a ON a.course_id = c.id AND a.class_date BETWEEN ? AND ?
     LEFT JOIN lecturers  l ON l.id = c.lecturer_id
     WHERE 1 = 1" . $scope . "
     GROUP BY c.id, c.code, c.title, l.full_name
     ORDER BY c.code",
    $params
);

$absentees = db_all(
    "SELECT s.reg_no, s.full_name, d.name AS department,
            COUNT(a.id) AS sessions,
            SUM(a.status = 'absent') AS absences,
            ROUND(100 * SUM(a.status IN ('present','late')) / NULLIF(COUNT(a.id),0), 1) AS rate
     FROM attendance a
     JOIN students s         ON s.id = a.student_id
     JOIN courses  c         ON c.id = a.course_id
     LEFT JOIN departments d ON d.id = s.department_id
     WHERE a.class_date BETWEEN ? AND ?" . $scope . "
     GROUP BY s.id, s.reg_no, s.full_name, d.name
     HAVING absences > 0
     ORDER BY absences DESC, rate ASC
     LIMIT 15",
    $params
);

$totalSessions = array_sum(array_map(static fn(array $row): int => (int)$row['sessions'], $byCourse));
$totalAttended = array_sum(array_map(
    static fn(array $row): int => (int)$row['present'] + (int)$row['late'],
    $byCourse
));
$overallRate = $totalSessions ? 100 * $totalAttended / $totalSessions : 0;

$pageTitle    = 'Attendance report';
$pageSubtitle = fdate($from) . ' to ' . fdate($to);
$pageActions  = '<button class="btn-sm btn-ghost no-print" type="button" onclick="window.print()">🖨️ Print</button>'
    . '<a class="btn-sm btn-ghost" href="' . url('reports/index.php') . '">← All reports</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel no-print">
  <div class="filters">
    <form method="get">
      <div class="field"><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div>
      <div class="field"><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Apply</button></div>
    </form>
  </div>
</div>

<div class="tiles">
  <div class="tile"><div class="label">Records</div><div class="number"><?= number_format($totalSessions) ?></div><div class="foot">Attendance entries in the period</div></div>
  <div class="tile green"><div class="label">Attended</div><div class="number"><?= number_format($totalAttended) ?></div><div class="foot">Present or late</div></div>
  <div class="tile red"><div class="label">Missed</div><div class="number"><?= number_format($totalSessions - $totalAttended) ?></div><div class="foot">Absent or excused</div></div>
  <div class="tile gold"><div class="label">Overall rate</div><div class="number"><?= percent($overallRate, 1) ?></div><div class="foot">Across all courses</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Attendance per course</h2><p>Recorded sessions in the selected period</p></div></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Course</th><th>Lecturer</th><th class="center">Records</th><th class="center">Present</th>
            <th class="center">Late</th><th class="center">Absent</th><th class="center">Excused</th><th>Rate</th></tr>
      </thead>
      <tbody>
      <?php foreach ($byCourse as $row): ?>
        <?php
        $sessions = (int)$row['sessions'];
        $rate     = $sessions ? 100 * ((int)$row['present'] + (int)$row['late']) / $sessions : 0;
        ?>
        <tr>
          <td><span class="pill"><?= e($row['code']) ?></span> <?= e($row['title']) ?></td>
          <td><?= e($row['lecturer'] ?: '-') ?></td>
          <td class="center"><?= $sessions ?></td>
          <td class="center"><?= (int)$row['present'] ?></td>
          <td class="center"><?= (int)$row['late'] ?></td>
          <td class="center"><?= (int)$row['absent'] ?></td>
          <td class="center"><?= (int)$row['excused'] ?></td>
          <td>
            <div class="bar <?= $rate >= 75 ? 'green' : ($rate >= 50 ? 'gold' : 'red') ?>">
              <span style="width:<?= (int)round($rate) ?>%"></span>
            </div>
            <small class="hint"><?= $sessions ? percent($rate, 1) : 'no records' ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byCourse): ?>
        <tr><td colspan="8" class="table-empty">No courses to report on.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Students with the most absences</h2><p>Top 15 in the selected period</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Registration no</th><th>Student</th><th>Department</th><th class="center">Records</th><th class="center">Absences</th><th>Attendance rate</th></tr></thead>
      <tbody>
      <?php foreach ($absentees as $row): ?>
        <tr>
          <td><span class="pill"><?= e($row['reg_no']) ?></span></td>
          <td><?= e($row['full_name']) ?></td>
          <td><?= e($row['department'] ?: '-') ?></td>
          <td class="center"><?= (int)$row['sessions'] ?></td>
          <td class="center"><strong><?= (int)$row['absences'] ?></strong></td>
          <td>
            <div class="bar <?= (float)$row['rate'] >= 75 ? 'green' : ((float)$row['rate'] >= 50 ? 'gold' : 'red') ?>">
              <span style="width:<?= (int)round((float)$row['rate']) ?>%"></span>
            </div>
            <small class="hint"><?= percent((float)$row['rate'], 1) ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$absentees): ?>
        <tr><td colspan="6" class="table-empty">No absences were recorded in this period.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
