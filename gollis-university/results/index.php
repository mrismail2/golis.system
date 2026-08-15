<?php
/** Results - browse recorded marks. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$courseId     = get_int('course_id');
$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);
$search       = (string)input('q', '');

$sql = "SELECT r.*, s.reg_no, s.full_name, s.id AS student_id, c.code, c.title, c.credit_hours
        FROM results r
        JOIN students s ON s.id = r.student_id
        JOIN courses  c ON c.id = r.course_id
        WHERE r.academic_year = ? AND r.semester = ?";
$params = [$academicYear, $semester];

if ($courseId) {
    $sql .= " AND r.course_id = ?";
    $params[] = $courseId;
}
if ($search !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.reg_no LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
}
if (is_student()) {
    $sql .= " AND r.student_id = ? AND r.is_published = 1";
    $params[] = (int)current_student_id();
} elseif (is_lecturer()) {
    $sql .= " AND c.lecturer_id = ?";
    $params[] = (int)current_lecturer_id();
}

$sql .= " ORDER BY c.code, s.reg_no";
$results = db_all($sql, $params);

$passed  = 0;
$totals  = 0.0;
foreach ($results as $result) {
    $totals += (float)$result['total_marks'];
    if ((float)$result['total_marks'] >= PASS_MARK) {
        $passed++;
    }
}
$count    = count($results);
$average  = $count ? $totals / $count : 0;
$passRate = $count ? 100 * $passed / $count : 0;

$courseOptions = is_lecturer() ? all_courses((int)current_lecturer_id()) : all_courses();

$pageTitle    = is_student() ? 'My results' : 'Results';
$pageSubtitle = $academicYear . ' · Semester ' . $semester;
$pageActions  = has_role('admin', 'lecturer')
    ? '<a class="btn-sm btn-blue" href="' . url('results/entry.php') . '">📈 Enter results</a>'
    : '<a class="btn-sm btn-ghost" href="' . url('results/transcript.php') . '">My transcript</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="tiles">
  <div class="tile"><div class="label">Results</div><div class="number"><?= number_format($count) ?></div><div class="foot">Recorded this term</div></div>
  <div class="tile green"><div class="label">Passed</div><div class="number"><?= number_format($passed) ?></div><div class="foot">Mark of <?= PASS_MARK ?> or more</div></div>
  <div class="tile red"><div class="label">Failed</div><div class="number"><?= number_format($count - $passed) ?></div><div class="foot">Below the pass mark</div></div>
  <div class="tile gold"><div class="label">Average mark</div><div class="number"><?= number_format($average, 1) ?></div><div class="foot">Pass rate <?= percent($passRate, 1) ?></div></div>
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
      <?php if (!is_student()): ?>
        <div class="field" style="min-width:200px">
          <label for="q">Search student</label>
          <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or registration no">
        </div>
      <?php endif; ?>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
      <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('results/index.php') ?>">Reset</a></div>
    </form>
  </div>

  <div class="panel-head"><div><h2><?= number_format($count) ?> results</h2><p>Coursework, examination and final grade</p></div></div>

  <div class="table-wrap">
    <table id="resultTable">
      <thead>
        <tr>
          <?php if (!is_student()): ?><th>Student</th><?php endif; ?>
          <th>Course</th><th class="center">Coursework</th><th class="center">Exam</th>
          <th class="center">Total</th><th class="center">Grade</th><th>Status</th>
          <?php if (has_role('admin', 'lecturer')): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($results as $result): ?>
        <tr>
          <?php if (!is_student()): ?>
            <td><a href="<?= url('students/view.php?id=' . (int)$result['student_id']) ?>"><?= e($result['full_name']) ?></a><br>
                <small class="hint"><?= e($result['reg_no']) ?></small></td>
          <?php endif; ?>
          <td><span class="pill"><?= e($result['code']) ?></span> <?= e($result['title']) ?></td>
          <td class="center"><?= number_format((float)$result['coursework'], 1) ?></td>
          <td class="center"><?= number_format((float)$result['exam_marks'], 1) ?></td>
          <td class="center"><strong><?= number_format((float)$result['total_marks'], 1) ?></strong></td>
          <td class="center"><strong><?= e($result['grade']) ?></strong><br><small class="hint"><?= number_format((float)$result['grade_points'], 2) ?> pts</small></td>
          <td>
            <?= status_badge((float)$result['total_marks'] >= PASS_MARK ? 'pass' : 'fail') ?>
            <?php if (!(int)$result['is_published']): ?><span class="status warn">Draft</span><?php endif; ?>
          </td>
          <?php if (has_role('admin', 'lecturer')): ?>
            <td>
              <a class="btn-sm btn-ghost" href="<?= url('results/entry.php?course_id=' . (int)$result['course_id']
                  . '&academic_year=' . urlencode((string)$result['academic_year']) . '&semester=' . (int)$result['semester']) ?>">Edit sheet</a>
              <?php if (is_admin()): ?>
                <a class="btn-sm btn-danger"
                   href="<?= url('results/delete.php?id=' . (int)$result['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                   data-confirm="Delete this result?">Delete</a>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$results): ?>
        <tr data-empty><td colspan="8" class="table-empty">No results match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
