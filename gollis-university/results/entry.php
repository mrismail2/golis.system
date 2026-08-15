<?php
/** Results - mark sheet for one course and term. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$courses  = is_lecturer() ? all_courses((int)current_lecturer_id()) : all_courses();
$courseId = get_int('course_id') ?: post_int('course_id');

if (!$courseId && $courses) {
    $courseId = (int)$courses[0]['id'];
}

$academicYear = (string)input('academic_year', CURRENT_YEAR);
$semester     = (int)input('semester', CURRENT_SEMESTER);
$course       = $courseId ? db_row("SELECT * FROM courses WHERE id = ?", [$courseId]) : null;

if ($course) {
    require_course_access($courseId);
}

$maxCoursework = 40;
$maxExam       = 60;

// --- save the mark sheet ---------------------------------------------
if (is_post() && $course) {
    verify_csrf();

    $courseworkMarks = (array)($_POST['coursework'] ?? []);
    $examMarks       = (array)($_POST['exam_marks'] ?? []);
    $publish         = (string)input('publish') === '1';
    $saved           = 0;

    foreach ($courseworkMarks as $studentId => $courseworkValue) {
        $studentId  = (int)$studentId;
        $coursework = max(0, min($maxCoursework, (float)$courseworkValue));
        $exam       = max(0, min($maxExam, (float)($examMarks[$studentId] ?? 0)));
        $total      = round($coursework + $exam, 2);
        $grading    = grade_for($total);

        db_query(
            "INSERT INTO results (student_id, course_id, academic_year, semester, coursework, exam_marks,
                                  total_marks, grade, grade_points, is_published, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE coursework = VALUES(coursework), exam_marks = VALUES(exam_marks),
                                     total_marks = VALUES(total_marks), grade = VALUES(grade),
                                     grade_points = VALUES(grade_points), is_published = VALUES(is_published),
                                     recorded_by = VALUES(recorded_by)",
            [
                $studentId, $courseId, $academicYear, $semester,
                $coursework, $exam, $total, $grading['grade'], $grading['points'],
                $publish ? 1 : 0, user_id(),
            ]
        );
        $saved++;
    }

    flash('Marks saved for ' . $saved . ' student' . ($saved === 1 ? '' : 's')
        . ($publish ? ' and published to the students.' : ' as a draft.'));
    redirect('results/entry.php?course_id=' . $courseId
        . '&academic_year=' . urlencode($academicYear) . '&semester=' . $semester);
}

$sheet = $course
    ? db_all(
        "SELECT s.id, s.reg_no, s.full_name,
                r.coursework, r.exam_marks, r.total_marks, r.grade, r.is_published
         FROM enrollments en
         JOIN students s ON s.id = en.student_id
         LEFT JOIN results r ON r.student_id = s.id AND r.course_id = en.course_id
              AND r.academic_year = en.academic_year AND r.semester = en.semester
         WHERE en.course_id = ? AND en.academic_year = ? AND en.semester = ?
         ORDER BY s.reg_no",
        [$courseId, $academicYear, $semester]
    )
    : [];

$isPublished = (bool)array_reduce(
    $sheet,
    static fn(int $carry, array $row): int => $carry + (int)($row['is_published'] ?? 0),
    0
);

$pageTitle    = 'Enter results';
$pageSubtitle = $course
    ? $course['code'] . ' · ' . $course['title'] . ' · ' . $academicYear . ' · Semester ' . $semester
    : 'No course selected';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('results/index.php') . '">All results</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="filters">
    <form method="get" data-auto-submit>
      <div class="field" style="min-width:270px">
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
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Open mark sheet</button></div>
    </form>
  </div>

  <?php if (!$course): ?>
    <div class="alert warn">No course is available for your account.</div>
  <?php elseif (!$sheet): ?>
    <div class="alert warn">
      No students are enrolled in <?= e($course['code']) ?> for <?= e($academicYear) ?> semester <?= $semester ?>.
      <a href="<?= url('courses/enrollments.php?course_id=' . $courseId) ?>">Add students to the class list</a> first.
    </div>
  <?php else: ?>
    <div class="alert info">
      Coursework is out of <?= $maxCoursework ?>, the examination is out of <?= $maxExam ?>.
      The total, grade and grade points are worked out automatically. The pass mark is <?= PASS_MARK ?>.
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="academic_year" value="<?= e($academicYear) ?>">
      <input type="hidden" name="semester" value="<?= $semester ?>">

      <div class="panel-head">
        <div><h2><?= count($sheet) ?> students</h2><p>Enter the marks, then save the sheet</p></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Registration no</th><th>Student</th>
                <th class="center">Coursework (<?= $maxCoursework ?>)</th>
                <th class="center">Exam (<?= $maxExam ?>)</th>
                <th class="center">Current total</th><th class="center">Grade</th></tr>
          </thead>
          <tbody>
          <?php foreach ($sheet as $index => $row): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><span class="pill"><?= e($row['reg_no']) ?></span></td>
              <td><?= e($row['full_name']) ?></td>
              <td class="center">
                <input type="number" step="0.5" min="0" max="<?= $maxCoursework ?>" style="max-width:110px"
                       name="coursework[<?= (int)$row['id'] ?>]" value="<?= e(number_format((float)($row['coursework'] ?? 0), 1, '.', '')) ?>">
              </td>
              <td class="center">
                <input type="number" step="0.5" min="0" max="<?= $maxExam ?>" style="max-width:110px"
                       name="exam_marks[<?= (int)$row['id'] ?>]" value="<?= e(number_format((float)($row['exam_marks'] ?? 0), 1, '.', '')) ?>">
              </td>
              <td class="center"><?= $row['total_marks'] === null ? '-' : number_format((float)$row['total_marks'], 1) ?></td>
              <td class="center"><strong><?= e($row['grade'] ?? '-') ?></strong></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="form-actions">
        <label style="font-weight:600">
          <input type="checkbox" name="publish" value="1" style="width:auto" <?= $isPublished ? 'checked' : '' ?>>
          Publish these results to the students
        </label>
      </div>
      <div class="form-actions">
        <button class="btn-sm btn-blue" type="submit">Save mark sheet</button>
        <a class="btn-sm btn-ghost" href="<?= url('results/index.php?course_id=' . $courseId) ?>">Cancel</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
