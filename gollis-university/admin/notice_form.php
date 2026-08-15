<?php
/** Administration - write or edit a campus notice. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id     = get_int('id');
$errors = [];
$notice = ['title' => '', 'body' => '', 'is_published' => 1];

if ($id) {
    $found = db_row("SELECT * FROM notices WHERE id = ?", [$id]);
    if (!$found) {
        flash('That notice no longer exists.', 'error');
        redirect('admin/notices.php');
    }
    $notice = $found;
}

if (is_post()) {
    verify_csrf();

    $notice['title']        = (string)input('title');
    $notice['body']         = (string)input('body');
    $notice['is_published'] = (string)input('is_published') === '1' ? 1 : 0;

    if ($notice['title'] === '') { $errors[] = 'The title is required.'; }
    if ($notice['body'] === '')  { $errors[] = 'The message is required.'; }

    if (!$errors) {
        if ($id) {
            db_query(
                "UPDATE notices SET title = ?, body = ?, is_published = ? WHERE id = ?",
                [$notice['title'], $notice['body'], $notice['is_published'], $id]
            );
            flash('Notice updated.');
        } else {
            db_query(
                "INSERT INTO notices (title, body, is_published, posted_by) VALUES (?, ?, ?, ?)",
                [$notice['title'], $notice['body'], $notice['is_published'], user_id()]
            );
            flash('Notice posted.');
        }

        redirect('admin/notices.php');
    }
}

$pageTitle    = $id ? 'Edit notice' : 'New notice';
$pageSubtitle = 'Shown in the notices section of the public website';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('admin/notices.php') . '">← Back to notices</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:760px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="field">
      <label for="title">Title</label>
      <input id="title" name="title" value="<?= e($notice['title']) ?>" maxlength="140" required>
    </div>
    <div class="field">
      <label for="body">Message</label>
      <textarea id="body" name="body" required><?= e($notice['body']) ?></textarea>
    </div>
    <div class="field">
      <label for="is_published">Visibility</label>
      <select id="is_published" name="is_published">
        <option value="1" <?= (int)$notice['is_published'] === 1 ? 'selected' : '' ?>>Published on the website</option>
        <option value="0" <?= (int)$notice['is_published'] === 0 ? 'selected' : '' ?>>Draft (hidden)</option>
      </select>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Post notice' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('admin/notices.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
