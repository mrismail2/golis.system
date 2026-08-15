<?php
/** Lecturers - profile with the courses they teach. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$id = get_int('id') ?: (int)(current_lecturer_id() ?? 0);

$lecturer = db_row(
    "SELECT l.*, d.name AS department, u.username
     FROM lecturers l
     LEFT JOIN departments d ON d.id = l.department_id
     LEFT JOIN users u       ON u.id = l.user_id
     WHERE l.id = ?",
    [$id]
);

if (!$lecturer) {
    flash('That lecturer no longer exists.', 'error');
    redirect('lecturers/index.php');
}

$courses = db_all(
    "SELECT c.*, d.name AS department,
            (SELECT COUNT(*) FROM enrollments en WHERE en.course_id = c.id) AS enrolled
     FROM courses c
     LEFT JOIN departments d ON d.id = c.department_id
     WHERE c.lecturer_id = ?
     ORDER BY c.code",
    [$id]
);

$students = (int)db_value(
    "SELECT COUNT(DISTINCT en.student_id)
     FROM enrollments en JOIN courses c ON c.id = en.course_id
     WHERE c.lecturer_id = ?",
    [$id],
    0
);

$attendanceRate = (float)db_value(
    "SELECT ROUND(100 * SUM(a.status IN ('present','late')) / NULLIF(COUNT(*),0), 1)
     FROM attendance a JOIN courses c ON c.id = a.course_id
     WHERE c.lecturer_id = ?",
    [$id],
    0
);

$averageMark = (float)db_value(
    "SELECT ROUND(AVG(r.total_marks), 1)
     FROM results r JOIN courses c ON c.id = r.course_id
     WHERE c.lecturer_id = ?",
    [$id],
    0
);

$pageTitle    = $lecturer['full_name'];
$pageSubtitle = $lecturer['staff_no'] . ' · ' . ($lecturer['department'] ?: 'No department');
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('lecturers/form.php?id=' . $id) . '">Edit</a>'
      . '<a class="btn-sm btn-ghost" href="' . url('lecturers/index.php') . '">← Back to list</a>'
    : '<a class="btn-sm btn-ghost" href="' . url('lecturers/index.php') . '">← Back to list</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="profile-head">
    <img src="<?= e(photo_url($lecturer['photo'])) ?>" alt="<?= e($lecturer['full_name']) ?>">
    <div style="flex:1;min-width:260px">
      <h2><?= e($lecturer['full_name']) ?> <?= status_badge($lecturer['status']) ?></h2>
      <dl class="data-list">
        <div><dt>Staff number</dt><dd><?= e($lecturer['staff_no']) ?></dd></div>
        <div><dt>Department</dt><dd><?= e($lecturer['department'] ?: '-') ?></dd></div>
        <div><dt>Specialization</dt><dd><?= e($lecturer['specialization'] ?: '-') ?></dd></div>
        <div><dt>Qualification</dt><dd><?= e($lecturer['qualification'] ?: '-') ?></dd></div>
        <div><dt>Email</dt><dd><?= e($lecturer['email'] ?: '-') ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($lecturer['phone'] ?: '-') ?></dd></div>
        <div><dt>Hired on</dt><dd><?= e(fdate($lecturer['hired_on'])) ?></dd></div>
        <div><dt>Portal account</dt><dd><?= e($lecturer['username'] ?: 'Not created') ?></dd></div>
      </dl>
    </div>
  </div>
</div>

<div class="tiles">
  <div class="tile"><div class="label">Courses</div><div class="number"><?= count($courses) ?></div><div class="foot">Currently assigned</div></div>
  <div class="tile gold"><div class="label">Students</div><div class="number"><?= number_format($students) ?></div><div class="foot">Enrolled in these courses</div></div>
  <div class="tile green"><div class="label">Attendance</div><div class="number"><?= percent($attendanceRate, 1) ?></div><div class="foot">All recorded sessions</div></div>
  <div class="tile red"><div class="label">Average mark</div><div class="number"><?= number_format($averageMark, 1) ?></div><div class="foot">Across graded courses</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Courses taught</h2><p>Assigned to <?= e($lecturer['full_name']) ?></p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Code</th><th>Course</th><th>Department</th><th class="center">Credits</th><th class="center">Students</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($courses as $course): ?>
        <tr>
          <td><span class="pill"><?= e($course['code']) ?></span></td>
          <td><?= e($course['title']) ?></td>
          <td><?= e($course['department'] ?: '-') ?></td>
          <td class="center"><?= (int)$course['credit_hours'] ?></td>
          <td class="center"><?= (int)$course['enrolled'] ?></td>
          <td>
            <a class="btn-sm btn-ghost" href="<?= url('courses/enrollments.php?course_id=' . (int)$course['id']) ?>">Class list</a>
            <a class="btn-sm btn-ghost" href="<?= url('attendance/mark.php?course_id=' . (int)$course['id']) ?>">Attendance</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$courses): ?>
        <tr><td colspan="6" class="table-empty">No courses are assigned to this lecturer.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
