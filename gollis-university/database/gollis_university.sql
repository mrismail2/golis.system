-- =====================================================================
--  Gollis University - Gabiley Campus
--  University Management System - MySQL / MariaDB schema and demo data
-- ---------------------------------------------------------------------
--  Import:
--      mysql -u root -p < database/gollis_university.sql
--  or through phpMyAdmin -> Import.
--
--  Demo logins (change them after the first import):
--      admin    / admin123
--      ahassan  / lecturer123      (lecturer)
--      GU-GAB-1001 / student123    (student)
-- =====================================================================

DROP DATABASE IF EXISTS `gollis_university`;
CREATE DATABASE `gollis_university`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `gollis_university`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users : every person who can sign in (admin, lecturer, student)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(60)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(120) NOT NULL,
    `email`         VARCHAR(120) DEFAULT NULL,
    `role`          ENUM('admin','lecturer','student') NOT NULL DEFAULT 'student',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login`    DATETIME     DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- departments / faculties
-- ---------------------------------------------------------------------
CREATE TABLE `departments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(10)  NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `icon`        VARCHAR(10)  DEFAULT NULL,
    `description` TEXT         DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- lecturers (academic staff)
-- ---------------------------------------------------------------------
CREATE TABLE `lecturers` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED DEFAULT NULL,
    `staff_no`       VARCHAR(20)  NOT NULL,
    `full_name`      VARCHAR(120) NOT NULL,
    `department_id`  INT UNSIGNED DEFAULT NULL,
    `specialization` VARCHAR(120) DEFAULT NULL,
    `qualification`  VARCHAR(120) DEFAULT NULL,
    `email`          VARCHAR(120) DEFAULT NULL,
    `phone`          VARCHAR(30)  DEFAULT NULL,
    `photo`          VARCHAR(160) DEFAULT NULL,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `hired_on`       DATE         DEFAULT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lecturers_staff_no` (`staff_no`),
    KEY `ix_lecturers_department` (`department_id`),
    KEY `ix_lecturers_user` (`user_id`),
    CONSTRAINT `fk_lecturers_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lecturers_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- students
-- ---------------------------------------------------------------------
CREATE TABLE `students` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `reg_no`        VARCHAR(20)  NOT NULL,
    `full_name`     VARCHAR(120) NOT NULL,
    `gender`        ENUM('male','female') DEFAULT NULL,
    `date_of_birth` DATE         DEFAULT NULL,
    `department_id` INT UNSIGNED DEFAULT NULL,
    `year_of_study` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `email`         VARCHAR(120) DEFAULT NULL,
    `phone`         VARCHAR(30)  DEFAULT NULL,
    `address`       VARCHAR(160) DEFAULT NULL,
    `photo`         VARCHAR(160) DEFAULT NULL,
    `status`        ENUM('active','graduated','suspended','withdrawn') NOT NULL DEFAULT 'active',
    `enrolled_on`   DATE         DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_reg_no` (`reg_no`),
    KEY `ix_students_department` (`department_id`),
    KEY `ix_students_user` (`user_id`),
    CONSTRAINT `fk_students_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- courses
