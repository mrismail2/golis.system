<?php
/** Portal home - one dashboard per role. */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_login();

$pageTitle    = 'Dashboard';
$pageSubtitle = APP_CAMPUS . ' · ' . CURRENT_YEAR . ' · Semester ' . CURRENT_SEMESTER;

require_once APP_ROOT . '/includes/header.php';
?>

<?php if (is_admin()): ?>
  <?php
  $students   = (int)db_value("SELECT COUNT(*) FROM students WHERE status = 'active'", [], 0);
  $lecturers  = (int)db_value("SELECT COUNT(*) FROM lecturers WHERE status = 'active'", [], 0);
  $courses    = (int)db_value("SELECT COUNT(*) FROM courses", [], 0);
  $billed     = (float)db_value("SELECT COALESCE(SUM(amount),0) FROM fees", [], 0);
  $collected  = (float)db_value("SELECT COALESCE(SUM(amount),0) FROM payments", [], 0);
  $unread     = (int)db_value("SELECT COUNT(*) FROM messages WHERE is_read = 0", [], 0);
  $attendance = (float)db_value(
      "SELECT ROUND(100 * SUM(status IN ('present','late')) / NULLIF(COUNT(*),0), 1)
       FROM attendance WHERE class_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
      [],
      0
  );

  $byDepartment = db_all(
      "SELECT d.name, COUNT(s.id) AS total
       FROM departments d
       LEFT JOIN students s ON s.department_id = d.id AND s.status = 'active'
       GROUP BY d.id, d.name
       ORDER BY total DESC, d.name"
  );
  $recentPayments = db_all(
      "SELECT p.receipt_no, p.amount, p.paid_on, p.method, s.reg_no, s.full_name
       FROM payments p
       JOIN fees f    ON f.id = p.fee_id
       JOIN students s ON s.id = f.student_id
       ORDER BY p.paid_on DESC, p.id DESC
       LIMIT 6"
  );
  $upcomingExams = db_all(
      "SELECT e.exam_date, e.start_time, e.room, c.code, c.title
       FROM exams e JOIN courses c ON c.id = e.course_id
       WHERE e.exam_date >= CURDATE()
       ORDER BY e.exam_date LIMIT 5"
  );
  $maxDept = max(1, (int)max(array_column($byDepartment, 'total') ?: [1]));
  ?>

  <div class="tiles">
    <div class="tile"><div class="label">Active students</div><div class="number"><?= number_format($students) ?></div><div class="foot">Enrolled at the campus</div></div>
    <div class="tile gold"><div class="label">Lecturers</div><div class="number"><?= number_format($lecturers) ?></div><div class="foot"><?= number_format($courses) ?> courses running</div></div>
    <div class="tile green"><div class="label">Fees collected</div><div class="number"><?= money($collected) ?></div><div class="foot">of <?= money($billed) ?> invoiced</div></div>
    <div class="tile red"><div class="label">Outstanding</div><div class="number"><?= money(max(0, $billed - $collected)) ?></div><div class="foot">Attendance <?= percent($attendance, 1) ?> (30 days)</div></div>
  </div>

  <div class="grid-2">
    <div class="panel">
      <div class="panel-head">
        <div><h2>Students per department</h2><p>Active registrations</p></div>
        <a class="btn-sm btn-ghost" href="<?= url('reports/students.php') ?>">Full report</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Department</th><th class="center">Students</th><th>Share</th></tr></thead>
          <tbody>
          <?php foreach ($byDepartment as $row): ?>
            <tr>
              <td><?= e($row['name']) ?></td>
              <td class="center"><?= (int)$row['total'] ?></td>
              <td><div class="bar"><span style="width:<?= (int)round(100 * (int)$row['total'] / $maxDept) ?>%"></span></div></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$byDepartment): ?>
            <tr><td colspan="3" class="table-empty">No departments yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div><h2>Latest payments</h2><p>Most recent receipts</p></div>
        <a class="btn-sm btn-ghost" href="<?= url('fees/index.php') ?>">Open fees</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Receipt</th><th>Student</th><th class="right">Amount</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recentPayments as $payment): ?>
            <tr>
              <td><?= e($payment['receipt_no']) ?></td>
              <td><?= e($payment['full_name']) ?><br><small class="hint"><?= e($payment['reg_no']) ?></small></td>
              <td class="right"><?= money($payment['amount']) ?></td>
              <td><?= e(fdate($payment['paid_on'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentPayments): ?>
            <tr><td colspan="4" class="table-empty">No payments recorded yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid-2">
    <div class="panel">
      <div class="panel-head"><div><h2>Upcoming examinations</h2><p>Next five sittings</p></div>
        <a class="btn-sm btn-ghost" href="<?= url('courses/exams.php') ?>">Timetable</a></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Course</th><th>Time</th><th>Room</th></tr></thead>
          <tbody>
          <?php foreach ($upcomingExams as $exam): ?>
            <tr>
              <td><?= e(fdate($exam['exam_date'])) ?></td>
              <td><?= e($exam['code']) ?> &ndash; <?= e($exam['title']) ?></td>
              <td><?= e(ftime($exam['start_time'])) ?></td>
              <td><?= e($exam['room'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$upcomingExams): ?>
            <tr><td colspan="4" class="table-empty">Nothing scheduled.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h2>Shortcuts</h2><p>Common administrative tasks</p></div></div>
      <p style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn-sm btn-blue" href="<?= url('students/form.php') ?>">➕ Register student</a>
        <a class="btn-sm btn-blue" href="<?= url('lecturers/form.php') ?>">➕ Add lecturer</a>
        <a class="btn-sm btn-blue" href="<?= url('courses/form.php') ?>">➕ New course</a>
        <a class="btn-sm btn-gold" href="<?= url('fees/invoice_form.php') ?>">💵 Create invoice</a>
        <a class="btn-sm btn-ghost" href="<?= url('attendance/mark.php') ?>">🗓️ Mark attendance</a>
        <a class="btn-sm btn-ghost" href="<?= url('results/entry.php') ?>">📈 Enter results</a>
        <a class="btn-sm btn-ghost" href="<?= url('admin/notices.php') ?>">📢 Post a notice</a>
        <a class="btn-sm btn-ghost" href="<?= url('admin/messages.php') ?>">✉️ Messages<?= $unread ? ' (' . $unread . ')' : '' ?></a>
      </p>
    </div>
  </div>

<?php elseif (is_lecturer()): ?>
  <?php
  $lecturerId = (int)current_lecturer_id();
  $myCourses  = db_all(
      "SELECT c.*, d.name AS department,
              (SELECT COUNT(*) FROM enrollments en WHERE en.course_id = c.id) AS enrolled
       FROM courses c
       LEFT JOIN departments d ON d.id = c.department_id
       WHERE c.lecturer_id = ?
       ORDER BY c.code",
      [$lecturerId]
  );
  $myStudents = (int)db_value(
      "SELECT COUNT(DISTINCT en.student_id)
       FROM enrollments en JOIN courses c ON c.id = en.course_id
       WHERE c.lecturer_id = ?",
      [$lecturerId],
      0
  );
  $markedToday = (int)db_value(
      "SELECT COUNT(*) FROM attendance a JOIN courses c ON c.id = a.course_id
       WHERE c.lecturer_id = ? AND a.class_date = CURDATE()",
      [$lecturerId],
      0
  );
  $pendingResults = (int)db_value(
      "SELECT COUNT(*)
       FROM enrollments en
       JOIN courses c ON c.id = en.course_id
       LEFT JOIN results r ON r.student_id = en.student_id AND r.course_id = en.course_id
            AND r.academic_year = en.academic_year AND r.semester = en.semester
       WHERE c.lecturer_id = ? AND r.id IS NULL",
      [$lecturerId],
      0
  );
  $myAttendance = (float)db_value(
      "SELECT ROUND(100 * SUM(a.status IN ('present','late')) / NULLIF(COUNT(*),0), 1)
       FROM attendance a JOIN courses c ON c.id = a.course_id
       WHERE c.lecturer_id = ? AND a.class_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
      [$lecturerId],
      0
  );
  ?>

  <div class="tiles">
    <div class="tile"><div class="label">My courses</div><div class="number"><?= count($myCourses) ?></div><div class="foot">Assigned this year</div></div>
    <div class="tile gold"><div class="label">My students</div><div class="number"><?= number_format($myStudents) ?></div><div class="foot">Across all my courses</div></div>
    <div class="tile green"><div class="label">Attendance</div><div class="number"><?= percent($myAttendance, 1) ?></div><div class="foot"><?= $markedToday ?> marked today</div></div>
    <div class="tile red"><div class="label">Results pending</div><div class="number"><?= number_format($pendingResults) ?></div><div class="foot">Enrollments without marks</div></div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <div><h2>My courses</h2><p>Mark attendance or enter results for a course</p></div>
      <a class="btn-sm btn-ghost" href="<?= url('courses/index.php') ?>">All courses</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Code</th><th>Course</th><th>Department</th><th class="center">Credits</th><th class="center">Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($myCourses as $course): ?>
          <tr>
            <td><span class="pill"><?= e($course['code']) ?></span></td>
            <td><?= e($course['title']) ?></td>
            <td><?= e($course['department'] ?: '-') ?></td>
            <td class="center"><?= (int)$course['credit_hours'] ?></td>
            <td class="center"><?= (int)$course['enrolled'] ?></td>
            <td>
              <a class="btn-sm btn-blue" href="<?= url('attendance/mark.php?course_id=' . (int)$course['id']) ?>">Attendance</a>
              <a class="btn-sm btn-ghost" href="<?= url('results/entry.php?course_id=' . (int)$course['id']) ?>">Results</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$myCourses): ?>
          <tr><td colspan="6" class="table-empty">No courses are assigned to you yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <?php
  $studentId = (int)current_student_id();
  $student   = $studentId
      ? db_row("SELECT s.*, d.name AS department FROM students s
                LEFT JOIN departments d ON d.id = s.department_id WHERE s.id = ?", [$studentId])
      : null;
  ?>

  <?php if (!$student): ?>
    <div class="alert warn">Your user account is not linked to a student record yet. Please contact the campus registry.</div>
  <?php else: ?>
    <?php
    $myCourses = db_all(
        "SELECT c.code, c.title, c.credit_hours, l.full_name AS lecturer
         FROM enrollments en
         JOIN courses c ON c.id = en.course_id
         LEFT JOIN lecturers l ON l.id = c.lecturer_id
         WHERE en.student_id = ?
         ORDER BY c.code",
        [$studentId]
    );
    $attendanceRate = (float)db_value(
        "SELECT ROUND(100 * SUM(status IN ('present','late')) / NULLIF(COUNT(*),0), 1)
         FROM attendance WHERE student_id = ?",
        [$studentId],
        0
    );
    $gpa = (float)db_value(
        "SELECT ROUND(SUM(r.grade_points * c.credit_hours) / NULLIF(SUM(c.credit_hours),0), 2)
         FROM results r JOIN courses c ON c.id = r.course_id
         WHERE r.student_id = ? AND r.is_published = 1",
        [$studentId],
        0
    );
    $billed    = (float)db_value("SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id = ?", [$studentId], 0);
    $paid      = (float)db_value(
        "SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN fees f ON f.id = p.fee_id WHERE f.student_id = ?",
        [$studentId],
        0
    );
    $myResults = db_all(
        "SELECT r.*, c.code, c.title
         FROM results r JOIN courses c ON c.id = r.course_id
         WHERE r.student_id = ? AND r.is_published = 1
         ORDER BY r.academic_year DESC, r.semester DESC, c.code",
        [$studentId]
    );
    $myExams = db_all(
        "SELECT e.exam_date, e.start_time, e.room, c.code, c.title
         FROM exams e
         JOIN courses c ON c.id = e.course_id
         JOIN enrollments en ON en.course_id = c.id AND en.student_id = ?
         WHERE e.exam_date >= CURDATE()
         ORDER BY e.exam_date",
        [$studentId]
    );
    ?>

    <div class="tiles">
      <div class="tile"><div class="label">Registration</div><div class="number" style="font-size:20px"><?= e($student['reg_no']) ?></div><div class="foot"><?= e($student['department'] ?: 'No department') ?> · Year <?= (int)$student['year_of_study'] ?></div></div>
      <div class="tile gold"><div class="label">My courses</div><div class="number"><?= count($myCourses) ?></div><div class="foot">Enrolled this year</div></div>
      <div class="tile green"><div class="label">Attendance</div><div class="number"><?= percent($attendanceRate, 1) ?></div><div class="foot">GPA <?= number_format($gpa, 2) ?> / 4.00</div></div>
      <div class="tile red"><div class="label">Fee balance</div><div class="number"><?= money(max(0, $billed - $paid)) ?></div><div class="foot"><?= money($paid) ?> paid of <?= money($billed) ?></div></div>
    </div>

    <div class="grid-2">
      <div class="panel">
        <div class="panel-head"><div><h2>My courses</h2><p><?= e(CURRENT_YEAR) ?></p></div>
          <a class="btn-sm btn-ghost" href="<?= url('attendance/index.php') ?>">My attendance</a></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Code</th><th>Course</th><th class="center">Credits</th><th>Lecturer</th></tr></thead>
            <tbody>
            <?php foreach ($myCourses as $course): ?>
              <tr>
                <td><span class="pill"><?= e($course['code']) ?></span></td>
                <td><?= e($course['title']) ?></td>
                <td class="center"><?= (int)$course['credit_hours'] ?></td>
                <td><?= e($course['lecturer'] ?: '-') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$myCourses): ?>
              <tr><td colspan="4" class="table-empty">You are not enrolled in any course yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><div><h2>My published results</h2><p>Latest marks</p></div>
          <a class="btn-sm btn-ghost" href="<?= url('results/transcript.php') ?>">Transcript</a></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Course</th><th class="center">Marks</th><th class="center">Grade</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($myResults as $result): ?>
              <tr>
                <td><?= e($result['code']) ?> &ndash; <?= e($result['title']) ?></td>
                <td class="center"><?= number_format((float)$result['total_marks'], 1) ?></td>
                <td class="center"><strong><?= e($result['grade']) ?></strong></td>
                <td><?= status_badge((float)$result['total_marks'] >= PASS_MARK ? 'pass' : 'fail') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$myResults): ?>
              <tr><td colspan="4" class="table-empty">No results have been published yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><div><h2>My examination timetable</h2><p>Upcoming sittings for your courses</p></div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Course</th><th>Time</th><th>Room</th></tr></thead>
          <tbody>
          <?php foreach ($myExams as $exam): ?>
            <tr>
              <td><?= e(fdate($exam['exam_date'])) ?></td>
              <td><?= e($exam['code']) ?> &ndash; <?= e($exam['title']) ?></td>
              <td><?= e(ftime($exam['start_time'])) ?></td>
              <td><?= e($exam['room'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$myExams): ?>
            <tr><td colspan="4" class="table-empty">No examinations are scheduled for your courses.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
