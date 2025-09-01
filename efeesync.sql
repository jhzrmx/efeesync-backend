-- ================================
-- eFeeSync Database Schema (Improved Version) --
-- ================================

-- RESET DATABASE

DROP DATABASE IF EXISTS efeesync; CREATE DATABASE efeesync; USE efeesync;

-- CREATE BASE TABLES

CREATE TABLE system_settings ( system_id INT PRIMARY KEY AUTO_INCREMENT, campus VARCHAR(25) NOT NULL, color VARCHAR(6) NOT NULL ) AUTO_INCREMENT = 1001;

CREATE TABLE roles ( role_id INT PRIMARY KEY AUTO_INCREMENT, role_name VARCHAR(50) NOT NULL ) AUTO_INCREMENT = 101;

CREATE TABLE users ( user_id INT PRIMARY KEY AUTO_INCREMENT, institutional_email VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(75) NOT NULL, role_id INT NOT NULL, last_name VARCHAR(50) NOT NULL, first_name VARCHAR(75) NOT NULL, middle_initial VARCHAR(3), picture VARCHAR(50) DEFAULT 'default.jpg', FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 100001;

CREATE TABLE departments ( department_id INT PRIMARY KEY AUTO_INCREMENT, department_code VARCHAR(10) NOT NULL UNIQUE, department_name VARCHAR(100) NOT NULL UNIQUE) AUTO_INCREMENT = 1001;

CREATE TABLE programs ( program_id INT PRIMARY KEY AUTO_INCREMENT, program_code VARCHAR(10) NOT NULL UNIQUE, program_name VARCHAR(100) NOT NULL UNIQUE, department_id INT NOT NULL, FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE organizations ( organization_id INT PRIMARY KEY AUTO_INCREMENT, organization_code VARCHAR(10) NOT NULL, organization_name VARCHAR(100) NOT NULL, organization_logo VARCHAR(50) DEFAULT 'default.jpg', organization_color VARCHAR(7) NOT NULL, department_id INT NULL, FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE budgets ( budget_id INT PRIMARY KEY AUTO_INCREMENT, budget_initial_calibration DECIMAL(10,2) NOT NULL, organization_id INT NOT NULL, FOREIGN KEY (organization_id) REFERENCES organizations(organization_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE budget_deductions ( budget_deduction_id INT PRIMARY KEY AUTO_INCREMENT, budget_deduction_title VARCHAR(50) NOT NULL, budget_deduction_reason TEXT NOT NULL, budget_deduction_amount DECIMAL(10,2) NOT NULL, budget_deduction_image_proof VARCHAR(50) NOT NULL, organization_id INT NOT NULL, FOREIGN KEY (organization_id) REFERENCES organizations(organization_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE events ( event_id INT PRIMARY KEY AUTO_INCREMENT, event_name VARCHAR(50) NOT NULL, event_description TEXT NOT NULL, event_target_year_levels SET('1', '2', '3', '4') NOT NULL DEFAULT '1,2,3,4', event_picture VARCHAR(255) DEFAULT 'default.jpg', event_sanction_has_comserv BOOLEAN NOT NULL DEFAULT FALSE, organization_id INT NOT NULL, FOREIGN KEY (organization_id) REFERENCES organizations(organization_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE students ( student_id INT PRIMARY KEY AUTO_INCREMENT, student_number_id VARCHAR(10) UNIQUE NOT NULL, user_id INT NOT NULL, student_section VARCHAR(2) NOT NULL, student_current_program INT NOT NULL, last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (student_current_program) REFERENCES programs(program_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE notifications ( notification_id INT PRIMARY KEY AUTO_INCREMENT, notification_type VARCHAR(20), notification_content TEXT NOT NULL, notification_read BOOLEAN NOT NULL DEFAULT FALSE, url_redirect VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id INT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE event_contributions ( event_contri_id INT PRIMARY KEY AUTO_INCREMENT, event_contri_due_date DATE NOT NULL, event_contri_cover_picture VARCHAR(50) NOT NULL, event_contri_fee DECIMAL(10,2) NOT NULL, event_contri_sanction_fee DECIMAL(10,2) NOT NULL DEFAULT 0, event_id INT NOT NULL, FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE event_attendance ( event_attend_id INT PRIMARY KEY AUTO_INCREMENT, event_attend_date DATE NOT NULL, event_attend_time SET('AM IN', 'AM OUT', 'PM IN', 'PM OUT') NOT NULL DEFAULT 'AM IN', event_attend_sanction_fee DECIMAL(10,2) NOT NULL DEFAULT 0, event_id INT NOT NULL, FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE paid_attendance_sanctions ( paid_attend_sanction_id INT PRIMARY KEY AUTO_INCREMENT, event_attend_id INT NOT NULL, student_id INT NOT NULL, paid_sanction_amount DECIMAL(10,2) NOT NULL, payment_type VARCHAR(20), online_payment_proof VARCHAR(50), FOREIGN KEY (event_attend_id) REFERENCES event_attendance(event_attend_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE attendance_made ( attendance_id INT PRIMARY KEY AUTO_INCREMENT, event_attend_id INT NOT NULL, student_id INT NOT NULL, time_type ENUM('AM IN', 'AM OUT', 'PM IN', 'PM OUT') NOT NULL, time_log DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (event_attend_id) REFERENCES event_attendance(event_attend_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE contributions_made ( contribution_id INT PRIMARY KEY AUTO_INCREMENT, event_contri_id INT NOT NULL, student_id INT NOT NULL, paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0, paid_date DATE NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (event_contri_id) REFERENCES event_contributions(event_contri_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE paid_contribution_sanctions ( paid_contri_sanction_id INT PRIMARY KEY AUTO_INCREMENT, student_id INT NOT NULL, event_contri_id INT NOT NULL, paid_sanction_amount DECIMAL(10,2) NOT NULL DEFAULT 0, payment_type VARCHAR(20), online_payment_proof VARCHAR(50), FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (event_contri_id) REFERENCES event_contributions(event_contri_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE attendance_excuse ( attendance_excuse_id INT PRIMARY KEY AUTO_INCREMENT, attendance_excuse_reason INT NOT NULL, attendance_excuse_proof_file VARCHAR(50) NOT NULL, attendance_excuse_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING', event_attend_id INT NOT NULL, FOREIGN KEY (event_attend_id) REFERENCES event_attendance(event_attend_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

CREATE TABLE student_programs_taken ( student_program_taken_id INT PRIMARY KEY AUTO_INCREMENT, student_id INT NOT NULL, program_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE, shift_status ENUM('PENDING', 'APPROVED', 'REJECTED') NULL, FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE ON UPDATE CASCADE )AUTO_INCREMENT = 1001;

CREATE TABLE community_service_made ( comserv_id INT PRIMARY KEY AUTO_INCREMENT, student_id INT NOT NULL, event_id INT NOT NULL, FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE ) AUTO_INCREMENT = 1001;

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(101,	'admin'),
(102,	'treasurer'),
(103,	'student');

-- Passwords were all 111
INSERT INTO `users` (`user_id`, `institutional_email`, `password`, `role_id`, `last_name`, `first_name`, `middle_initial`, `picture`) VALUES
(100001,	'admin@cbsua.edu.ph',	'$2y$10$BO3FUTiAZXv7nMhNn7DSu.NIAcMm4UsIpVVyJ5A4CTj90HmRkx4N2',	101,	'ADMIN LAST NAME',	'ADMIN FIRST NAME',	'MI',	'default.jpg'),
(100002,	'treasurer@cbsua.edu.ph',	'$2y$10$cRnaAYqfwT2xur7J20ig1up2e87iAQ8QK7oR2n.GNvDBW2M48vxQi',	102,	'TREASURER LAST NAME',	'TREASURER FIRST NAME',	'MI',	'default.jpg'),
(100003,	'student@cbsua.edu.ph',	'$2y$10$eeWzE7/NH6eSsIeTax2iJucBR52Ct8pUxGzRdxP1NC7.zw/W3Bbj2',	103,	'STUDENT LAST NAME',	'STUDENT FIRST NAME',	'MI',	'default.jpg');

INSERT INTO `departments` (`department_id`, `department_code`, `department_name`) VALUES
(1001, 'CIT',	'College of Information Technology'),
(1002, 'COT',	'College of Industrial Technology'),
(1003, 'COC',	'College of Criminology'),
(1004, 'COE',	'College of Education'),
(1005, 'ESAF',	'Environmental Science and Agroforestry Program');

INSERT INTO `programs` (`program_id`, `program_code`, `program_name`, `department_id`) VALUES
(1001,	'BSINFOTECH',	'BS Information Technology',	1001),
(1002,	'BSINDUST',	'BS Industrial Technlogy',	1002),
(1003,	'BSCRIM',	'BS Criminology',	1003),
(1004,	'BSED',	'BS Secondary Education',	1004),
(1005,	'BEED',	'BS Elementary Education',	1004),
(1006,	'BSAGRO',	'BS Agroforestry',	1005),
(1007,	'BSES',	'BS Environmental Science',	1005);

INSERT INTO `organizations` (`organization_id`, `organization_code`, `organization_name`, `organization_logo`, `organization_color`, `department_id`) VALUES
(1001,	'CITSC',	'College of Information Technology Student Council',	'default.jpg',	'violet',	1001),
(1002,	'COTSC',	'College of Industrial Technology Student Council',	'default.jpg',	'yellow',	1002),
(1003,	'CCSC',	'College of Criminology Student Council',	'default.jpg',	'red',	1003),
(1004,	'CESC',	'College of Education Student Council',	'default.jpg',	'blue',	1004),
(1005,	'ESAF',	'Student Council of Environmental Science and Agroforestry Program',	'default.jpg',	'brown',	1005),
(1006,	'SSC',	'Supreme Student Council',	'default.jpg',	'green',	NULL);

INSERT INTO `students` (`student_id`, `student_number_id`, `user_id`, `student_section`, `student_current_program`) VALUES
(1001,	'23-5374',	100002,	'3B',	1001),
(1002,	'23-2658',	100003,	'3C',	1001);
