<?php
/** Attendance - browse recorded sessions. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$courseId = get_int('course_id');
$from     = (string)input('from', date('Y-m-01'));
$to       = (string)input('to', date('Y-m-d'));
$status   = (string)input('status', '');

$sql = "SELECT a.*, s.reg_no, s.full_name, s.id AS student_id, c.code, c.title
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        JOIN courses  c ON c.id = a.course_id
        WHERE a.class_date BETWEEN ? AND ?";
$params = [$from, $to];

if ($courseId) {
    $sql .= " AND a.course_id = ?";
    $params[] = $courseId;
}
if ($status !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}
if (is_student()) {
    $sql .= " AND a.student_id = ?";
    $params[] = (int)current_student_id();
} elseif (is_lecturer()) {
    $sql .= " AND c.lecturer_id = ?";
    $params[] = (int)current_lecturer_id();
}

$sql .= " ORDER BY a.class_date DESC, c.code, s.reg_no";
$records = db_all($sql, $params);

$summary = [
    'present' => 0,
    'late'    => 0,
    'absent'  => 0,
    'excused' => 0,
];
foreach ($records as $record) {
    $summary[$record['status']] = ($summary[$record['status']] ?? 0) + 1;
}
$total = count($records);
$rate  = $total ? 100 * ($summary['present'] + $summary['late']) / $total : 0;

$courseOptions = is_lecturer() ? all_courses((int)current_lecturer_id()) : all_courses();

$pageTitle    = is_student() ? 'My attendance' : 'Attendance';
$pageSubtitle = 'Records between ' . fdate($from) . ' and ' . fdate($to);
$pageActions  = has_role('admin', 'lecturer')
    ? '<a class="btn-sm btn-blue" href="' . url('attendance/mark.php') . '">🗓️ Mark attendance</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="tiles">
  <div class="tile"><div class="label">Records</div><div class="number"><?= number_format($total) ?></div><div class="foot">In the selected period</div></div>
  <div class="tile green"><div class="label">Present</div><div class="number"><?= number_format($summary['present']) ?></div><div class="foot"><?= number_format($summary['late']) ?> late arrivals</div></div>
  <div class="tile red"><div class="label">Absent</div><div class="number"><?= number_format($summary['absent']) ?></div><div class="foot"><?= number_format($summary['excused']) ?> excused</div></div>
  <div class="tile gold"><div class="label">Attendance rate</div><div class="number"><?= percent($rate, 1) ?></div><div class="foot">Present or late</div></div>
</div>

<div class="panel">
  <div class="filters">
    <form method="get">
      <div class="field" style="min-width:250px">
        <label for="course_id">Course</label>
        <select id="course_id" name="course_id">
          <option value="">All courses</option>
          <?php foreach ($courseOptions as $course): ?>
            <option value="<?= (int)$course['id'] ?>" <?= (int)$course['id'] === $courseId ? 'selected' : '' ?>>
              <?= e($course['code'] . ' - ' . $course['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="from">From</label>
        <input id="from" name="from" type="date" value="<?= e($from) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input id="to" name="to" type="date" value="<?= e($to) ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All</option>
          <?php foreach (['present', 'late', 'absent', 'excused'] as $option): ?>
            <option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
      <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('attendance/index.php') ?>">Reset</a></div>
    </form>
  </div>

  <div class="panel-head">
    <div><h2><?= number_format($total) ?> attendance records</h2><p data-count-for="attendanceTable"><?= number_format($total) ?> records</p></div>
    <div class="field" style="min-width:220px">
      <input id="attSearch" onkeyup="filterTable('attSearch','attendanceTable')" placeholder="Search student or course...">
    </div>
  </div>

  <div class="table-wrap">
    <table id="attendanceTable">
      <thead>
        <tr><th>Date</th><?php if (!is_student()): ?><th>Student</th><?php endif; ?><th>Course</th><th>Status</th><th>Remarks</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr>
      </thead>
      <tbody>
      <?php foreach ($records as $record): ?>
        <tr>
          <td><?= e(fdate($record['class_date'])) ?></td>
          <?php if (!is_student()): ?>
            <td><a href="<?= url('students/view.php?id=' . (int)$record['student_id']) ?>"><?= e($record['full_name']) ?></a><br>
                <small class="hint"><?= e($record['reg_no']) ?></small></td>
          <?php endif; ?>
          <td><span class="pill"><?= e($record['code']) ?></span> <?= e($record['title']) ?></td>
          <td><?= status_badge($record['status']) ?></td>
          <td class="hint"><?= e($record['remarks'] ?: '') ?></td>
          <?php if (is_admin()): ?>
            <td>
              <a class="btn-sm btn-danger"
                 href="<?= url('attendance/delete.php?id=' . (int)$record['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete this attendance record?">Delete</a>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$records): ?>
        <tr data-empty><td colspan="<?= is_student() ? 4 : (is_admin() ? 6 : 5) ?>" class="table-empty">No attendance records for these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
