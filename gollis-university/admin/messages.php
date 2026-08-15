<?php
/** Administration - messages sent through the public contact form. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

if (is_post()) {
    verify_csrf();

    $messageId = post_int('message_id');
    $action    = (string)input('action');

    if ($action === 'read' && $messageId) {
        db_query("UPDATE messages SET is_read = 1 - is_read WHERE id = ?", [$messageId]);
        flash('Message updated.');
    } elseif ($action === 'delete' && $messageId) {
        db_query("DELETE FROM messages WHERE id = ?", [$messageId]);
        flash('Message deleted.');
    } elseif ($action === 'read_all') {
        db_query("UPDATE messages SET is_read = 1 WHERE is_read = 0");
        flash('All messages marked as read.');
    }

    redirect('admin/messages.php');
}

$messages = db_all("SELECT * FROM messages ORDER BY is_read, created_at DESC");
$unread   = count(array_filter($messages, static fn(array $row): bool => !(int)$row['is_read']));

$pageTitle    = 'Messages';
$pageSubtitle = $unread . ' unread of ' . count($messages) . ' received';
$pageActions  = '';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <div><h2>Contact form inbox</h2><p>Sent from the public website</p></div>
    <?php if ($unread): ?>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all">
        <button class="btn-sm btn-ghost" type="submit">Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Received</th><th>From</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($messages as $message): ?>
        <tr>
          <td><?= e(fdate($message['created_at'], 'd M Y H:i')) ?></td>
          <td><strong><?= e($message['name']) ?></strong><br><small class="hint"><?= e($message['email']) ?></small></td>
          <td><?= nl2br(e($message['body'])) ?></td>
          <td><?= (int)$message['is_read'] ? '<span class="status info">Read</span>' : '<span class="status warn">New</span>' ?></td>
          <td>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="read">
              <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
              <button class="btn-sm btn-ghost" type="submit"><?= (int)$message['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
            </form>
            <a class="btn-sm btn-blue" href="mailto:<?= e($message['email']) ?>">Reply</a>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
              <button class="btn-sm btn-danger" type="submit" data-confirm="Delete this message?">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$messages): ?>
        <tr><td colspan="5" class="table-empty">No messages have been received.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
