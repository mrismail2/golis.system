<?php
/** Courses - create and edit. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id     = get_int('id');
$errors = [];
$course = [
    'code'          => '',
    'title'         => '',
    'department_id' => '',
    'lecturer_id'   => '',
    'credit_hours'  => 3,
    'year_level'    => 1,
    'semester'      => CURRENT_SEMESTER,
    'description'   => '',
];

if ($id) {
    $found = db_row("SELECT * FROM courses WHERE id = ?", [$id]);
    if (!$found) {
        flash('That course no longer exists.', 'error');
        redirect('courses/index.php');
    }
    $course = $found;
}

if (is_post()) {
    verify_csrf();

    $course['code']          = strtoupper((string)input('code'));
    $course['title']         = (string)input('title');
    $course['department_id'] = (string)input('department_id');
    $course['lecturer_id']   = (string)input('lecturer_id');
    $course['credit_hours']  = max(1, min(9, (int)input('credit_hours', 3)));
    $course['year_level']    = max(1, min(4, (int)input('year_level', 1)));
    $course['semester']      = (int)input('semester', 1) === 2 ? 2 : 1;
    $course['description']   = (string)input('description');

    if ($course['code'] === '')  { $errors[] = 'The course code is required.'; }
    if ($course['title'] === '') { $errors[] = 'The course title is required.'; }

    $clash = (int)db_value("SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?", [$course['code'], $id], 0);
    if ($clash) {
        $errors[] = 'Another course already uses the code ' . $course['code'] . '.';
    }

    if (!$errors) {
        $fields = [
            $course['code'],
            $course['title'],
            $course['department_id'] !== '' ? (int)$course['department_id'] : null,
            $course['lecturer_id'] !== '' ? (int)$course['lecturer_id'] : null,
            $course['credit_hours'],
            $course['year_level'],
            $course['semester'],
            $course['description'] ?: null,
        ];

        if ($id) {
            db_query(
                "UPDATE courses SET code = ?, title = ?, department_id = ?, lecturer_id = ?, credit_hours = ?,
                        year_level = ?, semester = ?, description = ?
                 WHERE id = ?",
                [...$fields, $id]
            );
            flash('Course updated.');
        } else {
            db_query(
                "INSERT INTO courses (code, title, department_id, lecturer_id, credit_hours, year_level, semester, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                $fields
            );
            flash('Course ' . $course['code'] . ' created.');
        }

        redirect('courses/index.php');
    }
}

$pageTitle    = $id ? 'Edit course' : 'New course';
$pageSubtitle = $id ? $course['code'] . ' · ' . $course['title'] : 'Add a course to the catalog';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('courses/index.php') . '">← Back to list</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:860px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="form-grid">
      <div class="field">
        <label for="code">Course code</label>
        <input id="code" name="code" value="<?= e($course['code']) ?>" maxlength="15" placeholder="CS101" required>
      </div>
      <div class="field">
        <label for="title">Course title</label>
        <input id="title" name="title" value="<?= e($course['title']) ?>" maxlength="140" placeholder="Introduction to Programming" required>
      </div>
    </div>

    <div class="form-grid">
      <div class="field">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id">
          <option value="">Not assigned</option>
          <?= options(all_departments(), 'id', 'name', $course['department_id']) ?>
        </select>
      </div>
      <div class="field">
        <label for="lecturer_id">Lecturer</label>
        <select id="lecturer_id" name="lecturer_id">
          <option value="">Not assigned</option>
          <?= options(all_lecturers(), 'id', 'full_name', $course['lecturer_id']) ?>
        </select>
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="credit_hours">Credit hours</label>
        <input id="credit_hours" name="credit_hours" type="number" min="1" max="9" value="<?= (int)$course['credit_hours'] ?>">
      </div>
      <div class="field">
        <label for="year_level">Year level</label>
        <select id="year_level" name="year_level">
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?= $i ?>" <?= (int)$course['year_level'] === $i ? 'selected' : '' ?>>Year <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= (int)$course['semester'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" placeholder="What the course covers"><?= e($course['description']) ?></textarea>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Create course' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('courses/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
