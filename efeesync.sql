-- ================================
-- eFeeSync Database Schema (Improved Version) --
-- ================================

-- RESET DATABASE

DROP DATABASE IF EXISTS efeesync; CREATE DATABASE efeesync; USE efeesync;

-- CREATE BASE TABLES

-- ================================
-- SYSTEM & USERS
-- ================================
CREATE TABLE system_settings (
    system_id INT PRIMARY KEY AUTO_INCREMENT,
    campus VARCHAR(25) NOT NULL,
    color VARCHAR(6) NOT NULL
) AUTO_INCREMENT = 1001;

CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL
) AUTO_INCREMENT = 101;

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    institutional_email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    first_name VARCHAR(75) NOT NULL,
    middle_initial VARCHAR(3),
    picture VARCHAR(50) DEFAULT 'default.jpg',
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 100001;

-- ================================
-- DEPARTMENTS & PROGRAMS
-- ================================
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_code VARCHAR(10) NOT NULL UNIQUE,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    department_color VARCHAR(7) NOT NULL
) AUTO_INCREMENT = 1001;

CREATE TABLE programs (
    program_id INT PRIMARY KEY AUTO_INCREMENT,
    program_code VARCHAR(10) NOT NULL UNIQUE,
    program_name VARCHAR(100) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- ================================
-- ORGANIZATIONS & BUDGETS
-- ================================
CREATE TABLE organizations (
    organization_id INT PRIMARY KEY AUTO_INCREMENT,
    organization_code VARCHAR(10) NOT NULL,
    organization_name VARCHAR(100) NOT NULL,
    organization_logo VARCHAR(50) DEFAULT 'default.jpg',
    budget_initial_calibration DECIMAL(10,2) NOT NULL DEFAULT 0, -- moved budget calibration here
    department_id INT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE budget_deductions (
    budget_deduction_id INT PRIMARY KEY AUTO_INCREMENT,
    budget_deduction_title VARCHAR(50) NOT NULL,
    budget_deduction_reason TEXT NULL,
    budget_deduction_amount DECIMAL(10,2) NOT NULL,
    budget_deduction_image_proof VARCHAR(50) NULL,
    budget_deducted_at DATE,
    organization_id INT NOT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(organization_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- ================================
-- EVENTS
-- ================================
CREATE TABLE events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(50) NOT NULL,
    event_description TEXT NOT NULL,
    event_target_year_levels SET('1','2','3','4') NOT NULL DEFAULT '1,2,3,4',
    event_start_date DATE NOT NULL,
    event_end_date DATE,
    is_separate_day BOOLEAN NOT NULL DEFAULT FALSE,
    event_picture VARCHAR(255) DEFAULT 'default.jpg',
    event_sanction_has_comserv BOOLEAN NOT NULL DEFAULT FALSE,
    organization_id INT NOT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(organization_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- ================================
-- STUDENTS & OFFICERS
-- ================================
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    student_number_id VARCHAR(10) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    student_section VARCHAR(3) NOT NULL,
    student_current_program INT NULL,
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_current_program) REFERENCES programs(program_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE organization_officers (
    organization_officer_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    designation VARCHAR(50) DEFAULT 'treasurer',
    organization_id INT NOT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(organization_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- ================================
-- NOTIFICATIONS
-- ================================
-- CREATE TABLE notifications (
--     notification_id INT PRIMARY KEY AUTO_INCREMENT,
--     notification_type VARCHAR(20) NOT NULL, -- event, sanction, system, etc.
--     notification_content TEXT NOT NULL,
--     url_redirect VARCHAR(255) NOT NULL,
--     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
-- ) AUTO_INCREMENT = 1001;
-- 
-- -- Who should receive it (audience definition)
-- CREATE TABLE notification_targets (
--     target_id INT PRIMARY KEY AUTO_INCREMENT,
--     notification_id INT NOT NULL,
--     scope ENUM('user','role','org','global') NOT NULL,
--     year_levels SET('1','2','3','4') NOT NULL DEFAULT '1,2,3,4',
--     user_id INT NULL,
--     role_id INT NULL,
--     organization_id INT NULL,
--     FOREIGN KEY (notification_id) REFERENCES notifications(notification_id)
--         ON DELETE CASCADE ON UPDATE CASCADE,
--     FOREIGN KEY (user_id) REFERENCES users(user_id)
--         ON DELETE CASCADE ON UPDATE CASCADE,
--     FOREIGN KEY (role_id) REFERENCES roles(role_id)
--         ON DELETE CASCADE ON UPDATE CASCADE,
--     FOREIGN KEY (organization_id) REFERENCES organizations(organization_id)
--         ON DELETE CASCADE ON UPDATE CASCADE
-- ) AUTO_INCREMENT = 1001;
-- 
-- -- Track which users read it
-- CREATE TABLE notification_reads (
--     read_id INT PRIMARY KEY AUTO_INCREMENT,
--     notification_id INT NOT NULL,
--     user_id INT NOT NULL,
--     read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (notification_id) REFERENCES notifications(notification_id)
--         ON DELETE CASCADE ON UPDATE CASCADE,
--     FOREIGN KEY (user_id) REFERENCES users(user_id)
--         ON DELETE CASCADE ON UPDATE CASCADE
-- ) AUTO_INCREMENT = 1001;


-- ================================
-- CONTRIBUTIONS & SANCTIONS
-- ================================
CREATE TABLE event_contributions (
    event_contri_id INT PRIMARY KEY AUTO_INCREMENT,
    event_contri_due_date DATE NOT NULL,
    event_contri_fee DECIMAL(10,2) NOT NULL,
    event_contri_sanction_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    event_id INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE contributions_made (
    contribution_id INT PRIMARY KEY AUTO_INCREMENT,
    event_contri_id INT NOT NULL,
    student_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_contri_id) REFERENCES event_contributions(event_contri_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- CREATE TABLE paid_contribution_sanctions (
--     paid_contri_sanction_id INT PRIMARY KEY AUTO_INCREMENT,
--     student_id INT NOT NULL,
--     event_contri_id INT NOT NULL,
--     amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
--     payment_type VARCHAR(20),
--     online_payment_proof VARCHAR(255),
-- 	payment_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
--     paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (student_id) REFERENCES students(student_id)
--         ON DELETE CASCADE ON UPDATE CASCADE,
--     FOREIGN KEY (event_contri_id) REFERENCES event_contributions(event_contri_id)
--         ON DELETE CASCADE ON UPDATE CASCADE
-- ) AUTO_INCREMENT = 1001;

-- ================================
-- ATTENDANCE
-- ================================
CREATE TABLE event_attendance_dates (
    event_attend_date_id INT PRIMARY KEY AUTO_INCREMENT,
    event_attend_date DATE NOT NULL,
    event_id INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE event_attendance_times (
    event_attend_time_id INT PRIMARY KEY AUTO_INCREMENT,
    event_attend_time ENUM('AM IN','AM OUT','PM IN','PM OUT') NOT NULL DEFAULT 'AM IN',
    event_attend_sanction_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    event_attend_date_id INT NOT NULL,
    FOREIGN KEY (event_attend_date_id) REFERENCES event_attendance_dates(event_attend_date_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE attendance_made (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    event_attend_time_id INT NOT NULL,
    student_id INT NOT NULL,
    time_log DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_attend_time_id) REFERENCES event_attendance_times(event_attend_time_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE paid_attendance_sanctions (
    paid_attend_sanction_id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
	payment_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE attendance_excuse (
    attendance_excuse_id INT PRIMARY KEY AUTO_INCREMENT,
    attendance_excuse_reason TEXT,
    attendance_excuse_proof_file VARCHAR(255),
    attendance_excuse_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    attendance_excuse_submitted_at DATE NOT NULL DEFAULT (CURRENT_DATE()),
    event_attend_date_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (event_attend_date_id) REFERENCES event_attendance_dates(event_attend_date_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- ================================
-- ONLINE PAYMENTS FOR CONTRIBUTIONS AND ATTENDANCE SANCTIONS
-- ================================

CREATE TABLE online_payments (
    online_payment_id INT AUTO_INCREMENT PRIMARY KEY,
--  reference_no VARCHAR(100) NOT NULL,             -- GCash/Bank ref
    method VARCHAR(50) NOT NULL DEFAULT 'GCASH',    -- GCash, Bank Transfer, etc.
    image_proof VARCHAR(255),                       -- proof image/file
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    student_id INT NOT NULL,
    status ENUM('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE online_payment_contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    online_payment_id INT NOT NULL,
    contribution_id INT NOT NULL,
    FOREIGN KEY (online_payment_id) REFERENCES online_payments(online_payment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (contribution_id) REFERENCES contributions_made(contribution_id) ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

-- 
-- CREATE TABLE online_payment_attendance_sanctions (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     online_payment_id INT NOT NULL,
--     paid_attend_sanction_id INT NOT NULL,
--     FOREIGN KEY (online_payment_id) REFERENCES online_payments(online_payment_id),
--     FOREIGN KEY (paid_attend_sanction_id) REFERENCES paid_attendance_sanctions(paid_attend_sanction_id)
-- ) AUTO_INCREMENT = 1001;

-- ================================
-- PROGRAM HISTORY & COMSERV
-- ================================
CREATE TABLE student_programs_taken (
    student_program_taken_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    program_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    shift_status ENUM('PENDING','APPROVED','REJECTED') NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(program_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

CREATE TABLE community_service_made (
    comserv_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) AUTO_INCREMENT = 1001;

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(101,	'admin'),
(102,	'student');

-- Passwords were all 111
INSERT INTO `users` (`user_id`, `institutional_email`, `password`, `role_id`, `last_name`, `first_name`, `middle_initial`, `picture`) VALUES
(100001,	'admin@cbsua.edu.ph',	'$2y$10$BO3FUTiAZXv7nMhNn7DSu.NIAcMm4UsIpVVyJ5A4CTj90HmRkx4N2',	101,	'OSAS',	'ADMIN',	'MI',	'default.jpg'),
(100002,	'cit.treasurer@cbsua.edu.ph',	'$2y$10$cRnaAYqfwT2xur7J20ig1up2e87iAQ8QK7oR2n.GNvDBW2M48vxQi',	102,	'TREASURER',	'CIT',	'MI',	'default.jpg'),
(100003,	'cit.student@cbsua.edu.ph',	'$2y$10$eeWzE7/NH6eSsIeTax2iJucBR52Ct8pUxGzRdxP1NC7.zw/W3Bbj2',	102,	'STUDENT ',	'CIT',	'MI',	'default.jpg'),
(100004,	'cot.treasurer@cbsua.edu.ph',	'$2y$10$XzngyZHNNYOPEQL0zuvO3OWIMBbaGv8CDoyJUvBvwJ3L9bn4wFaaC',	102,	'TREASURER',	'COT',	'MI',	'default.jpg'),
(100005,	'cot.student@cbsua.edu.ph',	'$2y$10$OIpyQM94jo0M8XGtfpOsvOSpjBwZI.lD4SChiPt9.lZOgoCy5TcXO',	102,	'STUDENT ',	'COT',	'MI',	'default.jpg'),
(100006,	'coc.treasurer@cbsua.edu.ph',	'$2y$10$cf59TOBvHHRKYSHSjs213u1F/1v0Keqhm5hiZB1A3ERhIKlSfo4s.',	102,	'TREASURER',	'COC',	'MI',	'default.jpg'),
(100007,	'coc.student@cbsua.edu.ph',	'$2y$10$ToUr5Viy8PFbcGEAXDuokeGDdp4J6X/cq6NBanRFIhzUSc0nhfsey',	102,	'STUDENT ',	'COC',	'MI',	'default.jpg'),
(100008,	'coe.treasurer@cbsua.edu.ph',	'$2y$10$QiA2aUtq1XKO17FjtEmVO./ScNI4PKe0UVPqDoRFrSIwltKu7rzyy',	102,	'TREASURER',	'COE',	'MI',	'default.jpg'),
(100009,	'coe.student@cbsua.edu.ph',	'$2y$10$QhQyQOsX7XOBq8/.VvaOy.V9HO0CTnidoFwBA6IwEc6Kpd7sLlLrK',	102,	'STUDENT ',	'COE',	'MI',	'default.jpg'),
(100010,	'esaf.treasurer@cbsua.edu.ph',	'$2y$10$luz2U150SZHquZ7WqZD8JOAOpp1J4GTpYc3agS5lnr4cF9rhebhoC',	102,	'TREASURER',	'ESAF',	'MI',	'default.jpg'),
(100011,	'esaf.student@cbsua.edu.ph',	'$2y$10$cGvWdfEQK/ETDeCnjQO.ru9TYRXzRB2mn0ZzOiawBatV0oXphciS6',	102,	'STUDENT ',	'ESAF',	'MI',	'default.jpg'),
(100012,	'ssc@cbsua.edu.ph',	'$2y$10$QwpJ/o65HnXKe8y6bcH7cuLGFELoiGDmcyzDFr3c/OTBnpg/SWxR.',	102,	'SSC',	'SSC',	'MI',	'default.jpg');

INSERT INTO `departments` (`department_id`, `department_code`, `department_name`, `department_color`) VALUES
(1001, 'CIT',	'College of Information Technology', '#4F1C51'),
(1002, 'COT',	'College of Industrial Technology', '#FFD95F'),
(1003, 'COC',	'College of Criminology', '#3A0519'),
(1004, 'COE',	'College of Education', '#0E2148'),
(1005, 'ESAF',	'Environmental Science and Agroforestry Program', '#4B352A');

INSERT INTO `programs` (`program_id`, `program_code`, `program_name`, `department_id`) VALUES
(1001,	'BSINFOTECH',	'BS Information Technology',	1001),
(1002,	'BSINDUST',	'BS Industrial Technlogy',	1002),
(1003,	'BSCRIM',	'BS Criminology',	1003),
(1004,	'BSED',	'BS Secondary Education',	1004),
(1005,	'BEED',	'BS Elementary Education',	1004),
(1006,	'BSAGRO',	'BS Agroforestry',	1005),
(1007,	'BSES',	'BS Environmental Science',	1005);

INSERT INTO `organizations` (`organization_id`, `organization_code`, `organization_name`, `organization_logo`, `department_id`) VALUES
(1001,	'CITSC',	'College of Information Technology Student Council',	'default.jpg', 1001),
(1002,	'COTSC',	'College of Industrial Technology Student Council',	'default.jpg', 1002),
(1003,	'CCSC',	'College of Criminology Student Council',	'default.jpg', 1003),
(1004,	'CESC',	'College of Education Student Council',	'default.jpg',	1004),
(1005,	'SCEAP',	'Student Council of Environmental Science and Agroforestry Program', 'default.jpg',	1005),
(1006,	'SSC',	'Supreme Student Council',	'default.jpg', NULL);

INSERT INTO `students` (`student_id`, `student_number_id`, `user_id`, `student_section`, `student_current_program`) VALUES
(1001,	'23-5374',	100002,	'3B',	1001),
(1002,	'23-2658',	100003,	'3C',	1001),
(1003,	'23-4521',	100004,	'3A',	1002),
(1004,	'23-4671',	100005,	'3D',	1002),
(1005,	'23-1624',	100006,	'3D',	1003),
(1006,	'23-3891',	100007,	'3A',	1003),
(1007,	'23-2461',	100008,	'3C',	1004),
(1008,	'23-7524',	100009,	'3E',	1005),
(1009,	'23-2582',	100010,	'3F',	1006),
(1010,	'23-0234',	100011,	'3D',	1006),
(1011,	'23-2438',	100012,	'3B',	1007);

INSERT INTO `organization_officers` (`organization_officer_id`, `student_id`, `designation`, `organization_id`) VALUES
(1001,	1001,	'treasurer',	1001),
(1002,	1003,	'treasurer',	1002),
(1003,	1005,	'treasurer',	1003),
(1004,	1007,	'treasurer',	1004),
(1005,	1009,	'treasurer',	1005),
(1006,	1011,	'treasurer',	1006);