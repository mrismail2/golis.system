<?php
/** Courses - catalog. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$departmentId = get_int('department_id');
$lecturerId   = get_int('lecturer_id');
$search       = (string)input('q', '');

$sql    = "SELECT c.*, d.name AS department, l.full_name AS lecturer,
                  (SELECT COUNT(*) FROM enrollments en WHERE en.course_id = c.id) AS enrolled
           FROM courses c
           LEFT JOIN departments d ON d.id = c.department_id
           LEFT JOIN lecturers  l ON l.id = c.lecturer_id
           WHERE 1 = 1";
$params = [];

if ($departmentId) {
    $sql .= " AND c.department_id = ?";
    $params[] = $departmentId;
}
if ($lecturerId) {
    $sql .= " AND c.lecturer_id = ?";
    $params[] = $lecturerId;
}
if ($search !== '') {
    $sql .= " AND (c.code LIKE ? OR c.title LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
}

// students only see the courses they are enrolled in
if (is_student()) {
    $sql .= " AND c.id IN (SELECT en.course_id FROM enrollments en WHERE en.student_id = ?)";
    $params[] = (int)current_student_id();
}

$sql .= " ORDER BY c.code";
$courses = db_all($sql, $params);

$pageTitle    = is_student() ? 'My courses' : 'Courses';
$pageSubtitle = 'Course catalog for ' . CURRENT_YEAR;
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('courses/form.php') . '">➕ New course</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <?php if (!is_student()): ?>
    <div class="filters">
      <form method="get" data-auto-submit>
        <div class="field">
          <label for="department_id">Department</label>
          <select id="department_id" name="department_id">
            <option value="">All departments</option>
            <?= options(all_departments(), 'id', 'name', $departmentId ?: null) ?>
          </select>
        </div>
        <div class="field">
          <label for="lecturer_id">Lecturer</label>
          <select id="lecturer_id" name="lecturer_id">
            <option value="">All lecturers</option>
            <?= options(all_lecturers(), 'id', 'full_name', $lecturerId ?: null) ?>
          </select>
        </div>
        <div class="field" style="min-width:220px">
          <label for="q">Search</label>
          <input id="q" name="q" value="<?= e($search) ?>" placeholder="Course code or title">
        </div>
        <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
        <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('courses/index.php') ?>">Reset</a></div>
      </form>
    </div>
  <?php endif; ?>

  <div class="panel-head"><div><h2><?= count($courses) ?> courses</h2><p>Credit hours, department and teaching staff</p></div></div>

  <div class="table-wrap">
    <table id="courseTable">
      <thead>
        <tr><th>Code</th><th>Course title</th><th>Department</th><th>Lecturer</th>
            <th class="center">Credits</th><th class="center">Year</th><th class="center">Students</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($courses as $course): ?>
        <tr>
          <td><span class="pill"><?= e($course['code']) ?></span></td>
          <td><strong><?= e($course['title']) ?></strong><br><small class="hint"><?= e($course['description'] ?: '') ?></small></td>
          <td><?= e($course['department'] ?: '-') ?></td>
          <td><?= e($course['lecturer'] ?: 'Not assigned') ?></td>
          <td class="center"><?= (int)$course['credit_hours'] ?></td>
          <td class="center"><?= (int)$course['year_level'] ?></td>
          <td class="center"><?= (int)$course['enrolled'] ?></td>
          <td>
            <?php if (!is_student()): ?>
              <a class="btn-sm btn-ghost" href="<?= url('courses/enrollments.php?course_id=' . (int)$course['id']) ?>">Class list</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
              <a class="btn-sm btn-blue" href="<?= url('courses/form.php?id=' . (int)$course['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('courses/delete.php?id=' . (int)$course['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete <?= e($course['code']) ?>? Enrollments, attendance and results for this course are deleted too.">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$courses): ?>
        <tr data-empty><td colspan="8" class="table-empty">No courses match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