-- ---------------------------------------------------------------------
CREATE TABLE `courses` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(15)  NOT NULL,
    `title`         VARCHAR(140) NOT NULL,
    `department_id` INT UNSIGNED DEFAULT NULL,
    `lecturer_id`   INT UNSIGNED DEFAULT NULL,
    `credit_hours`  TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `year_level`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `semester`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `description`   TEXT         DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_courses_code` (`code`),
    KEY `ix_courses_department` (`department_id`),
    KEY `ix_courses_lecturer` (`lecturer_id`),
    CONSTRAINT `fk_courses_department` FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_courses_lecturer` FOREIGN KEY (`lecturer_id`)
        REFERENCES `lecturers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- enrollments : which student takes which course, in which term
-- ---------------------------------------------------------------------
CREATE TABLE `enrollments` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`    INT UNSIGNED NOT NULL,
    `course_id`     INT UNSIGNED NOT NULL,
    `academic_year` VARCHAR(9)   NOT NULL,
    `semester`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `enrolled_on`   DATE         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enrollment` (`student_id`,`course_id`,`academic_year`,`semester`),
    KEY `ix_enrollments_course` (`course_id`),
    CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- attendance : one row per student / course / day
-- ---------------------------------------------------------------------
CREATE TABLE `attendance` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  INT UNSIGNED NOT NULL,
    `course_id`   INT UNSIGNED NOT NULL,
    `class_date`  DATE         NOT NULL,
    `status`      ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `remarks`     VARCHAR(160) DEFAULT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance_day` (`student_id`,`course_id`,`class_date`),
    KEY `ix_attendance_course_date` (`course_id`,`class_date`),
    CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_user` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- results : marks per student / course / term
-- ---------------------------------------------------------------------
CREATE TABLE `results` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`    INT UNSIGNED NOT NULL,
    `course_id`     INT UNSIGNED NOT NULL,
    `academic_year` VARCHAR(9)   NOT NULL,
    `semester`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `coursework`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `exam_marks`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `total_marks`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `grade`         VARCHAR(3)   NOT NULL DEFAULT 'F',
    `grade_points`  DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `is_published`  TINYINT(1)   NOT NULL DEFAULT 0,
    `recorded_by`   INT UNSIGNED DEFAULT NULL,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_result` (`student_id`,`course_id`,`academic_year`,`semester`),
    KEY `ix_results_course` (`course_id`),
    CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_results_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_results_user` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- fees : one invoice per student per term, paid off by payments
