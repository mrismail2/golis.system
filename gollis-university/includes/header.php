<?php
/**
 * Portal layout - top of the page (sidebar + topbar).
 *
 * Pages set these before including it:
 *     $pageTitle    - heading shown in the topbar
 *     $pageSubtitle - optional small text under the heading
 *     $pageActions  - optional HTML for buttons on the right
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../config/auth.php';
}

require_login();

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions  = $pageActions  ?? '';
$currentUser  = current_user();
$currentPath  = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');

/** Sidebar definition: [url, label, icon, roles]. */
$navGroups = [
    'Overview' => [
        ['dashboard.php', 'Dashboard', '🏠', ['admin', 'lecturer', 'student']],
    ],
    'Academics' => [
        ['students/index.php',    'Students',    '🎓', ['admin', 'lecturer']],
        ['lecturers/index.php',   'Lecturers',   '👨‍🏫', ['admin', 'lecturer']],
        ['departments/index.php', 'Departments', '🏛️', ['admin', 'lecturer', 'student']],
        ['courses/index.php',     'Courses',     '📚', ['admin', 'lecturer', 'student']],
        ['courses/exams.php',     'Exams',       '📝', ['admin', 'lecturer', 'student']],
    ],
    'Records' => [
        ['attendance/index.php', 'Attendance', '🗓️', ['admin', 'lecturer', 'student']],
        ['results/index.php',    'Results',    '📈', ['admin', 'lecturer', 'student']],
        ['fees/index.php',       'Fees',       '💵', ['admin', 'student']],
        ['reports/index.php',    'Reports',    '📊', ['admin', 'lecturer']],
    ],
    'Administration' => [
        ['admin/index.php',    'User accounts', '👥', ['admin']],
        ['admin/notices.php',  'Notices',       '📢', ['admin']],
        ['admin/messages.php', 'Messages',      '✉️', ['admin']],
    ],
];

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> &ndash; <?= e(APP_NAME) ?></title>
  <link rel="icon" href="<?= url('assets/images/logo.jpg') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="portal">
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <a class="side-brand" href="<?= url('dashboard.php') ?>">
      <img src="<?= url('assets/images/logo.jpg') ?>" alt="<?= e(APP_NAME) ?> logo">
      <div>
        <strong><?= e(APP_NAME) ?></strong>
        <span><?= e(strtoupper(APP_CAMPUS)) ?></span>
      </div>
    </a>

    <nav class="side-nav">
      <?php foreach ($navGroups as $group => $items): ?>
        <?php
        $visible = array_filter($items, static fn(array $item): bool => has_role(...$item[3]));
        if (!$visible) {
            continue;
        }
        ?>
        <div class="side-group"><?= e($group) ?></div>
        <?php foreach ($visible as [$path, $label, $icon]): ?>
          <?php $active = str_ends_with($currentPath, $path) ? ' active' : ''; ?>
          <a class="side-link<?= $active ?>" href="<?= url($path) ?>">
            <span class="ico"><?= $icon ?></span><?= e($label) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <div class="side-group">Account</div>
      <a class="side-link<?= str_ends_with($currentPath, 'admin/profile.php') ? ' active' : '' ?>"
         href="<?= url('admin/profile.php') ?>"><span class="ico">⚙️</span>My profile</a>
      <a class="side-link" href="<?= url('index.php') ?>"><span class="ico">🌐</span>Public site</a>
      <a class="side-link danger" href="<?= url('logout.php') ?>"><span class="ico">🚪</span>Sign out</a>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar-app">
      <button class="menu-toggle" type="button" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>
      <div class="page-heading">
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageSubtitle !== ''): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
      </div>
      <div class="topbar-right">
        <?= $pageActions ?>
        <div class="who">
          <strong><?= e($currentUser['full_name']) ?></strong>
          <span><?= e(ucfirst($currentUser['role'])) ?></span>
        </div>
      </div>
    </header>

    <main class="content">
      <?php foreach ($flashes as $message): ?>
        <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
      <?php endforeach; ?>
