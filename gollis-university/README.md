# Gollis University – Gabiley Campus

A university management system for the Gabiley campus, written in plain PHP 8 and
MySQL/MariaDB. It has two faces:

* a **public website** (`index.php`) with the campus profile, academic programs, the
  examination timetable, notices and a contact form; and
* a **management portal** (`login.php` → `dashboard.php`) where administrators,
  lecturers and students work with students, courses, attendance, results and fees.

The public pages keep the look of the original single-page design (blue `#33479d` and
gold `#ffd400`), but every figure and table now comes from the database instead of being
hard-coded.

---

## 1. Requirements

| Component | Version |
|---|---|
| PHP | 8.1 or newer, with `pdo_mysql` and `fileinfo`/`exif` enabled |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.4+ |
| Web server | Apache, Nginx, or the PHP built-in server |

XAMPP, WAMP, MAMP or Laragon all satisfy this out of the box.

## 2. Installation

1. **Copy the project** into your web root, e.g. `C:\xampp\htdocs\gollis-university`
   or `/var/www/html/gollis-university`.

2. **Create the database.** Import `database/gollis_university.sql` — it creates the
   `gollis_university` schema, every table and a set of demo records:

   ```bash
   mysql -u root -p < database/gollis_university.sql
   ```

   In phpMyAdmin: *Import → choose `database/gollis_university.sql` → Go*.

3. **Point the app at your database.** Edit the `DB_*` constants at the top of
   `config/database.php`, or set the environment variables `GU_DB_HOST`, `GU_DB_NAME`,
   `GU_DB_USER`, `GU_DB_PASS`.

4. **Make the upload folder writable** so student and lecturer photos can be saved:

   ```bash
   chmod -R 775 assets/uploads
   ```

5. **Open the site** at `http://localhost/gollis-university/` and sign in through
   *Portal login*.

   To try it without a web server:

   ```bash
   php -S localhost:8000
   ```

## 3. Demo accounts

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `admin123` |
| Lecturer | `ahassan` | `lecturer123` |
| Student | `GU-GAB-1001` | `student123` |

The other demo lecturers (`amohamed`, `anoor`, `sali`) share the lecturer password, and
students `GU-GAB-1002` … `GU-GAB-1005` share the student password.

**Change these passwords before using the system for anything real** — either from
*My profile* or from *User accounts* in the portal.

## 4. What each role can do

| Area | Administrator | Lecturer | Student |
|---|---|---|---|
| Dashboard | campus-wide figures | own courses and classes | own courses, GPA, balance |
| Students | full CRUD, portal accounts, photos | read-only directory | own record only |
| Lecturers | full CRUD, portal accounts | read-only directory | – |
| Departments | full CRUD | read-only | read-only |
| Courses | full CRUD, class lists | own courses | own enrolled courses |
| Exams | schedule and edit | read timetable | own exam timetable |
| Attendance | mark and delete any register | mark own courses | own attendance history |
| Results | enter, publish, delete | enter and publish own courses | own published results, transcript |
| Fees | invoices, payments, statements | – | own invoices and statement |
| Reports | all four reports | enrollment, attendance, results | – |
| Notices & messages | manage | – | – |

Lecturers are restricted to the courses assigned to them, and students can only ever
open their own records — the checks live in `config/auth.php`
(`require_course_access()`, `require_student_access()`).

## 5. Folder layout

```
gollis-university/
├── index.php               Public website (programs, exams, notices, contact form)
├── login.php               Portal sign in
├── logout.php              Ends the session
├── dashboard.php           Role-aware portal home
│
├── config/
│   ├── database.php        Credentials, campus settings, PDO helpers (db, db_all, …)
│   └── auth.php            Sessions, sign in, roles and access guards
│
├── includes/               Shared code used by every portal page
│   ├── functions.php       Escaping, CSRF, flashes, formatting, grading, uploads
│   ├── header.php          Sidebar + topbar layout
│   └── footer.php
│
├── admin/                  User accounts, notices, contact inbox, my profile
├── students/               Register, edit, delete, full student record
├── lecturers/              Faculty directory and profiles
├── departments/            Departments / academic programs
├── courses/                Catalog, class lists, examination timetable
├── attendance/             Daily register and attendance history
├── results/                Mark sheets, results list, printable transcript
├── fees/                   Invoices, payments, printable statement
├── reports/                Enrollment, attendance, results and fee reports
│
├── assets/
│   ├── css/style.css       One stylesheet for the website and the portal
│   ├── js/main.js          Menu, table search, confirmations, bulk register
│   ├── images/logo.jpg     Campus logo
│   └── uploads/            Student and lecturer photos (writable)
│
├── database/
│   └── gollis_university.sql   Schema + demo data
└── README.md
```

