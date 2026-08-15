<?php
/** Administration - portal user accounts. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

// enable / disable an account
if (is_post()) {
    verify_csrf();

    $userId = post_int('user_id');
    if (input('action') === 'toggle' && $userId && $userId !== user_id()) {
        db_query("UPDATE users SET is_active = 1 - is_active WHERE id = ?", [$userId]);
        flash('Account updated.');
    } elseif ($userId === user_id()) {
        flash('You cannot disable your own account.', 'warn');
    }

    redirect('admin/index.php');
}

$role   = (string)input('role', '');
$search = (string)input('q', '');

$sql    = "SELECT u.*,
                  (SELECT s.reg_no   FROM students  s WHERE s.user_id  = u.id LIMIT 1) AS reg_no,
                  (SELECT l.staff_no FROM lecturers l WHERE l.user_id = u.id LIMIT 1) AS staff_no
           FROM users u WHERE 1 = 1";
$params = [];

if ($role !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}
if ($search !== '') {
    $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql .= " ORDER BY FIELD(u.role,'admin','lecturer','student'), u.username";
$users = db_all($sql, $params);

$pageTitle    = 'User accounts';
$pageSubtitle = 'Who can sign in to the portal';
$pageActions  = '<a class="btn-sm btn-blue" href="' . url('admin/user_form.php') . '">➕ New account</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="filters">
    <form method="get" data-auto-submit>
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
          <option value="">All roles</option>
          <?php foreach (['admin' => 'Administrator', 'lecturer' => 'Lecturer', 'student' => 'Student'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $role === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="min-width:230px">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Username, name or email">
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Filter</button></div>
      <div class="field"><label>&nbsp;</label><a class="btn-sm btn-ghost" href="<?= url('admin/index.php') ?>">Reset</a></div>
    </form>
  </div>

  <div class="panel-head"><div><h2><?= count($users) ?> accounts</h2><p>Administrators, lecturers and students</p></div></div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Linked record</th><th>Last login</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><span class="pill"><?= e($user['username']) ?></span></td>
          <td><?= e($user['full_name']) ?><br><small class="hint"><?= e($user['email'] ?: '') ?></small></td>
          <td><?= e(ucfirst($user['role'])) ?></td>
          <td><?= e($user['reg_no'] ?: ($user['staff_no'] ?: '-')) ?></td>
          <td><?= e($user['last_login'] ? fdate($user['last_login'], 'd M Y H:i') : 'never') ?></td>
          <td><?= status_badge((int)$user['is_active'] ? 'active' : 'inactive') ?></td>
          <td>
            <a class="btn-sm btn-blue" href="<?= url('admin/user_form.php?id=' . (int)$user['id']) ?>">Edit</a>
            <?php if ((int)$user['id'] !== user_id()): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                <button class="btn-sm btn-ghost" type="submit"><?= (int)$user['is_active'] ? 'Disable' : 'Enable' ?></button>
              </form>
              <a class="btn-sm btn-danger"
                 href="<?= url('admin/user_delete.php?id=' . (int)$user['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
                 data-confirm="Delete the account <?= e($user['username']) ?>? The student or lecturer record itself is kept.">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="7" class="table-empty">No accounts match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
