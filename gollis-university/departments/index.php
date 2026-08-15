<?php
/** Departments - list. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$departments = db_all(
    "SELECT d.*,
            (SELECT COUNT(*) FROM students  s WHERE s.department_id  = d.id AND s.status = 'active') AS students,
            (SELECT COUNT(*) FROM lecturers l WHERE l.department_id = d.id AND l.status = 'active') AS lecturers,
            (SELECT COUNT(*) FROM courses   c WHERE c.department_id = d.id) AS courses
     FROM departments d
     ORDER BY d.name"
);

$pageTitle    = 'Departments';
$pageSubtitle = 'Faculties and academic programs of the campus';
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('departments/form.php') . '">➕ New department</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <div><h2><?= count($departments) ?> departments</h2><p>Students, staff and courses per department</p></div>
    <div class="field" style="min-width:240px">
      <input id="deptSearch" onkeyup="filterTable('deptSearch','deptTable')" placeholder="Search department...">
    </div>
  </div>

  <div class="table-wrap">
    <table id="deptTable">
      <thead>
        <tr>
          <th>Code</th><th>Department</th><th>Description</th>
          <th class="center">Students</th><th class="center">Lecturers</th><th class="center">Courses</th>
          <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($departments as $department): ?>
        <tr>
          <td><span class="pill"><?= e($department['code']) ?></span></td>
          <td><?= e($department['icon']) ?> <strong><?= e($department['name']) ?></strong></td>
          <td class="hint"><?= e($department['description']) ?></td>
          <td class="center"><?= (int)$department['students'] ?></td>
          <td class="center"><?= (int)$department['lecturers'] ?></td>
          <td class="center"><?= (int)$department['courses'] ?></td>
          <?php if (is_admin()): ?>
            <td>
              <a class="btn-sm btn-ghost" href="<?= url('departments/form.php?id=' . (int)$department['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('departments/delete.php?id=' . (int)$department['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete <?= e($department['name']) ?>? Students and courses stay, but lose their department.">Delete</a>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$departments): ?>
        <tr data-empty><td colspan="<?= is_admin() ? 7 : 6 ?>" class="table-empty">No departments have been added yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
