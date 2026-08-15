<?php
/** Students - full record: courses, attendance, results and fees. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_login();

$id = get_int('id') ?: (int)(current_student_id() ?? 0);
require_student_access($id);

$student = db_row(
    "SELECT s.*, d.name AS department, u.username
     FROM students s
     LEFT JOIN departments d ON d.id = s.department_id
     LEFT JOIN users u       ON u.id = s.user_id
     WHERE s.id = ?",
    [$id]
);

if (!$student) {
    flash('That student record no longer exists.', 'error');
    redirect(is_student() ? 'dashboard.php' : 'students/index.php');
}

// --- enroll / un-enroll (admin only) ---------------------------------
if (is_post() && is_admin()) {
    verify_csrf();

    if (input('action') === 'enroll') {
        $courseId = post_int('course_id');
        if ($courseId) {
            db_query(
                "INSERT IGNORE INTO enrollments (student_id, course_id, academic_year, semester, enrolled_on)
                 VALUES (?, ?, ?, ?, CURDATE())",
                [$id, $courseId, (string)input('academic_year', CURRENT_YEAR), (int)input('semester', CURRENT_SEMESTER)]
            );
            flash('Course enrollment saved.');
        }
    } elseif (input('action') === 'unenroll') {
        db_query("DELETE FROM enrollments WHERE id = ? AND student_id = ?", [post_int('enrollment_id'), $id]);
        flash('Enrollment removed.');
    }

    redirect('students/view.php?id=' . $id);
}

$enrollments = db_all(
    "SELECT en.id, en.academic_year, en.semester, c.id AS course_id, c.code, c.title, c.credit_hours,
            l.full_name AS lecturer
     FROM enrollments en
     JOIN courses c        ON c.id = en.course_id
     LEFT JOIN lecturers l ON l.id = c.lecturer_id
     WHERE en.student_id = ?
     ORDER BY en.academic_year DESC, en.semester DESC, c.code",
    [$id]
);

$attendance = db_all(
    "SELECT c.code, c.title,
            COUNT(*) AS sessions,
            SUM(a.status IN ('present','late')) AS attended
     FROM attendance a
     JOIN courses c ON c.id = a.course_id
     WHERE a.student_id = ?
     GROUP BY c.id, c.code, c.title
     ORDER BY c.code",
    [$id]
);

$results = db_all(
    "SELECT r.*, c.code, c.title, c.credit_hours
     FROM results r
     JOIN courses c ON c.id = r.course_id
     WHERE r.student_id = ?" . (is_student() ? " AND r.is_published = 1" : "") . "
     ORDER BY r.academic_year DESC, r.semester DESC, c.code",
    [$id]
);

$fees = db_all(
    "SELECT f.*, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id), 0) AS paid
     FROM fees f
     WHERE f.student_id = ?
     ORDER BY f.academic_year DESC, f.semester DESC",
    [$id]
);

$totalBilled = array_sum(array_map(static fn(array $f): float => (float)$f['amount'], $fees));
$totalPaid   = array_sum(array_map(static fn(array $f): float => (float)$f['paid'], $fees));

$gpa = (float)db_value(
    "SELECT ROUND(SUM(r.grade_points * c.credit_hours) / NULLIF(SUM(c.credit_hours),0), 2)
     FROM results r JOIN courses c ON c.id = r.course_id
     WHERE r.student_id = ? AND r.is_published = 1",
    [$id],
    0
);

$pageTitle    = $student['full_name'];
$pageSubtitle = $student['reg_no'] . ' · ' . ($student['department'] ?: 'No department') . ' · Year ' . (int)$student['year_of_study'];
$pageActions  = '';
if (is_admin()) {
    $pageActions .= '<a class="btn-sm btn-blue" href="' . url('students/form.php?id=' . $id) . '">Edit</a>';
    $pageActions .= '<a class="btn-sm btn-gold" href="' . url('fees/invoice_form.php?student_id=' . $id) . '">New invoice</a>';
}
$pageActions .= '<a class="btn-sm btn-ghost" href="' . url('results/transcript.php?student_id=' . $id) . '">Transcript</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel">
  <div class="profile-head">
    <img src="<?= e(photo_url($student['photo'])) ?>" alt="<?= e($student['full_name']) ?>">
    <div style="flex:1;min-width:260px">
      <h2><?= e($student['full_name']) ?> <?= status_badge($student['status']) ?></h2>
      <dl class="data-list">
        <div><dt>Registration no</dt><dd><?= e($student['reg_no']) ?></dd></div>
        <div><dt>Department</dt><dd><?= e($student['department'] ?: '-') ?></dd></div>
        <div><dt>Year of study</dt><dd>Year <?= (int)$student['year_of_study'] ?></dd></div>
        <div><dt>Gender</dt><dd><?= e(ucfirst((string)$student['gender'])) ?></dd></div>
        <div><dt>Date of birth</dt><dd><?= e(fdate($student['date_of_birth'])) ?></dd></div>
        <div><dt>Enrolled on</dt><dd><?= e(fdate($student['enrolled_on'])) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($student['email'] ?: '-') ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($student['phone'] ?: '-') ?></dd></div>
        <div><dt>Address</dt><dd><?= e($student['address'] ?: '-') ?></dd></div>
        <div><dt>Portal account</dt><dd><?= e($student['username'] ?: 'Not created') ?></dd></div>
      </dl>
    </div>
  </div>
</div>

<div class="tiles">
  <div class="tile"><div class="label">Courses</div><div class="number"><?= count($enrollments) ?></div><div class="foot">Current enrollments</div></div>
  <div class="tile gold"><div class="label">GPA</div><div class="number"><?= number_format($gpa, 2) ?></div><div class="foot">of 4.00, published results</div></div>
  <div class="tile green"><div class="label">Fees paid</div><div class="number"><?= money($totalPaid) ?></div><div class="foot">of <?= money($totalBilled) ?> invoiced</div></div>
  <div class="tile red"><div class="label">Balance</div><div class="number"><?= money(max(0, $totalBilled - $totalPaid)) ?></div><div class="foot">Outstanding tuition</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Enrolled courses</h2><p>Courses taken by this student</p></div></div>

  <?php if (is_admin()): ?>
    <form method="post" class="filters" style="margin-bottom:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enroll">
      <div class="field" style="min-width:260px">
        <label for="course_id">Add a course</label>
        <select id="course_id" name="course_id" required>
          <option value="">Choose a course</option>
          <?php foreach (db_all("SELECT id, CONCAT(code, ' - ', title) AS label FROM courses ORDER BY code") as $course): ?>
            <option value="<?= (int)$course['id'] ?>"><?= e($course['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year">
          <?php foreach (academic_years() as $academicYear): ?>
            <option value="<?= e($academicYear) ?>" <?= $academicYear === CURRENT_YEAR ? 'selected' : '' ?>><?= e($academicYear) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= $value === CURRENT_SEMESTER ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn-sm btn-blue" type="submit">Enroll</button></div>
    </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Code</th><th>Course</th><th>Lecturer</th><th class="center">Credits</th><th>Term</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($enrollments as $enrollment): ?>
        <tr>
          <td><span class="pill"><?= e($enrollment['code']) ?></span></td>
          <td><?= e($enrollment['title']) ?></td>
          <td><?= e($enrollment['lecturer'] ?: '-') ?></td>
          <td class="center"><?= (int)$enrollment['credit_hours'] ?></td>
          <td><?= e($enrollment['academic_year']) ?> · S<?= (int)$enrollment['semester'] ?></td>
          <?php if (is_admin()): ?>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unenroll">
                <input type="hidden" name="enrollment_id" value="<?= (int)$enrollment['id'] ?>">
                <button class="btn-sm btn-danger" type="submit"
                        data-confirm="Remove <?= e($enrollment['code']) ?> from this student?">Remove</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$enrollments): ?>
        <tr><td colspan="<?= is_admin() ? 6 : 5 ?>" class="table-empty">No course enrollments yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head"><div><h2>Attendance summary</h2><p>Sessions attended per course</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Course</th><th class="center">Attended</th><th class="center">Sessions</th><th>Rate</th></tr></thead>
        <tbody>
        <?php foreach ($attendance as $row): ?>
          <?php $rate = (int)$row['sessions'] ? 100 * (int)$row['attended'] / (int)$row['sessions'] : 0; ?>
          <tr>
            <td><?= e($row['code']) ?> &ndash; <?= e($row['title']) ?></td>
            <td class="center"><?= (int)$row['attended'] ?></td>
            <td class="center"><?= (int)$row['sessions'] ?></td>
            <td>
              <div class="bar <?= $rate >= 75 ? 'green' : ($rate >= 50 ? 'gold' : 'red') ?>">
                <span style="width:<?= (int)round($rate) ?>%"></span>
              </div>
              <small class="hint"><?= percent($rate, 1) ?></small>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$attendance): ?>
          <tr><td colspan="4" class="table-empty">No attendance has been recorded.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><div><h2>Results</h2><p>Marks per course</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Course</th><th class="center">Total</th><th class="center">Grade</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($results as $result): ?>
          <tr>
            <td><?= e($result['code']) ?> &ndash; <?= e($result['title']) ?><br>
                <small class="hint"><?= e($result['academic_year']) ?> · S<?= (int)$result['semester'] ?></small></td>
            <td class="center"><?= number_format((float)$result['total_marks'], 1) ?></td>
            <td class="center"><strong><?= e($result['grade']) ?></strong></td>
            <td>
              <?= status_badge((float)$result['total_marks'] >= PASS_MARK ? 'pass' : 'fail') ?>
              <?php if (!(int)$result['is_published']): ?><span class="status warn">Draft</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$results): ?>
          <tr><td colspan="4" class="table-empty">No results recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div><h2>Fee invoices</h2><p>Tuition and other charges</p></div>
    <?php if (is_admin()): ?>
      <a class="btn-sm btn-gold" href="<?= url('fees/invoice_form.php?student_id=' . $id) ?>">➕ New invoice</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Term</th><th>Type</th><th class="right">Amount</th><th class="right">Paid</th><th class="right">Balance</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($fees as $fee): ?>
        <?php $balance = fee_balance((float)$fee['amount'], (float)$fee['paid']); ?>
        <tr>
          <td><?= e($fee['academic_year']) ?> · S<?= (int)$fee['semester'] ?></td>
          <td><?= e(ucfirst($fee['fee_type'])) ?></td>
          <td class="right"><?= money($fee['amount']) ?></td>
          <td class="right"><?= money($fee['paid']) ?></td>
          <td class="right"><?= money($balance) ?></td>
          <td><?= status_badge(fee_status((float)$fee['amount'], (float)$fee['paid'])) ?></td>
          <td><a class="btn-sm btn-ghost" href="<?= url('fees/statement.php?student_id=' . $id) ?>">Statement</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$fees): ?>
        <tr><td colspan="7" class="table-empty">No invoices have been raised for this student.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($fees): ?>
        <tfoot>
          <tr>
            <td colspan="2">Totals</td>
            <td class="right"><?= money($totalBilled) ?></td>
            <td class="right"><?= money($totalPaid) ?></td>
            <td class="right"><?= money(max(0, $totalBilled - $totalPaid)) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
