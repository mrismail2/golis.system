<?php
/** Students - register a new student or edit an existing one. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id      = get_int('id');
$errors  = [];
$student = [
    'reg_no'        => next_reg_no(),
    'full_name'     => '',
    'gender'        => 'male',
    'date_of_birth' => '',
    'department_id' => '',
    'year_of_study' => 1,
    'email'         => '',
    'phone'         => '',
    'address'       => '',
    'status'        => 'active',
    'enrolled_on'   => date('Y-m-d'),
    'photo'         => null,
    'user_id'       => null,
];

if ($id) {
    $found = db_row("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$found) {
        flash('That student record no longer exists.', 'error');
        redirect('students/index.php');
    }
    $student = $found;
}

$linkedUser = $student['user_id']
    ? db_row("SELECT * FROM users WHERE id = ?", [(int)$student['user_id']])
    : null;

if (is_post()) {
    verify_csrf();

    $student['reg_no']        = (string)input('reg_no');
    $student['full_name']     = (string)input('full_name');
    $student['gender']        = (string)input('gender', 'male');
    $student['date_of_birth'] = (string)input('date_of_birth');
    $student['department_id'] = (string)input('department_id');
    $student['year_of_study'] = max(1, min(6, (int)input('year_of_study', 1)));
    $student['email']         = (string)input('email');
    $student['phone']         = (string)input('phone');
    $student['address']       = (string)input('address');
    $student['status']        = (string)input('status', 'active');
    $student['enrolled_on']   = (string)input('enrolled_on');

    if ($student['reg_no'] === '')    { $errors[] = 'The registration number is required.'; }
    if ($student['full_name'] === '') { $errors[] = 'The student name is required.'; }
    if ($student['email'] !== '' && !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    }

    $clash = (int)db_value("SELECT COUNT(*) FROM students WHERE reg_no = ? AND id <> ?", [$student['reg_no'], $id], 0);
    if ($clash) {
        $errors[] = 'Another student already uses ' . $student['reg_no'] . '.';
    }

    [$photoName, $photoError] = save_photo('photo', 'student');
    if ($photoError) {
        $errors[] = $photoError;
    }

    // optional portal account
    $wantsAccount = (string)input('create_account') === '1';
    $newPassword  = (string)($_POST['account_password'] ?? '');
    if ($wantsAccount && !$linkedUser && strlen($newPassword) < 6) {
        $errors[] = 'The portal password must be at least 6 characters.';
    }

    if (!$errors) {
        $fields = [
            $student['reg_no'],
            $student['full_name'],
            $student['gender'],
            $student['date_of_birth'] ?: null,
            $student['department_id'] !== '' ? (int)$student['department_id'] : null,
            $student['year_of_study'],
            $student['email'] ?: null,
            $student['phone'] ?: null,
            $student['address'] ?: null,
            $student['status'],
            $student['enrolled_on'] ?: null,
        ];

        if ($id) {
            db_query(
                "UPDATE students SET reg_no = ?, full_name = ?, gender = ?, date_of_birth = ?, department_id = ?,
                        year_of_study = ?, email = ?, phone = ?, address = ?, status = ?, enrolled_on = ?
                 WHERE id = ?",
                [...$fields, $id]
            );
            if ($photoName) {
                delete_photo($student['photo']);
                db_query("UPDATE students SET photo = ? WHERE id = ?", [$photoName, $id]);
            }
            flash('Student record updated.');
        } else {
            db_query(
                "INSERT INTO students (reg_no, full_name, gender, date_of_birth, department_id, year_of_study,
                                       email, phone, address, status, enrolled_on, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [...$fields, $photoName]
            );
            $id = (int)db()->lastInsertId();
            flash('Student registered with number ' . $student['reg_no'] . '.');
        }

        if ($wantsAccount && !$linkedUser) {
            $taken = (int)db_value("SELECT COUNT(*) FROM users WHERE username = ?", [$student['reg_no']], 0);
            if ($taken) {
                flash('A user account with the username ' . $student['reg_no'] . ' already exists, so no new account was created.', 'warn');
            } else {
                db_query(
                    "INSERT INTO users (username, password_hash, full_name, email, role)
                     VALUES (?, ?, ?, ?, 'student')",
                    [
                        $student['reg_no'],
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        $student['full_name'],
                        $student['email'] ?: null,
                    ]
                );
                db_query("UPDATE students SET user_id = ? WHERE id = ?", [(int)db()->lastInsertId(), $id]);
                flash('Portal account created. Username: ' . $student['reg_no'] . '.');
            }
        }

        redirect('students/view.php?id=' . $id);
    }
}

$pageTitle    = $id ? 'Edit student' : 'Register student';
$pageSubtitle = $id ? $student['full_name'] : 'Add a new student to the register';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('students/index.php') . '">← Back to list</a>';

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
        <label for="reg_no">Registration number</label>
        <input id="reg_no" name="reg_no" value="<?= e($student['reg_no']) ?>" maxlength="20" required>
      </div>
      <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($student['full_name']) ?>" maxlength="120" required>
      </div>
      <div class="field">
        <label for="gender">Gender</label>
        <select id="gender" name="gender">
          <option value="male"   <?= $student['gender'] === 'male'   ? 'selected' : '' ?>>Male</option>
          <option value="female" <?= $student['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
        </select>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">Not assigned</option>
          <?= options(all_departments(), 'id', 'name', $student['department_id']) ?>
        </select>
      </div>
      <div class="field">
        <label for="year_of_study">Year of study</label>
        <select id="year_of_study" name="year_of_study">
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?= $i ?>" <?= (int)$student['year_of_study'] === $i ? 'selected' : '' ?>>Year <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['active', 'graduated', 'suspended', 'withdrawn'] as $option): ?>
            <option value="<?= $option ?>" <?= $student['status'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="date_of_birth">Date of birth</label>
        <input id="date_of_birth" name="date_of_birth" type="date" value="<?= e($student['date_of_birth']) ?>">
      </div>
      <div class="field">
        <label for="enrolled_on">Enrolled on</label>
        <input id="enrolled_on" name="enrolled_on" type="date" value="<?= e($student['enrolled_on']) ?>">
      </div>
      <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($student['phone']) ?>" maxlength="30" placeholder="+252 63 ...">
      </div>
    </div>

    <div class="form-grid">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($student['email']) ?>" maxlength="120">
      </div>
      <div class="field">
        <label for="address">Address</label>
        <input id="address" name="address" value="<?= e($student['address']) ?>" maxlength="160" placeholder="Gabiley">
      </div>
    </div>

    <div class="field">
      <label for="photo">Photo</label>
      <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
      <span class="hint">JPG, PNG or WEBP, up to 2 MB. Leave empty to keep the current photo.</span>
    </div>

    <?php if ($linkedUser): ?>
      <div class="alert info">
        Portal account: <strong><?= e($linkedUser['username']) ?></strong>
        (<?= e($linkedUser['is_active'] ? 'active' : 'disabled') ?>).
        Passwords are reset from <a href="<?= url('admin/index.php') ?>">User accounts</a>.
      </div>
    <?php else: ?>
      <div class="field">
        <label><input type="checkbox" name="create_account" value="1" style="width:auto"> Create a portal account for this student</label>
        <input name="account_password" type="password" placeholder="Portal password (at least 6 characters)" autocomplete="new-password">
        <span class="hint">The username will be the registration number.</span>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Register student' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('students/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
