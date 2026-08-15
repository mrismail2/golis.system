<?php
/** Attendance - daily register for one course. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$courses  = is_lecturer() ? all_courses((int)current_lecturer_id()) : all_courses();
$courseId = get_int('course_id') ?: post_int('course_id');

if (!$courseId && $courses) {
    $courseId = (int)$courses[0]['id'];
}

$classDate = (string)input('class_date', date('Y-m-d'));
$course    = $courseId ? db_row("SELECT * FROM courses WHERE id = ?", [$courseId]) : null;

if ($course) {
    require_course_access($courseId);
}

// --- save the register ----------------------------------------------
if (is_post() && $course) {
    verify_csrf();

    $statuses = (array)($_POST['status'] ?? []);
    $remarks  = (array)($_POST['remarks'] ?? []);
    $allowed  = ['present', 'absent', 'late', 'excused'];
    $saved    = 0;

    foreach ($statuses as $studentId => $value) {
        $studentId = (int)$studentId;
        $value     = in_array($value, $allowed, true) ? $value : 'present';
        $remark    = trim((string)($remarks[$studentId] ?? ''));

        db_query(
            "INSERT INTO attendance (student_id, course_id, class_date, status, remarks, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), recorded_by = VALUES(recorded_by)",
            [$studentId, $courseId, $classDate, $value, $remark ?: null, user_id()]
        );
        $saved++;
    }

    flash('Attendance saved for ' . $saved . ' student' . ($saved === 1 ? '' : 's') . ' on ' . fdate($classDate) . '.');
    redirect('attendance/mark.php?course_id=' . $courseId . '&class_date=' . urlencode($classDate));
}

$register = $course
    ? db_all(
        "SELECT s.id, s.reg_no, s.full_name, a.status, a.remarks
         FROM enrollments en
         JOIN students s ON s.id = en.student_id
         LEFT JOIN attendance a ON a.student_id = s.id AND a.course_id = en.course_id AND a.class_date = ?
         WHERE en.course_id = ?
         GROUP BY s.id, s.reg_no, s.full_name, a.status, a.remarks
         ORDER BY s.reg_no",
        [$classDate, $courseId]
    )
    : [];

$alreadyMarked = (int)db_value(
    "SELECT COUNT(*) FROM attendance WHERE course_id = ? AND class_date = ?",
    [$courseId, $classDate],
    0
);

$pageTitle    = 'Mark attendance';
$pageSubtitle = $course ? $course['code'] . ' · ' . $course['title'] . ' · ' . fdate($classDate) : 'No course selected';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('attendance/index.php') . '">All records</a>';

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
        <label for="class_date">Class date</label>
        <input id="class_date" name="class_date" type="date" value="<?= e($classDate) ?>">
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Open register</button></div>
    </form>
  </div>

  <?php if (!$course): ?>
    <div class="alert warn">No course is available for your account.</div>
  <?php elseif (!$register): ?>
    <div class="alert warn">
      No students are enrolled in <?= e($course['code']) ?> yet.
      <a href="<?= url('courses/enrollments.php?course_id=' . $courseId) ?>">Add students to the class list</a> first.
    </div>
  <?php else: ?>
    <?php if ($alreadyMarked): ?>
      <div class="alert info">Attendance for <?= e(fdate($classDate)) ?> was already recorded. Saving again updates the existing entries.</div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="class_date" value="<?= e($classDate) ?>">

      <div class="panel-head">
        <div><h2><?= count($register) ?> students</h2><p>Choose a status for each student, then save the register</p></div>
        <div class="field" style="min-width:200px">
          <label for="checkAll">Set everyone to</label>
          <select id="checkAll" data-check-all="attendance">
            <option value="">Choose...</option>
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="late">Late</option>
            <option value="excused">Excused</option>
          </select>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Registration no</th><th>Student</th>
                <th class="center">Present</th><th class="center">Absent</th><th class="center">Late</th><th class="center">Excused</th>
                <th>Remarks</th></tr>
          </thead>
          <tbody>
          <?php foreach ($register as $index => $row): ?>
            <?php $current = $row['status'] ?: 'present'; ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><span class="pill"><?= e($row['reg_no']) ?></span></td>
              <td><?= e($row['full_name']) ?></td>
              <?php foreach (['present', 'absent', 'late', 'excused'] as $option): ?>
                <td class="center">
                  <input type="radio" style="width:auto"
                         name="status[<?= (int)$row['id'] ?>]" value="<?= $option ?>"
                         data-bulk="attendance" <?= $current === $option ? 'checked' : '' ?>>
                </td>
              <?php endforeach; ?>
              <td><input name="remarks[<?= (int)$row['id'] ?>]" value="<?= e($row['remarks'] ?? '') ?>" maxlength="160" placeholder="Optional"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="form-actions">
        <button class="btn-sm btn-blue" type="submit">Save register</button>
        <a class="btn-sm btn-ghost" href="<?= url('attendance/index.php?course_id=' . $courseId) ?>">Cancel</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
