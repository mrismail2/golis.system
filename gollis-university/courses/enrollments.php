<?php
/** Courses - class list: who is enrolled in one course. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$courseId = get_int('course_id') ?: post_int('course_id');
$courses  = is_admin() ? all_courses() : all_courses((int)current_lecturer_id());

if (!$courseId && $courses) {
    $courseId = (int)$courses[0]['id'];
}

$course = $courseId
    ? db_row(
        "SELECT c.*, d.name AS department, l.full_name AS lecturer
         FROM courses c
         LEFT JOIN departments d ON d.id = c.department_id
         LEFT JOIN lecturers  l ON l.id = c.lecturer_id
         WHERE c.id = ?",
        [$courseId]
    )
    : null;

if ($course) {
    require_course_access($courseId);
}

$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);

// --- add / remove students (admin only) ------------------------------
if (is_post() && is_admin() && $course) {
    verify_csrf();

    if (input('action') === 'add') {
        $studentIds = array_map('intval', (array)($_POST['student_ids'] ?? []));
        $added      = 0;

        foreach ($studentIds as $studentId) {
            if ($studentId <= 0) {
                continue;
            }
            $stmt = db_query(
                "INSERT IGNORE INTO enrollments (student_id, course_id, academic_year, semester, enrolled_on)
                 VALUES (?, ?, ?, ?, CURDATE())",
                [$studentId, $courseId, $academicYear, $semester]
            );
            $added += $stmt->rowCount();
        }

        flash($added . ' student' . ($added === 1 ? '' : 's') . ' enrolled in ' . $course['code'] . '.');
    } elseif (input('action') === 'remove') {
        db_query("DELETE FROM enrollments WHERE id = ? AND course_id = ?", [post_int('enrollment_id'), $courseId]);
        flash('Student removed from the class list.');
    }

    redirect('courses/enrollments.php?course_id=' . $courseId
        . '&academic_year=' . urlencode($academicYear) . '&semester=' . $semester);
}

$classList = $course
    ? db_all(
        "SELECT en.id, en.academic_year, en.semester, s.id AS student_id, s.reg_no, s.full_name,
                s.year_of_study, d.name AS department
         FROM enrollments en
         JOIN students s        ON s.id = en.student_id
         LEFT JOIN departments d ON d.id = s.department_id
         WHERE en.course_id = ? AND en.academic_year = ? AND en.semester = ?
         ORDER BY s.reg_no",
        [$courseId, $academicYear, $semester]
    )
    : [];

$available = ($course && is_admin())
    ? db_all(
        "SELECT s.id, s.reg_no, s.full_name, s.year_of_study, d.name AS department
         FROM students s
         LEFT JOIN departments d ON d.id = s.department_id
         WHERE s.status = 'active'
           AND s.id NOT IN (
               SELECT en.student_id FROM enrollments en
               WHERE en.course_id = ? AND en.academic_year = ? AND en.semester = ?
           )
         ORDER BY s.reg_no",
        [$courseId, $academicYear, $semester]
    )
    : [];

$pageTitle    = 'Class list';
$pageSubtitle = $course ? $course['code'] . ' · ' . $course['title'] : 'No course selected';
$pageActions  = $course
    ? '<a class="btn-sm btn-ghost" href="' . url('attendance/mark.php?course_id=' . $courseId) . '">Mark attendance</a>'
      . '<a class="btn-sm btn-ghost" href="' . url('results/entry.php?course_id=' . $courseId) . '">Enter results</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="filters">
    <form method="get" data-auto-submit>
      <div class="field" style="min-width:260px">
        <label for="course_id">Course</label>
        <select id="course_id" name="course_id">
          <?php foreach ($courses as $option): ?>
            <option value="<?= (int)$option['id'] ?>" <?= (int)$option['id'] === $courseId ? 'selected' : '' ?>>
              <?= e($option['code'] . ' - ' . $option['title']) ?>
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
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Show</button></div>
    </form>
  </div>

  <?php if (!$course): ?>
    <div class="alert warn">No course is available for your account.</div>
  <?php else: ?>
    <div class="panel-head">
      <div><h2><?= count($classList) ?> enrolled students</h2>
           <p><?= e($course['code']) ?> · <?= e($academicYear) ?> · Semester <?= $semester ?> · Lecturer: <?= e($course['lecturer'] ?: 'not assigned') ?></p></div>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Registration no</th><th>Student</th><th>Department</th><th class="center">Year</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($classList as $index => $row): ?>
          <tr>
            <td><?= $index + 1 ?></td>
            <td><span class="pill"><?= e($row['reg_no']) ?></span></td>
            <td><a href="<?= url('students/view.php?id=' . (int)$row['student_id']) ?>"><?= e($row['full_name']) ?></a></td>
            <td><?= e($row['department'] ?: '-') ?></td>
            <td class="center">Year <?= (int)$row['year_of_study'] ?></td>
            <?php if (is_admin()): ?>
              <td>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="course_id" value="<?= $courseId ?>">
                  <input type="hidden" name="academic_year" value="<?= e($academicYear) ?>">
                  <input type="hidden" name="semester" value="<?= $semester ?>">
                  <input type="hidden" name="enrollment_id" value="<?= (int)$row['id'] ?>">
                  <button class="btn-sm btn-danger" type="submit" data-confirm="Remove <?= e($row['full_name']) ?> from this class?">Remove</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$classList): ?>
          <tr><td colspan="<?= is_admin() ? 6 : 5 ?>" class="table-empty">Nobody is enrolled for this term yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($course && is_admin()): ?>
  <div class="panel">
    <div class="panel-head"><div><h2>Enroll students</h2><p>Tick the students to add to <?= e($course['code']) ?></p></div></div>

    <?php if (!$available): ?>
      <p class="hint">Every active student is already enrolled in this course for the selected term.</p>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <input type="hidden" name="academic_year" value="<?= e($academicYear) ?>">
        <input type="hidden" name="semester" value="<?= $semester ?>">

        <div class="table-wrap">
          <table>
            <thead><tr><th class="center">Add</th><th>Registration no</th><th>Student</th><th>Department</th><th class="center">Year</th></tr></thead>
            <tbody>
            <?php foreach ($available as $row): ?>
              <tr>
                <td class="center"><input type="checkbox" name="student_ids[]" value="<?= (int)$row['id'] ?>" style="width:auto"></td>
                <td><?= e($row['reg_no']) ?></td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['department'] ?: '-') ?></td>
                <td class="center">Year <?= (int)$row['year_of_study'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="form-actions">
          <button class="btn-sm btn-blue" type="submit">Enroll selected students</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