-- ---------------------------------------------------------------------
CREATE TABLE `fees` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     INT UNSIGNED NOT NULL,
    `academic_year`  VARCHAR(9)   NOT NULL,
    `semester`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `fee_type`       ENUM('tuition','registration','exam','library','other') NOT NULL DEFAULT 'tuition',
    `amount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `due_date`       DATE         DEFAULT NULL,
    `description`    VARCHAR(160) DEFAULT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fee_term` (`student_id`,`academic_year`,`semester`,`fee_type`),
    CONSTRAINT `fk_fees_student` FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fee_id`      INT UNSIGNED NOT NULL,
    `receipt_no`  VARCHAR(25)  NOT NULL,
    `amount`      DECIMAL(10,2) NOT NULL,
    `paid_on`     DATE         NOT NULL,
    `method`      ENUM('cash','zaad','edahab','bank','cheque') NOT NULL DEFAULT 'cash',
    `note`        VARCHAR(160) DEFAULT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_receipt` (`receipt_no`),
    KEY `ix_payments_fee` (`fee_id`),
    CONSTRAINT `fk_payments_fee` FOREIGN KEY (`fee_id`)
        REFERENCES `fees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- exams : examination timetable shown on the public site
-- ---------------------------------------------------------------------
CREATE TABLE `exams` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`     INT UNSIGNED NOT NULL,
    `exam_date`     DATE         NOT NULL,
    `start_time`    TIME         NOT NULL DEFAULT '09:00:00',
    `duration_mins` SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    `room`          VARCHAR(40)  DEFAULT NULL,
    `academic_year` VARCHAR(9)   NOT NULL,
    `semester`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `ix_exams_course` (`course_id`),
    CONSTRAINT `fk_exams_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notices : campus announcements on the public home page
-- ---------------------------------------------------------------------
CREATE TABLE `notices` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(140) NOT NULL,
    `body`        TEXT         NOT NULL,
    `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
    `posted_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_notices_user` FOREIGN KEY (`posted_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- messages : submissions from the public contact form
-- ---------------------------------------------------------------------
CREATE TABLE `messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `email`      VARCHAR(120) NOT NULL,
    `body`       TEXT         NOT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DEMO DATA
-- =====================================================================

-- users -----------------------------------------------------------------
-- admin123 / lecturer123 / student123 (bcrypt hashes)
INSERT INTO `users` (`id`,`username`,`password_hash`,`full_name`,`email`,`role`) VALUES
(1,'admin',      '$2y$10$AS0zu3./CDIDJATRwy5lcObbi7rMoxv0JVzrqpr62MM.tc6aRu.Tm','System Administrator','admin@golisuniversity.edu','admin'),
(2,'ahassan',    '$2y$10$/bdCox58p7QTsNAivSRfbOyt7wd6U7KCJfVj7Zf/CyvlYQAW3dDZO','Dr. Ahmed Hassan','a.hassan@golisuniversity.edu','lecturer'),
(3,'amohamed',   '$2y$10$/bdCox58p7QTsNAivSRfbOyt7wd6U7KCJfVj7Zf/CyvlYQAW3dDZO','Ms. Amina Mohamed','a.mohamed@golisuniversity.edu','lecturer'),
(4,'anoor',      '$2y$10$/bdCox58p7QTsNAivSRfbOyt7wd6U7KCJfVj7Zf/CyvlYQAW3dDZO','Mr. Abdi Noor','a.noor@golisuniversity.edu','lecturer'),
(5,'sali',       '$2y$10$/bdCox58p7QTsNAivSRfbOyt7wd6U7KCJfVj7Zf/CyvlYQAW3dDZO','Dr. Sahra Ali','s.ali@golisuniversity.edu','lecturer'),
(6,'GU-GAB-1001','$2y$10$RQwqCacQAJCgvhC0xGy3iO6M92leaS6ewJDW8CS.EkEg/ok4qxKyy','Ahmed Mohamed Ali','ahmed.ali@student.golisuniversity.edu','student'),
(7,'GU-GAB-1002','$2y$10$RQwqCacQAJCgvhC0xGy3iO6M92leaS6ewJDW8CS.EkEg/ok4qxKyy','Hodan Abdi Hassan','hodan.hassan@student.golisuniversity.edu','student'),
(8,'GU-GAB-1003','$2y$10$RQwqCacQAJCgvhC0xGy3iO6M92leaS6ewJDW8CS.EkEg/ok4qxKyy','Mohamed Yusuf Omar','mohamed.omar@student.golisuniversity.edu','student'),
(9,'GU-GAB-1004','$2y$10$RQwqCacQAJCgvhC0xGy3iO6M92leaS6ewJDW8CS.EkEg/ok4qxKyy','Fatima Ibrahim Ali','fatima.ali@student.golisuniversity.edu','student'),
(10,'GU-GAB-1005','$2y$10$RQwqCacQAJCgvhC0xGy3iO6M92leaS6ewJDW8CS.EkEg/ok4qxKyy','Abdirahman Ismail Noor','abdirahman.noor@student.golisuniversity.edu','student');

-- departments -----------------------------------------------------------
INSERT INTO `departments` (`id`,`code`,`name`,`icon`,`description`) VALUES
(1,'CS','Computer Science','💻','Programming, software development, databases, networking and digital technology.'),
(2,'BA','Business Administration','📊','Management, entrepreneurship, accounting, marketing and leadership.'),
(3,'HS','Health Sciences','🏥','Foundations of health, community care, research and professional practice.'),
(4,'ED','Education','🎓','Teaching methods, curriculum development, educational leadership and research.');

-- lecturers -------------------------------------------------------------
INSERT INTO `lecturers` (`id`,`user_id`,`staff_no`,`full_name`,`department_id`,`specialization`,`qualification`,`email`,`phone`,`hired_on`) VALUES
(1,2,'TC-001','Dr. Ahmed Hassan',1,'Software Engineering','PhD Computer Science','a.hassan@golisuniversity.edu','+252 63 4110001','2019-09-01'),
(2,3,'TC-002','Ms. Amina Mohamed',2,'Accounting & Finance','MSc Finance','a.mohamed@golisuniversity.edu','+252 63 4110002','2020-02-10'),
(3,4,'TC-003','Mr. Abdi Noor',4,'Curriculum Studies','MEd Curriculum','a.noor@golisuniversity.edu','+252 63 4110003','2018-10-05'),
(4,5,'TC-004','Dr. Sahra Ali',3,'Public Health','PhD Public Health','s.ali@golisuniversity.edu','+252 63 4110004','2021-01-15');

-- students --------------------------------------------------------------
INSERT INTO `students` (`id`,`user_id`,`reg_no`,`full_name`,`gender`,`date_of_birth`,`department_id`,`year_of_study`,`email`,`phone`,`address`,`status`,`enrolled_on`) VALUES
(1, 6,'GU-GAB-1001','Ahmed Mohamed Ali','male','2004-03-12',1,2,'ahmed.ali@student.golisuniversity.edu','+252 63 5220001','Gabiley','active','2024-09-15'),
(2, 7,'GU-GAB-1002','Hodan Abdi Hassan','female','2003-07-22',2,3,'hodan.hassan@student.golisuniversity.edu','+252 63 5220002','Gabiley','active','2023-09-18'),
(3, 8,'GU-GAB-1003','Mohamed Yusuf Omar','male','2005-11-04',4,1,'mohamed.omar@student.golisuniversity.edu','+252 63 5220003','Wajaale','active','2025-09-20'),
(4, 9,'GU-GAB-1004','Fatima Ibrahim Ali','female','2002-01-30',3,4,'fatima.ali@student.golisuniversity.edu','+252 63 5220004','Hargeisa','active','2022-09-12'),
(5,10,'GU-GAB-1005','Abdirahman Ismail Noor','male','2004-06-18',1,2,'abdirahman.noor@student.golisuniversity.edu','+252 63 5220005','Gabiley','active','2024-09-15'),
(6,NULL,'GU-GAB-1006','Naima Ali Jama','female','2004-09-09',2,2,'naima.jama@student.golisuniversity.edu','+252 63 5220006','Gabiley','active','2024-09-15'),
(7,NULL,'GU-GAB-1007','Khadar Warsame Egeh','male','2003-12-01',1,3,'khadar.egeh@student.golisuniversity.edu','+252 63 5220007','Berbera','active','2023-09-18'),
(8,NULL,'GU-GAB-1008','Sagal Mahamed Farah','female','2005-04-25',3,1,'sagal.farah@student.golisuniversity.edu','+252 63 5220008','Gabiley','active','2025-09-20');

-- courses ---------------------------------------------------------------
INSERT INTO `courses` (`id`,`code`,`title`,`department_id`,`lecturer_id`,`credit_hours`,`year_level`,`semester`,`description`) VALUES
(1,'CS101','Introduction to Programming',1,1,3,1,1,'Problem solving and programming fundamentals.'),
(2,'CS205','Database Systems',1,1,3,2,1,'Relational modelling, SQL and database design.'),
(3,'BA201','Principles of Management',2,2,3,2,1,'Planning, organising, leading and controlling.'),
(4,'BA210','Financial Accounting',2,2,3,2,1,'Recording and reporting financial transactions.'),
(5,'ED101','Foundations of Education',4,3,3,1,1,'History, philosophy and sociology of education.'),
(6,'HS202','Community Health',3,4,4,2,1,'Health promotion and community based care.'),
(7,'CS310','Web Application Development',1,1,3,3,1,'HTML, CSS, JavaScript, PHP and MySQL.'),
(8,'HS305','Epidemiology',3,4,3,3,1,'Disease distribution, determinants and control.');

-- enrollments -----------------------------------------------------------
INSERT INTO `enrollments` (`student_id`,`course_id`,`academic_year`,`semester`,`enrolled_on`) VALUES
(1,1,'2025/2026',1,'2025-09-22'),
(1,2,'2025/2026',1,'2025-09-22'),
(2,3,'2025/2026',1,'2025-09-22'),
(2,4,'2025/2026',1,'2025-09-22'),
(3,5,'2025/2026',1,'2025-09-22'),
(4,6,'2025/2026',1,'2025-09-22'),
(4,8,'2025/2026',1,'2025-09-22'),
(5,1,'2025/2026',1,'2025-09-22'),
(5,2,'2025/2026',1,'2025-09-22'),
(6,3,'2025/2026',1,'2025-09-22'),
(7,7,'2025/2026',1,'2025-09-22'),
(8,6,'2025/2026',1,'2025-09-22');

-- attendance ------------------------------------------------------------
INSERT INTO `attendance` (`student_id`,`course_id`,`class_date`,`status`,`recorded_by`) VALUES
(1,1,'2026-08-13','present',2),(5,1,'2026-08-13','present',2),
(1,1,'2026-08-14','present',2),(5,1,'2026-08-14','late',2),
(2,3,'2026-08-14','present',3),(6,3,'2026-08-14','present',3),
(3,5,'2026-08-14','absent',4),
(4,6,'2026-08-14','present',5),(8,6,'2026-08-14','present',5),
(1,2,'2026-08-12','present',2),(5,2,'2026-08-12','absent',2),
(7,7,'2026-08-12','present',2),
(4,8,'2026-08-13','present',5);

-- results ---------------------------------------------------------------
INSERT INTO `results` (`student_id`,`course_id`,`academic_year`,`semester`,`coursework`,`exam_marks`,`total_marks`,`grade`,`grade_points`,`is_published`,`recorded_by`) VALUES
(1,1,'2025/2026',1,35.00,52.00,87.00,'A',  4.00,1,2),
(1,2,'2025/2026',1,30.00,48.00,78.00,'B+', 3.30,1,2),
(2,3,'2025/2026',1,32.00,47.00,79.00,'B+', 3.30,1,3),
(2,4,'2025/2026',1,28.00,45.00,73.00,'B',  3.00,1,3),
(3,5,'2025/2026',1,26.00,42.00,68.00,'B',  3.00,1,4),
(4,6,'2025/2026',1,36.00,55.00,91.00,'A+', 4.00,1,5),
(4,8,'2025/2026',1,33.00,50.00,83.00,'A-', 3.70,1,5),
(5,1,'2025/2026',1,29.00,43.00,72.00,'B',  3.00,1,2),
(6,3,'2025/2026',1,24.00,38.00,62.00,'C+', 2.50,1,3),
(7,7,'2025/2026',1,31.00,49.00,80.00,'A-', 3.70,1,2),
(8,6,'2025/2026',1,20.00,28.00,48.00,'F',  0.00,0,5);

-- fees and payments (tuition in USD, 180 - 250 per semester) --------------
INSERT INTO `fees` (`id`,`student_id`,`academic_year`,`semester`,`fee_type`,`amount`,`due_date`,`description`) VALUES
(1,1,'2025/2026',1,'tuition',220.00,'2025-10-30','Semester 1 tuition'),
(2,2,'2025/2026',1,'tuition',240.00,'2025-10-30','Semester 1 tuition'),
(3,3,'2025/2026',1,'tuition',200.00,'2025-10-30','Semester 1 tuition'),
(4,4,'2025/2026',1,'tuition',250.00,'2025-10-30','Semester 1 tuition'),
(5,5,'2025/2026',1,'tuition',220.00,'2025-10-30','Semester 1 tuition'),
(6,6,'2025/2026',1,'tuition',240.00,'2025-10-30','Semester 1 tuition'),
(7,7,'2025/2026',1,'tuition',230.00,'2025-10-30','Semester 1 tuition'),
(8,8,'2025/2026',1,'tuition',180.00,'2025-10-30','Semester 1 tuition');

INSERT INTO `payments` (`fee_id`,`receipt_no`,`amount`,`paid_on`,`method`,`recorded_by`) VALUES
(1,'RCP-2026-0001',220.00,'2025-10-12','zaad',1),
(2,'RCP-2026-0002',180.00,'2025-10-14','cash',1),
(3,'RCP-2026-0003',200.00,'2025-10-15','edahab',1),
(4,'RCP-2026-0004',200.00,'2025-10-18','bank',1),
(5,'RCP-2026-0005',120.00,'2025-10-20','zaad',1),
(6,'RCP-2026-0006',240.00,'2025-10-21','cash',1),
(7,'RCP-2026-0007',100.00,'2025-11-02','zaad',1);

-- exams -----------------------------------------------------------------
INSERT INTO `exams` (`course_id`,`exam_date`,`start_time`,`duration_mins`,`room`,`academic_year`,`semester`) VALUES
(1,'2026-08-15','09:00:00',120,'Room A1','2025/2026',1),
(3,'2026-08-17','09:00:00',120,'Room B2','2025/2026',1),
(5,'2026-08-19','13:00:00',120,'Room C1','2025/2026',1),
(6,'2026-08-21','13:00:00',180,'Hall 1','2025/2026',1),
(2,'2026-08-24','09:00:00',120,'Room A2','2025/2026',1);

-- notices ---------------------------------------------------------------
INSERT INTO `notices` (`title`,`body`,`posted_by`) VALUES
('Admissions Open','Applications for the new academic intake are now being accepted at the Gabiley campus registry office.',1),
('Student Orientation','New students are invited to attend campus orientation one week before classes begin.',1),
('Library & Study Support','Students can contact the campus office for library access and academic support information.',1),
('Semester Fees','The payment period for semester one tuition is now open. Fees can be paid by Zaad, eDahab, bank or cash.',1);