`includes/` is not in the original sketch of the project; it holds the layout and helper
code that every module shares, so the pages themselves stay short.

## 6. Database

Thirteen tables, all InnoDB with foreign keys:

| Table | Holds |
|---|---|
| `users` | portal sign-in accounts and their role |
| `departments` | faculties / academic programs |
| `lecturers` | academic staff, optionally linked to a user account |
| `students` | student register, optionally linked to a user account |
| `courses` | course catalog with credit hours, department and lecturer |
| `enrollments` | which student takes which course in which term |
| `attendance` | one row per student, course and day |
| `results` | coursework, exam marks, total, grade and grade points |
| `fees` | invoices raised against a student |
| `payments` | receipts paid against an invoice |
| `exams` | examination timetable |
| `notices` | announcements on the public website |
| `messages` | submissions from the public contact form |

Deleting a student removes their enrollments, attendance, results, invoices and
payments; deleting a department or lecturer keeps the related records and only clears
the link.

### Grading

Marks are entered as coursework (out of 40) plus examination (out of 60). The total,
grade and grade points are calculated by `grade_for()` in `includes/functions.php`:

| Total | 90+ | 85–89 | 80–84 | 75–79 | 65–74 | 60–64 | 55–59 | 50–54 | < 50 |
|---|---|---|---|---|---|---|---|---|---|
| Grade | A+ | A | A- | B+ | B | C+ | C | D | F |
| Points | 4.00 | 4.00 | 3.70 | 3.30 | 3.00 | 2.50 | 2.00 | 1.00 | 0.00 |

The pass mark is 50 (`PASS_MARK`). GPA is credit-weighted. Results stay hidden from
students until the mark sheet is saved with *Publish these results* ticked.

### Fees

Tuition is recorded in USD, typically $180–$250 per semester. An invoice can be raised
for one student or for a whole department/year at once, and payments (cash, Zaad,
eDahab, bank, cheque) are receipted against it. Balances and paid/partial/unpaid status
are derived from the payments, never stored twice.

## 7. Settings you may want to change

All in `config/database.php`:

| Constant | Purpose |
|---|---|
| `APP_NAME`, `APP_CAMPUS` | Names shown in the header, footer and printouts |
| `APP_ADDRESS`, `APP_PHONE`, `APP_EMAIL` | Contact details on the public site |
| `CURRENT_YEAR`, `CURRENT_SEMESTER` | Term that filters default to (e.g. `2025/2026`) |
| `PASS_MARK` | Minimum total marks to pass |
| `APP_CURRENCY` | Currency label used on fee screens |
| `APP_DEBUG` | Set to `false` on a live server so errors are not displayed |

## 8. Security notes

* Passwords are stored with `password_hash()` (bcrypt) and checked with
  `password_verify()`; they are never stored or logged in plain text.
* Every query uses prepared statements through the helpers in `config/database.php`.
* Every form that changes data carries a CSRF token (`csrf_field()` / `verify_csrf()`).
* All output goes through `e()`, so student names and notices cannot inject HTML.
* Photo uploads are limited to JPG/PNG/WEBP under 2 MB, renamed to a random file name,
  and `assets/uploads/.htaccess` stops anything in that folder from being executed.
* `config/` and `includes/` carry an `.htaccess` that denies direct web access. On Nginx
  add the equivalent `location` blocks, since `.htaccess` is an Apache feature.
* Turn `APP_DEBUG` off in production so database errors are not shown to visitors.

## 9. Printing

The transcript (`results/transcript.php`), fee statement (`fees/statement.php`),
examination timetable and the reports all have a **Print** button. The stylesheet hides
the sidebar, topbar and action buttons when printing, so the page comes out as a clean
document on campus letterhead.

---

© 2026 Gollis University – Gabiley Campus. Built for the campus management project;
the original public-page design was created by Rahma Mohamed.
