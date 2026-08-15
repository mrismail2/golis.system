<?php
/** My profile - every signed in user can update their own details. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$errors  = [];
$account = db_row("SELECT * FROM users WHERE id = ?", [user_id()]);

if (!$account) {
    logout_user();
    redirect('login.php');
}

if (is_post()) {
    verify_csrf();

    $fullName        = (string)input('full_name');
    $email           = (string)input('email');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Your name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    }

    if ($newPassword !== '' || $confirmPassword !== '') {
        if (!password_verify($currentPassword, $account['password_hash'])) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'The new password must be at least 6 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'The new password and its confirmation do not match.';
        }
    }

    if (!$errors) {
        db_query(
            "UPDATE users SET full_name = ?, email = ? WHERE id = ?",
            [$fullName, $email ?: null, user_id()]
        );

        if ($newPassword !== '') {
            db_query(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($newPassword, PASSWORD_DEFAULT), user_id()]
            );
            flash('Your password has been changed.');
        }

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email']     = $email;

        flash('Your profile has been updated.');
        redirect('admin/profile.php');
    }
}

$linked = null;
if (is_student()) {
    $linked = db_row("SELECT reg_no AS reference, full_name FROM students WHERE user_id = ?", [user_id()]);
} elseif (is_lecturer()) {
    $linked = db_row("SELECT staff_no AS reference, full_name FROM lecturers WHERE user_id = ?", [user_id()]);
}

$pageTitle    = 'My profile';
$pageSubtitle = 'Your sign-in details';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:760px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <dl class="data-list" style="margin-bottom:20px">
    <div><dt>Username</dt><dd><?= e($account['username']) ?></dd></div>
    <div><dt>Role</dt><dd><?= e(ucfirst($account['role'])) ?></dd></div>
    <div><dt>Linked record</dt><dd><?= e($linked['reference'] ?? '-') ?></dd></div>
    <div><dt>Last login</dt><dd><?= e($account['last_login'] ? fdate($account['last_login'], 'd M Y H:i') : 'this session') ?></dd></div>
  </dl>

  <form method="post">
    <?= csrf_field() ?>

    <div class="form-grid">
      <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($account['full_name']) ?>" maxlength="120" required>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($account['email']) ?>" maxlength="120">
      </div>
    </div>

    <h3 style="margin-top:10px;color:var(--dark);font-size:16px">Change password</h3>
    <p class="hint">Leave these fields empty to keep your current password.</p>

    <div class="form-grid three">
      <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit">Save changes</button>
      <a class="btn-sm btn-ghost" href="<?= url('dashboard.php') ?>">Back to dashboard</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
