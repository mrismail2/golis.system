<?php
/** Departments - create and edit. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id         = get_int('id');
$department = ['code' => '', 'name' => '', 'icon' => '🎓', 'description' => ''];
$errors     = [];

if ($id) {
    $found = db_row("SELECT * FROM departments WHERE id = ?", [$id]);
    if (!$found) {
        flash('That department no longer exists.', 'error');
        redirect('departments/index.php');
    }
    $department = $found;
}

if (is_post()) {
    verify_csrf();

    $department['code']        = strtoupper((string)input('code'));
    $department['name']        = (string)input('name');
    $department['icon']        = (string)input('icon');
    $department['description'] = (string)input('description');

    if ($department['code'] === '')  { $errors[] = 'The department code is required.'; }
    if ($department['name'] === '')  { $errors[] = 'The department name is required.'; }

    $clash = (int)db_value(
        "SELECT COUNT(*) FROM departments WHERE code = ? AND id <> ?",
        [$department['code'], $id],
        0
    );
    if ($clash) {
        $errors[] = 'Another department already uses the code ' . $department['code'] . '.';
    }

    if (!$errors) {
        if ($id) {
            db_query(
                "UPDATE departments SET code = ?, name = ?, icon = ?, description = ? WHERE id = ?",
                [$department['code'], $department['name'], $department['icon'], $department['description'], $id]
            );
            flash('Department updated.');
        } else {
            db_query(
                "INSERT INTO departments (code, name, icon, description) VALUES (?, ?, ?, ?)",
                [$department['code'], $department['name'], $department['icon'], $department['description']]
            );
            flash('Department created.');
        }
        redirect('departments/index.php');
    }
}

$pageTitle    = $id ? 'Edit department' : 'New department';
$pageSubtitle = 'Faculties and academic programs';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('departments/index.php') . '">← Back to list</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:760px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label for="code">Department code</label>
        <input id="code" name="code" maxlength="10" value="<?= e($department['code']) ?>" placeholder="CS" required>
      </div>
      <div class="field">
        <label for="icon">Icon (emoji)</label>
        <input id="icon" name="icon" maxlength="10" value="<?= e($department['icon']) ?>" placeholder="💻">
      </div>
    </div>
    <div class="field">
      <label for="name">Department name</label>
      <input id="name" name="name" maxlength="120" value="<?= e($department['name']) ?>" placeholder="Computer Science" required>
    </div>
    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" placeholder="What this department teaches"><?= e($department['description']) ?></textarea>
      <span class="hint">Shown on the public website under Academic Programs.</span>
    </div>
    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Create department' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('departments/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
