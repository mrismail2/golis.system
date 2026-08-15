<?php
/**
 * Gollis University - Gabiley Campus
 * Public website: programs, exam timetable, notices and the contact form.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

// --- contact form ----------------------------------------------------
$formError = '';
if (is_post() && input('action') === 'contact') {
    verify_csrf();

    $name    = (string)input('name');
    $email   = (string)input('email');
    $message = (string)input('message');

    if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Please fill in your name, a valid email address and your message.';
    } else {
        db_query(
            "INSERT INTO messages (name, email, body) VALUES (?, ?, ?)",
            [$name, $email, $message]
        );
        flash('Thank you, ' . $name . '. Your message has been sent to the campus office.');
        redirect('index.php#contact');
    }
}

// --- live figures for the home page ---------------------------------
$totalStudents  = (int)db_value("SELECT COUNT(*) FROM students WHERE status = 'active'", [], 0);
$totalLecturers = (int)db_value("SELECT COUNT(*) FROM lecturers WHERE status = 'active'", [], 0);
$totalCourses   = (int)db_value("SELECT COUNT(*) FROM courses", [], 0);
$totalPrograms  = (int)db_value("SELECT COUNT(*) FROM departments", [], 0);

$attendanceRate = (float)db_value(
    "SELECT ROUND(100 * SUM(status IN ('present','late')) / NULLIF(COUNT(*),0), 0)
     FROM attendance WHERE class_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    [],
    0
);

$departments = db_all("SELECT * FROM departments ORDER BY name");
$notices     = db_all("SELECT * FROM notices WHERE is_published = 1 ORDER BY created_at DESC LIMIT 4");
$exams       = db_all(
    "SELECT e.*, c.code, c.title
     FROM exams e
     JOIN courses c ON c.id = e.course_id
     ORDER BY e.exam_date, e.start_time
     LIMIT 6"
);

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> &ndash; <?= e(APP_CAMPUS) ?></title>
  <meta name="description" content="Gollis University Gabiley Campus - academic programs, campus notices, examinations and the student management portal.">
  <link rel="icon" href="<?= url('assets/images/logo.jpg') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<div class="topbar">
  <div>📍 <?= e(APP_ADDRESS) ?></div>
  <div>📞 <?= e(APP_PHONE) ?> &nbsp; | &nbsp; ✉ <?= e(APP_EMAIL) ?></div>
</div>

<header class="site-header">
  <nav>
    <a class="brand" href="#home">
      <img class="brand-logo" src="<?= url('assets/images/logo.jpg') ?>" alt="<?= e(APP_NAME) ?> logo">
      <div><?= e(APP_NAME) ?><span><?= e(strtoupper(APP_CAMPUS)) ?></span></div>
    </a>
    <div class="links">
      <a href="#home">Home</a>
      <a href="#programs">Programs</a>
      <a href="#exams">Exams</a>
      <a href="#notices">Notices</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
      <?php if (is_logged_in()): ?>
        <a class="btn btn-primary" href="<?= url('dashboard.php') ?>">My dashboard</a>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= url('login.php') ?>">Portal login</a>
      <?php endif; ?>
    </div>
    <button class="menu" onclick="toggleMenu()" aria-label="Open menu">☰</button>
  </nav>
</header>

<main>
  <section class="hero" id="home">
    <div class="hero-inner">
      <div class="badge">Welcome to <?= e(APP_NAME) ?> &ndash; <?= e(APP_CAMPUS) ?></div>
      <h1>Learn. Lead. Serve.</h1>
      <p>A modern university experience focused on academic excellence, practical skills, innovation, and service to the community.</p>
      <a class="btn btn-primary" href="#programs">Explore Programs</a>
      <a class="btn btn-outline" href="<?= url('login.php') ?>">Student &amp; Staff Portal</a>
    </div>
  </section>

  <section class="dashboard" id="overview">
    <div class="container">
      <div class="section-title">
        <h2>Campus at a Glance</h2>
        <p>Figures come straight from the university management system.</p>
      </div>
      <div class="dashboard-grid">
        <div class="dash-card">
          <div class="label">ACTIVE STUDENTS</div>
          <div class="number"><?= number_format($totalStudents) ?></div>
          <div>Registered this year</div>
        </div>
        <div class="dash-card">
          <div class="label">ACADEMIC STAFF</div>
          <div class="number"><?= number_format($totalLecturers) ?></div>
          <div>Lecturers and faculty</div>
        </div>
        <div class="dash-card">
          <div class="label">COURSES</div>
          <div class="number"><?= number_format($totalCourses) ?></div>
          <div>Across <?= number_format($totalPrograms) ?> departments</div>
        </div>
        <div class="dash-card">
          <div class="label">ATTENDANCE</div>
          <div class="number"><?= percent($attendanceRate) ?></div>
          <div>Average over 30 days</div>
        </div>
      </div>

      <div class="dashboard-panel">
        <h3>Quick Overview</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Item</th><th>Current status</th><th>Details</th></tr></thead>
            <tbody>
              <tr><td>Admissions</td><td><?= status_badge('open') ?></td><td>New student applications are welcome at the registry</td></tr>
              <tr><td>Semester fees</td><td><?= status_badge('pending') ?></td><td>Payment period for <?= e(CURRENT_YEAR) ?> is active</td></tr>
              <tr><td>Final exams</td><td><?= status_badge('upcoming') ?></td><td>See the timetable below</td></tr>
              <tr><td>Results</td><td><?= status_badge('published') ?></td><td>Sign in to the portal to view your marks</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <section id="programs">
    <div class="container">
      <div class="section-title">
        <h2>Academic Programs</h2>
        <p>Departments currently offered at the Gabiley campus.</p>
      </div>
      <div class="cards programs">
        <?php foreach ($departments as $department): ?>
          <div class="card program">
            <div class="icon"><?= e($department['icon'] ?: '🎓') ?></div>
            <h3><?= e($department['name']) ?></h3>
            <p><?= e($department['description']) ?></p>
          </div>
        <?php endforeach; ?>
        <?php if (!$departments): ?>
          <p class="hint">No departments have been added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="about" id="exams">
    <div class="container">
      <div class="section-title">
        <h2>Examination Timetable</h2>
        <p>Upcoming examinations for <?= e(CURRENT_YEAR) ?>.</p>
      </div>
      <div class="dashboard-panel">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Course</th><th>Code</th><th>Time</th><th>Room</th></tr></thead>
            <tbody>
              <?php foreach ($exams as $exam): ?>
                <tr>
                  <td><?= e(fdate($exam['exam_date'])) ?></td>
                  <td><?= e($exam['title']) ?></td>
                  <td><?= e($exam['code']) ?></td>
                  <td><?= e(ftime($exam['start_time'])) ?></td>
                  <td><?= e($exam['room'] ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$exams): ?>
                <tr><td colspan="5" class="table-empty">The timetable has not been published yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <section class="notice" id="notices">
    <div class="container">
      <div class="section-title">
        <h2>Campus Notices</h2>
        <p>Important information for students and visitors.</p>
      </div>
      <div class="notice-list">
        <?php foreach ($notices as $notice): ?>
          <div class="notice-item">
            <strong><?= e($notice['title']) ?></strong><br>
            <small><?= e($notice['body']) ?></small><br>
            <small><?= e(fdate($notice['created_at'])) ?></small>
          </div>
        <?php endforeach; ?>
        <?php if (!$notices): ?>
          <div class="notice-item"><small>There are no notices at the moment.</small></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="about" id="about">
    <div class="container about-grid">
      <div>
        <div class="section-title" style="text-align:left;margin-bottom:20px">
          <h2>About Our Campus</h2>
          <p>Building knowledge and opportunity in Gabiley.</p>
        </div>
        <p><?= e(APP_NAME) ?> <?= e(APP_CAMPUS) ?> serves students from Gabiley and the surrounding districts with
           programs in technology, business, health and education. This site is the public face of the campus
           management system, which keeps student records, attendance, results and fees in one place.</p>
        <p style="margin-top:15px;color:var(--muted)">Students, lecturers and administrators sign in to the portal
           to work with their own records.</p>
      </div>
      <div class="about-box">
        <h3>Our Vision</h3>
        <p>To inspire a generation of knowledgeable, ethical, and innovative graduates who contribute positively to
           Somaliland and the wider world.</p>
        <div class="stats">
          <div class="stat"><strong><?= number_format($totalPrograms) ?></strong>Departments</div>
          <div class="stat"><strong><?= number_format($totalStudents) ?></strong>Students</div>
          <div class="stat"><strong><?= number_format($totalLecturers) ?></strong>Faculty</div>
          <div class="stat"><strong><?= number_format($totalCourses) ?></strong>Courses</div>
        </div>
      </div>
    </div>
  </section>

  <section>
    <div class="container">
      <div class="section-title">
        <h2>Why Choose Gollis University?</h2>
        <p>Designed around students, skills, and opportunity.</p>
      </div>
      <div class="cards">
        <div class="card"><div class="icon">🌱</div><h3>Student Growth</h3><p>Supportive learning experiences that help students develop confidence and practical skills.</p></div>
        <div class="card"><div class="icon">🔬</div><h3>Practical Learning</h3><p>Classroom knowledge connected to real projects, research and community needs.</p></div>
        <div class="card"><div class="icon">🤝</div><h3>Community Impact</h3><p>Responsible leadership and meaningful contributions to local communities.</p></div>
      </div>
    </div>
  </section>

  <section class="contact" id="contact">
    <div class="container contact-grid">
      <div>
        <h2>Get in Touch</h2>
        <p>Have a question about admissions, programs or campus life? Send a message and the campus office will reply.</p>
        <p style="margin-top:20px">📍 <?= e(APP_ADDRESS) ?><br>📞 <?= e(APP_PHONE) ?><br>✉ <?= e(APP_EMAIL) ?></p>
      </div>
      <div>
        <?php foreach ($flashes as $message): ?>
          <div class="alert <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($formError !== ''): ?>
          <div class="alert error"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= url('index.php') ?>#contact">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact">
          <input name="name" placeholder="Your name" maxlength="120" required>
          <input name="email" type="email" placeholder="Your email" maxlength="120" required>
          <textarea name="message" placeholder="Your message" required></textarea>
          <button class="btn btn-primary" type="submit">Send Message</button>
        </form>
      </div>
    </div>
  </section>
</main>

<footer class="site-footer">
  &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &ndash; <?= e(APP_CAMPUS) ?> &nbsp;|&nbsp;
  University Management System &nbsp;|&nbsp;
  <a href="<?= url('login.php') ?>">Portal login</a>
</footer>

<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
