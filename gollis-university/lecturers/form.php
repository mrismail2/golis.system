<?php
/** Lecturers - add and edit. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id       = get_int('id');
$errors   = [];
$lecturer = [
    'staff_no'       => next_staff_no(),
    'full_name'      => '',
    'department_id'  => '',
    'specialization' => '',
    'qualification'  => '',
    'email'          => '',
    'phone'          => '',
    'status'         => 'active',
    'hired_on'       => date('Y-m-d'),
    'photo'          => null,
    'user_id'        => null,
];

if ($id) {
    $found = db_row("SELECT * FROM lecturers WHERE id = ?", [$id]);
    if (!$found) {
        flash('That lecturer no longer exists.', 'error');
        redirect('lecturers/index.php');
    }
    $lecturer = $found;
}

$linkedUser = $lecturer['user_id']
    ? db_row("SELECT * FROM users WHERE id = ?", [(int)$lecturer['user_id']])
    : null;

if (is_post()) {
    verify_csrf();

    $lecturer['staff_no']       = (string)input('staff_no');
    $lecturer['full_name']      = (string)input('full_name');
    $lecturer['department_id']  = (string)input('department_id');
    $lecturer['specialization'] = (string)input('specialization');
    $lecturer['qualification']  = (string)input('qualification');
    $lecturer['email']          = (string)input('email');
    $lecturer['phone']          = (string)input('phone');
    $lecturer['status']         = (string)input('status', 'active');
    $lecturer['hired_on']       = (string)input('hired_on');

    if ($lecturer['staff_no'] === '')  { $errors[] = 'The staff number is required.'; }
    if ($lecturer['full_name'] === '') { $errors[] = 'The lecturer name is required.'; }
    if ($lecturer['email'] !== '' && !filter_var($lecturer['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    }

    $clash = (int)db_value("SELECT COUNT(*) FROM lecturers WHERE staff_no = ? AND id <> ?", [$lecturer['staff_no'], $id], 0);
    if ($clash) {
        $errors[] = 'Another lecturer already uses ' . $lecturer['staff_no'] . '.';
    }

    [$photoName, $photoError] = save_photo('photo', 'lecturer');
    if ($photoError) {
        $errors[] = $photoError;
    }

    $wantsAccount = (string)input('create_account') === '1';
    $username     = (string)input('account_username');
    $newPassword  = (string)($_POST['account_password'] ?? '');

    if ($wantsAccount && !$linkedUser) {
        if ($username === '') {
            $errors[] = 'Enter a username for the portal account.';
        } elseif ((int)db_value("SELECT COUNT(*) FROM users WHERE username = ?", [$username], 0)) {
            $errors[] = 'The username ' . $username . ' is already taken.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'The portal password must be at least 6 characters.';
        }
    }

    if (!$errors) {
        $fields = [
            $lecturer['staff_no'],
            $lecturer['full_name'],
            $lecturer['department_id'] !== '' ? (int)$lecturer['department_id'] : null,
            $lecturer['specialization'] ?: null,
            $lecturer['qualification'] ?: null,
            $lecturer['email'] ?: null,
            $lecturer['phone'] ?: null,
            $lecturer['status'],
            $lecturer['hired_on'] ?: null,
        ];

        if ($id) {
            db_query(
                "UPDATE lecturers SET staff_no = ?, full_name = ?, department_id = ?, specialization = ?,
                        qualification = ?, email = ?, phone = ?, status = ?, hired_on = ?
                 WHERE id = ?",
                [...$fields, $id]
            );
            if ($photoName) {
                delete_photo($lecturer['photo']);
                db_query("UPDATE lecturers SET photo = ? WHERE id = ?", [$photoName, $id]);
            }
            flash('Lecturer updated.');
        } else {
            db_query(
                "INSERT INTO lecturers (staff_no, full_name, department_id, specialization, qualification,
                                        email, phone, status, hired_on, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [...$fields, $photoName]
            );
            $id = (int)db()->lastInsertId();
            flash('Lecturer added with staff number ' . $lecturer['staff_no'] . '.');
        }

        if ($wantsAccount && !$linkedUser) {
            db_query(
                "INSERT INTO users (username, password_hash, full_name, email, role)
                 VALUES (?, ?, ?, ?, 'lecturer')",
                [$username, password_hash($newPassword, PASSWORD_DEFAULT), $lecturer['full_name'], $lecturer['email'] ?: null]
            );
            db_query("UPDATE lecturers SET user_id = ? WHERE id = ?", [(int)db()->lastInsertId(), $id]);
            flash('Portal account created. Username: ' . $username . '.');
        }

        redirect('lecturers/view.php?id=' . $id);
    }
}

$pageTitle    = $id ? 'Edit lecturer' : 'Add lecturer';
$pageSubtitle = $id ? $lecturer['full_name'] : 'Add a new member of academic staff';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('lecturers/index.php') . '">← Back to list</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:900px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-grid three">
      <div class="field">
        <label for="staff_no">Staff number</label>
        <input id="staff_no" name="staff_no" value="<?= e($lecturer['staff_no']) ?>" maxlength="20" required>
      </div>
      <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($lecturer['full_name']) ?>" maxlength="120" required>
      </div>
      <div class="field">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">Not assigned</option>
          <?= options(all_departments(), 'id', 'name', $lecturer['department_id']) ?>
        </select>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="specialization">Specialization</label>
        <input id="specialization" name="specialization" value="<?= e($lecturer['specialization']) ?>" maxlength="120" placeholder="Software Engineering">
      </div>
      <div class="field">
        <label for="qualification">Qualification</label>
        <input id="qualification" name="qualification" value="<?= e($lecturer['qualification']) ?>" maxlength="120" placeholder="MSc Computer Science">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="active"   <?= $lecturer['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $lecturer['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($lecturer['email']) ?>" maxlength="120">
      </div>
      <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($lecturer['phone']) ?>" maxlength="30">
      </div>
      <div class="field">
        <label for="hired_on">Hired on</label>
        <input id="hired_on" name="hired_on" type="date" value="<?= e($lecturer['hired_on']) ?>">
      </div>
    </div>

    <div class="field">
      <label for="photo">Photo</label>
      <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
      <span class="hint">JPG, PNG or WEBP, up to 2 MB.</span>
    </div>

    <?php if ($linkedUser): ?>
      <div class="alert info">
        Portal account: <strong><?= e($linkedUser['username']) ?></strong>
        (<?= e($linkedUser['is_active'] ? 'active' : 'disabled') ?>).
        Passwords are reset from <a href="<?= url('admin/index.php') ?>">User accounts</a>.
      </div>
    <?php else: ?>
      <div class="form-grid three">
        <div class="field">
          <label><input type="checkbox" name="create_account" value="1" style="width:auto"> Create portal account</label>
        </div>
        <div class="field">
          <label for="account_username">Username</label>
          <input id="account_username" name="account_username" maxlength="60" placeholder="ahassan">
        </div>
        <div class="field">
          <label for="account_password">Password</label>
          <input id="account_password" name="account_password" type="password" autocomplete="new-password" placeholder="At least 6 characters">
        </div>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Add lecturer' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('lecturers/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
