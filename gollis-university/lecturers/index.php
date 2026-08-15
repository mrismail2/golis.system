<?php
/** Lecturers - directory. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$departmentId = get_int('department_id');
$search       = (string)input('q', '');

$sql    = "SELECT l.*, d.name AS department,
                  (SELECT COUNT(*) FROM courses c WHERE c.lecturer_id = l.id) AS courses
           FROM lecturers l
           LEFT JOIN departments d ON d.id = l.department_id
           WHERE 1 = 1";
$params = [];

if ($departmentId) {
    $sql .= " AND l.department_id = ?";
    $params[] = $departmentId;
}
if ($search !== '') {
    $sql .= " AND (l.full_name LIKE ? OR l.staff_no LIKE ? OR l.specialization LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql .= " ORDER BY l.staff_no";
$lecturers = db_all($sql, $params);

$pageTitle    = 'Lecturers';
$pageSubtitle = 'Academic staff of ' . APP_CAMPUS;
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('lecturers/form.php') . '">➕ Add lecturer</a>'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="filters">
    <form method="get" data-auto-submit>
      <div class="field">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">All departments</option>
          <?= options(all_departments(), 'id', 'name', $departmentId ?: null) ?>
        </select>
      </div>
      <div class="field" style="min-width:240px">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name, staff number or specialization">
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
      <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('lecturers/index.php') ?>">Reset</a></div>
    </form>
  </div>

  <div class="panel-head"><div><h2><?= count($lecturers) ?> lecturers</h2><p>Faculty directory</p></div></div>

  <div class="table-wrap">
    <table id="lecturerTable">
      <thead>
        <tr><th>Staff no</th><th>Name</th><th>Department</th><th>Specialization</th>
            <th class="center">Courses</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($lecturers as $lecturer): ?>
        <tr>
          <td><span class="pill"><?= e($lecturer['staff_no']) ?></span></td>
          <td><strong><?= e($lecturer['full_name']) ?></strong><br><small class="hint"><?= e($lecturer['qualification'] ?: '') ?></small></td>
          <td><?= e($lecturer['department'] ?: '-') ?></td>
          <td><?= e($lecturer['specialization'] ?: '-') ?></td>
          <td class="center"><?= (int)$lecturer['courses'] ?></td>
          <td><?= status_badge($lecturer['status']) ?></td>
          <td>
            <a class="btn-sm btn-ghost" href="<?= url('lecturers/view.php?id=' . (int)$lecturer['id']) ?>">View</a>
            <?php if (is_admin()): ?>
              <a class="btn-sm btn-blue" href="<?= url('lecturers/form.php?id=' . (int)$lecturer['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('lecturers/delete.php?id=' . (int)$lecturer['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete <?= e($lecturer['full_name']) ?>? Their courses will be left without a lecturer.">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lecturers): ?>
        <tr data-empty><td colspan="7" class="table-empty">No lecturers match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
