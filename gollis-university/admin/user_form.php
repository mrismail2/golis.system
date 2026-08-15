<?php
/** Administration - create a portal account or edit one. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id     = get_int('id');
$errors = [];
$user   = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'role'      => 'student',
    'is_active' => 1,
];

if ($id) {
    $found = db_row("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$found) {
        flash('That account no longer exists.', 'error');
        redirect('admin/index.php');
    }
    $user = $found;
}

if (is_post()) {
    verify_csrf();

    $user['username']  = (string)input('username');
    $user['full_name'] = (string)input('full_name');
    $user['email']     = (string)input('email');
    $user['role']      = (string)input('role', 'student');
    $user['is_active'] = (string)input('is_active') === '1' ? 1 : 0;
    $password          = (string)($_POST['password'] ?? '');

    if ($user['username'] === '')  { $errors[] = 'The username is required.'; }
    if ($user['full_name'] === '') { $errors[] = 'The full name is required.'; }
    if (!in_array($user['role'], ['admin', 'lecturer', 'student'], true)) {
        $errors[] = 'Choose a valid role.';
    }
    if ($user['email'] !== '' && !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    }
    if (!$id && strlen($password) < 6) {
        $errors[] = 'The password must be at least 6 characters.';
    }
    if ($id && $password !== '' && strlen($password) < 6) {
        $errors[] = 'The new password must be at least 6 characters.';
    }
    if ($id === user_id() && $user['role'] !== 'admin') {
        $errors[] = 'You cannot remove the administrator role from your own account.';
    }

    $clash = (int)db_value("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?", [$user['username'], $id], 0);
    if ($clash) {
        $errors[] = 'The username ' . $user['username'] . ' is already taken.';
    }

    if (!$errors) {
        if ($id) {
            db_query(
                "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?",
                [$user['username'], $user['full_name'], $user['email'] ?: null, $user['role'], $user['is_active'], $id]
            );
            if ($password !== '') {
                db_query("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $id]);
                flash('Password reset for ' . $user['username'] . '.');
            }
            flash('Account updated.');
        } else {
            db_query(
                "INSERT INTO users (username, password_hash, full_name, email, role, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $user['username'], password_hash($password, PASSWORD_DEFAULT), $user['full_name'],
                    $user['email'] ?: null, $user['role'], $user['is_active'],
                ]
            );
            flash('Account created for ' . $user['username'] . '.');
        }

        redirect('admin/index.php');
    }
}

$pageTitle    = $id ? 'Edit account' : 'New account';
$pageSubtitle = 'Portal sign-in details';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('admin/index.php') . '">← Back to accounts</a>';

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
        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($user['username']) ?>" maxlength="60" required>
        <span class="hint">Students normally sign in with their registration number.</span>
      </div>
      <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" maxlength="120" required>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($user['email']) ?>" maxlength="120">
      </div>
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
          <?php foreach (['admin' => 'Administrator', 'lecturer' => 'Lecturer', 'student' => 'Student'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $user['role'] === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="is_active">Status</label>
        <select id="is_active" name="is_active">
          <option value="1" <?= (int)$user['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= (int)$user['is_active'] === 0 ? 'selected' : '' ?>>Disabled</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="password"><?= $id ? 'New password (leave empty to keep the current one)' : 'Password' ?></label>
      <input id="password" name="password" type="password" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
      <span class="hint">At least 6 characters.</span>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Create account' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('admin/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
