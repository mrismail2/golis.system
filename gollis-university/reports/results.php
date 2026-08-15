<?php
/** Reports - performance per course and the grade distribution. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);

$scope  = '';
$params = [PASS_MARK, $academicYear, $semester];
if (is_lecturer()) {
    $scope    = " AND c.lecturer_id = ?";
    $params[] = (int)current_lecturer_id();
}

$byCourse = db_all(
    "SELECT c.code, c.title, l.full_name AS lecturer,
            COUNT(r.id) AS graded,
            ROUND(AVG(r.total_marks), 1) AS average,
            MAX(r.total_marks) AS best,
            MIN(r.total_marks) AS worst,
            SUM(r.total_marks >= ?) AS passed
     FROM results r
     JOIN courses c        ON c.id = r.course_id
     LEFT JOIN lecturers l ON l.id = c.lecturer_id
     WHERE r.academic_year = ? AND r.semester = ?" . $scope . "
     GROUP BY c.id, c.code, c.title, l.full_name
     ORDER BY c.code",
    $params
);

$gradeParams = [$academicYear, $semester];
if (is_lecturer()) {
    $gradeParams[] = (int)current_lecturer_id();
}

$byGrade = db_all(
    "SELECT r.grade, COUNT(*) AS total
     FROM results r JOIN courses c ON c.id = r.course_id
     WHERE r.academic_year = ? AND r.semester = ?" . $scope . "
     GROUP BY r.grade
     ORDER BY FIELD(r.grade,'A+','A','A-','B+','B','C+','C','D','F')",
    $gradeParams
);

$topStudents = db_all(
    "SELECT s.reg_no, s.full_name, d.name AS department,
            ROUND(SUM(r.grade_points * c.credit_hours) / NULLIF(SUM(c.credit_hours),0), 2) AS gpa,
            ROUND(AVG(r.total_marks), 1) AS average,
            SUM(c.credit_hours) AS credits
     FROM results r
     JOIN students s         ON s.id = r.student_id
     JOIN courses  c         ON c.id = r.course_id
     LEFT JOIN departments d ON d.id = s.department_id
     WHERE r.academic_year = ? AND r.semester = ?" . $scope . "
     GROUP BY s.id, s.reg_no, s.full_name, d.name
     ORDER BY gpa DESC, average DESC
     LIMIT 10",
    $gradeParams
);

$graded    = array_sum(array_map(static fn(array $row): int => (int)$row['graded'], $byCourse));
$passed    = array_sum(array_map(static fn(array $row): int => (int)$row['passed'], $byCourse));
$maxGrade  = max(1, (int)max(array_column($byGrade, 'total') ?: [1]));

$pageTitle    = 'Results report';
$pageSubtitle = $academicYear . ' · Semester ' . $semester;
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
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= $value === $semester ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Apply</button></div>
    </form>
  </div>
</div>

<div class="tiles">
  <div class="tile"><div class="label">Graded results</div><div class="number"><?= number_format($graded) ?></div><div class="foot">Across <?= count($byCourse) ?> courses</div></div>
  <div class="tile green"><div class="label">Passed</div><div class="number"><?= number_format($passed) ?></div><div class="foot">Mark of <?= PASS_MARK ?> or more</div></div>
  <div class="tile red"><div class="label">Failed</div><div class="number"><?= number_format($graded - $passed) ?></div><div class="foot">Below the pass mark</div></div>
  <div class="tile gold"><div class="label">Pass rate</div><div class="number"><?= percent($graded ? 100 * $passed / $graded : 0, 1) ?></div><div class="foot">This term</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Performance per course</h2><p>Averages, best and lowest marks</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Course</th><th>Lecturer</th><th class="center">Graded</th><th class="center">Average</th>
                 <th class="center">Best</th><th class="center">Lowest</th><th>Pass rate</th></tr></thead>
      <tbody>
      <?php foreach ($byCourse as $row): ?>
        <?php $rate = (int)$row['graded'] ? 100 * (int)$row['passed'] / (int)$row['graded'] : 0; ?>
        <tr>
          <td><span class="pill"><?= e($row['code']) ?></span> <?= e($row['title']) ?></td>
          <td><?= e($row['lecturer'] ?: '-') ?></td>
          <td class="center"><?= (int)$row['graded'] ?></td>
          <td class="center"><strong><?= number_format((float)$row['average'], 1) ?></strong></td>
          <td class="center"><?= number_format((float)$row['best'], 1) ?></td>
          <td class="center"><?= number_format((float)$row['worst'], 1) ?></td>
          <td>
            <div class="bar <?= $rate >= 75 ? 'green' : ($rate >= 50 ? 'gold' : 'red') ?>">
              <span style="width:<?= (int)round($rate) ?>%"></span>
            </div>
            <small class="hint"><?= percent($rate, 1) ?></small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byCourse): ?>
        <tr><td colspan="7" class="table-empty">No results recorded for this term.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><div><h2>Grade distribution</h2><p>How many students earned each grade</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Grade</th><th class="center">Students</th><th>Share</th></tr></thead>
        <tbody>
        <?php foreach ($byGrade as $row): ?>
          <tr>
            <td><strong><?= e($row['grade']) ?></strong></td>
            <td class="center"><?= (int)$row['total'] ?></td>
            <td>
              <div class="bar <?= $row['grade'] === 'F' ? 'red' : 'green' ?>">
                <span style="width:<?= (int)round(100 * (int)$row['total'] / $maxGrade) ?>%"></span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$byGrade): ?>
          <tr><td colspan="3" class="table-empty">No grades to show.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>Top students</h2><p>Highest GPA this term</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Department</th><th class="center">GPA</th><th class="center">Average</th></tr></thead>
        <tbody>
        <?php foreach ($topStudents as $index => $row): ?>
          <tr>
            <td><?= $index + 1 ?></td>
            <td><?= e($row['full_name']) ?><br><small class="hint"><?= e($row['reg_no']) ?></small></td>
            <td><?= e($row['department'] ?: '-') ?></td>
            <td class="center"><strong><?= number_format((float)$row['gpa'], 2) ?></strong></td>
            <td class="center"><?= number_format((float)$row['average'], 1) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$topStudents): ?>
          <tr><td colspan="5" class="table-empty">No results recorded for this term.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
