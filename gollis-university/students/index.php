<?php
/** Students - list with filters. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_staff();

$departmentId = get_int('department_id');
$year         = get_int('year');
$status       = (string)input('status', '');
$search       = (string)input('q', '');

$sql    = "SELECT s.*, d.name AS department
           FROM students s
           LEFT JOIN departments d ON d.id = s.department_id
           WHERE 1 = 1";
$params = [];

if ($departmentId) {
    $sql .= " AND s.department_id = ?";
    $params[] = $departmentId;
}
if ($year) {
    $sql .= " AND s.year_of_study = ?";
    $params[] = $year;
}
if ($status !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql .= " ORDER BY s.reg_no";
$students = db_all($sql, $params);

$pageTitle    = 'Students';
$pageSubtitle = 'Student register of ' . APP_CAMPUS;
$pageActions  = is_admin()
    ? '<a class="btn-sm btn-blue" href="' . url('students/form.php') . '">➕ Register student</a>'
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
      <div class="field">
        <label for="year">Year of study</label>
        <select id="year" name="year">
          <option value="">All years</option>
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?= $i ?>" <?= $year === $i ? 'selected' : '' ?>>Year <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">All</option>
          <?php foreach (['active', 'graduated', 'suspended', 'withdrawn'] as $option): ?>
            <option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="min-width:220px">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name, registration no or email">
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <button class="btn-sm btn-blue" type="submit">Filter</button>
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <a class="btn-sm btn-ghost" href="<?= url('students/index.php') ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="panel-head">
    <div><h2><?= count($students) ?> students</h2><p data-count-for="studentTable"><?= count($students) ?> records</p></div>
  </div>

  <div class="table-wrap">
    <table id="studentTable">
      <thead>
        <tr>
          <th>Registration no</th><th>Student name</th><th>Department</th>
          <th class="center">Year</th><th>Contact</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($students as $student): ?>
        <tr>
          <td><span class="pill"><?= e($student['reg_no']) ?></span></td>
          <td><strong><?= e($student['full_name']) ?></strong><br><small class="hint"><?= e(ucfirst((string)$student['gender'])) ?></small></td>
          <td><?= e($student['department'] ?: '-') ?></td>
          <td class="center">Year <?= (int)$student['year_of_study'] ?></td>
          <td><small><?= e($student['email'] ?: '-') ?><br><?= e($student['phone'] ?: '') ?></small></td>
          <td><?= status_badge($student['status']) ?></td>
          <td>
            <a class="btn-sm btn-ghost" href="<?= url('students/view.php?id=' . (int)$student['id']) ?>">View</a>
            <?php if (is_admin()): ?>
              <a class="btn-sm btn-blue" href="<?= url('students/form.php?id=' . (int)$student['id']) ?>">Edit</a>
              <a class="btn-sm btn-danger"
                 href="<?= url('students/delete.php?id=' . (int)$student['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete <?= e($student['full_name']) ?> and all their attendance, results and fee records?">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?>
        <tr data-empty><td colspan="7" class="table-empty">No students match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
