<?php
/** Sign in to the university management portal. */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error    = '';
$username = '';

if (is_post()) {
    verify_csrf();

    $username = (string)input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $error = attempt_login($username, $password) ?? '';

        if ($error === '') {
            $target = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            flash('Welcome back, ' . current_user()['full_name'] . '.');
            redirect($target ?: 'dashboard.php');
        }
    }
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in &ndash; <?= e(APP_NAME) ?></title>
  <link rel="icon" href="<?= url('assets/images/logo.jpg') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="brand">
      <img class="brand-logo" src="<?= url('assets/images/logo.jpg') ?>" alt="<?= e(APP_NAME) ?> logo">
      <div><?= e(APP_NAME) ?><span><?= e(strtoupper(APP_CAMPUS)) ?></span></div>
    </div>
    <h1>Management Portal</h1>
    <p class="hint">Sign in with the account issued by the campus registry.</p>

    <?php foreach ($flashes as $message): ?>
      <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <?php if ($error !== ''): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('login.php') ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Username or registration number</label>
        <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary" type="submit">Sign in</button>
    </form>

    <div class="login-demo">
      <strong>Demo accounts</strong><br>
      Administrator: <code>admin</code> / <code>admin123</code><br>
      Lecturer: <code>ahassan</code> / <code>lecturer123</code><br>
      Student: <code>GU-GAB-1001</code> / <code>student123</code>
    </div>

    <a class="login-back" href="<?= url('index.php') ?>">&larr; Back to the campus website</a>
  </div>
</body>
</html>
