<?php
/** Administration - campus notices shown on the public website. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();

    $noticeId = post_int('notice_id');
    if (input('action') === 'toggle' && $noticeId) {
        db_query("UPDATE notices SET is_published = 1 - is_published WHERE id = ?", [$noticeId]);
        flash('Notice updated.');
    }

    redirect('admin/notices.php');
}

$notices = db_all(
    "SELECT n.*, u.full_name AS author
     FROM notices n LEFT JOIN users u ON u.id = n.posted_by
     ORDER BY n.created_at DESC"
);

$pageTitle    = 'Campus notices';
$pageSubtitle = 'Announcements published on the public website';
$pageActions  = '<a class="btn-sm btn-blue" href="' . url('admin/notice_form.php') . '">➕ New notice</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head"><div><h2><?= count($notices) ?> notices</h2><p>Only published notices appear on the home page</p></div></div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Message</th><th>Posted</th><th>By</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($notices as $notice): ?>
        <tr>
          <td><strong><?= e($notice['title']) ?></strong></td>
          <td class="hint"><?= e(mb_strimwidth($notice['body'], 0, 110, '...')) ?></td>
          <td><?= e(fdate($notice['created_at'])) ?></td>
          <td><?= e($notice['author'] ?: '-') ?></td>
          <td><?= status_badge((int)$notice['is_published'] ? 'published' : 'draft') ?></td>
          <td>
            <a class="btn-sm btn-blue" href="<?= url('admin/notice_form.php?id=' . (int)$notice['id']) ?>">Edit</a>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="notice_id" value="<?= (int)$notice['id'] ?>">
              <button class="btn-sm btn-ghost" type="submit"><?= (int)$notice['is_published'] ? 'Unpublish' : 'Publish' ?></button>
            </form>
            <a class="btn-sm btn-danger"
               href="<?= url('admin/notice_delete.php?id=' . (int)$notice['id'] . '&csrf_token=' . urlencode(csrf_token())) ?>"
               data-confirm="Delete the notice &quot;<?= e($notice['title']) ?>&quot;?">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$notices): ?>
        <tr><td colspan="6" class="table-empty">No notices have been posted yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
