<?php
/** Examination timetable. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);

$sql = "SELECT e.*, c.code, c.title, d.name AS department, l.full_name AS lecturer
        FROM exams e
        JOIN courses c          ON c.id = e.course_id
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN lecturers  l  ON l.id = c.lecturer_id
        WHERE e.academic_year = ? AND e.semester = ?";
$params = [$academicYear, $semester];

if (is_student()) {
    $sql .= " AND c.id IN (SELECT en.course_id FROM enrollments en WHERE en.student_id = ?)";
    $params[] = (int)current_student_id();
} elseif (is_lecturer()) {
    $sql .= " AND (c.lecturer_id = ? OR ? = 0)";
    $lecturerId = (int)(current_lecturer_id() ?? 0);
    array_push($params, $lecturerId, $lecturerId);
}

$sql .= " ORDER BY e.exam_date, e.start_time";
$exams = db_all($sql, $params);

$pageTitle    = 'Examinations';
$pageSubtitle = 'Timetable for ' . $academicYear . ' · Semester ' . $semester;
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('courses/exam_form.php') . '">➕ Schedule exam</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
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
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Show</button></div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-ghost no-print" type="button" onclick="window.print()">🖨️ Print</button></div>
    </form>
  </div>

  <div class="panel-head"><div><h2><?= count($exams) ?> examinations</h2><p><?= is_student() ? 'Sittings for your enrolled courses' : 'All scheduled sittings' ?></p></div></div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Course</th><th>Department</th><th>Time</th><th class="center">Duration</th><th>Room</th><th>Invigilator</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr>
      </thead>
      <tbody>
      <?php foreach ($exams as $exam): ?>
        <tr>
          <td><?= e(fdate($exam['exam_date'])) ?><br><small class="hint"><?= e(fdate($exam['exam_date'], 'l')) ?></small></td>
          <td><span class="pill"><?= e($exam['code']) ?></span> <?= e($exam['title']) ?></td>
          <td><?= e($exam['department'] ?: '-') ?></td>
          <td><?= e(ftime($exam['start_time'])) ?></td>
          <td class="center"><?= (int)$exam['duration_mins'] ?> min</td>
          <td><?= e($exam['room'] ?: '-') ?></td>
          <td><?= e($exam['lecturer'] ?: '-') ?></td>
          <?php if (is_admin()): ?>
            <td>
              <a class="btn-sm btn-blue" href="<?= url('courses/exam_form.php?id=' . (int)$exam['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('courses/exam_delete.php?id=' . (int)$exam['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Remove this exam from the timetable?">Delete</a>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$exams): ?>
        <tr><td colspan="<?= is_admin() ? 8 : 7 ?>" class="table-empty">No examinations have been scheduled for this term.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
