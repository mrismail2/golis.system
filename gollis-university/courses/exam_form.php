<?php
/** Examinations - schedule or edit a sitting. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id     = get_int('id');
$errors = [];
$exam   = [
    'course_id'     => '',
    'exam_date'     => date('Y-m-d'),
    'start_time'    => '09:00',
    'duration_mins' => 120,
    'room'          => '',
    'academic_year' => CURRENT_YEAR,
    'semester'      => CURRENT_SEMESTER,
];

if ($id) {
    $found = db_row("SELECT * FROM exams WHERE id = ?", [$id]);
    if (!$found) {
        flash('That examination no longer exists.', 'error');
        redirect('courses/exams.php');
    }
    $exam               = $found;
    $exam['start_time'] = substr((string)$found['start_time'], 0, 5);
}

if (is_post()) {
    verify_csrf();

    $exam['course_id']     = (string)input('course_id');
    $exam['exam_date']     = (string)input('exam_date');
    $exam['start_time']    = (string)input('start_time');
    $exam['duration_mins'] = max(30, min(360, (int)input('duration_mins', 120)));
    $exam['room']          = (string)input('room');
    $exam['academic_year'] = (string)input('academic_year', CURRENT_YEAR);
    $exam['semester']      = (int)input('semester', 1) === 2 ? 2 : 1;

    if ($exam['course_id'] === '') { $errors[] = 'Choose the course being examined.'; }
    if ($exam['exam_date'] === '') { $errors[] = 'The examination date is required.'; }

    if (!$errors) {
        $fields = [
            (int)$exam['course_id'],
            $exam['exam_date'],
            $exam['start_time'] . ':00',
            $exam['duration_mins'],
            $exam['room'] ?: null,
            $exam['academic_year'],
            $exam['semester'],
        ];

        if ($id) {
            db_query(
                "UPDATE exams SET course_id = ?, exam_date = ?, start_time = ?, duration_mins = ?, room = ?,
                        academic_year = ?, semester = ?
                 WHERE id = ?",
                [...$fields, $id]
            );
            flash('Examination updated.');
        } else {
            db_query(
                "INSERT INTO exams (course_id, exam_date, start_time, duration_mins, room, academic_year, semester)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                $fields
            );
            flash('Examination added to the timetable.');
        }

        redirect('courses/exams.php?academic_year=' . urlencode($exam['academic_year']) . '&semester=' . $exam['semester']);
    }
}

$courseOptions = db_all("SELECT id, CONCAT(code, ' - ', title) AS label FROM courses ORDER BY code");

$pageTitle    = $id ? 'Edit examination' : 'Schedule examination';
$pageSubtitle = 'Examination timetable';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('courses/exams.php') . '">← Back to timetable</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:800px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="field">
      <label for="course_id">Course</label>
      <select id="course_id" name="course_id" required>
        <option value="">Choose a course</option>
        <?= options($courseOptions, 'id', 'label', $exam['course_id']) ?>
      </select>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="exam_date">Date</label>
        <input id="exam_date" name="exam_date" type="date" value="<?= e($exam['exam_date']) ?>" required>
      </div>
      <div class="field">
        <label for="start_time">Start time</label>
        <input id="start_time" name="start_time" type="time" value="<?= e($exam['start_time']) ?>" required>
      </div>
      <div class="field">
        <label for="duration_mins">Duration (minutes)</label>
        <input id="duration_mins" name="duration_mins" type="number" min="30" max="360" step="15" value="<?= (int)$exam['duration_mins'] ?>">
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="room">Room / hall</label>
        <input id="room" name="room" value="<?= e($exam['room']) ?>" maxlength="40" placeholder="Room A1">
      </div>
      <div class="field">
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year">
          <?php foreach (academic_years() as $year): ?>
            <option value="<?= e($year) ?>" <?= $year === $exam['academic_year'] ? 'selected' : '' ?>><?= e($year) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= (int)$exam['semester'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Add to timetable' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('courses/exams.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
