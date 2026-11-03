CREATE DATABASE IF NOT EXISTS skprofund CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skprofund;
CREATE TABLE roles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 code VARCHAR(30) NOT NULL,
 system_role BOOLEAN NOT NULL DEFAULT FALSE,
 description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;
INSERT INTO roles(name,code,system_role) VALUES ('Admin','admin',1),('SK Chairperson','chairperson',1),('SK Treasurer','treasurer',1),('SK Official','official',1),('Youth / Resident','resident',1);
CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role_id INT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 phone VARCHAR(40) NOT NULL DEFAULT '',
 barangay VARCHAR(120) NOT NULL DEFAULT 'Pag-Asa, Mabitac, Laguna',
 active BOOLEAN NOT NULL DEFAULT TRUE,
 photo_path VARCHAR(100) NULL,
 auth_version INT NOT NULL DEFAULT 1,
 must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB;
CREATE TABLE categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 description TEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE fund_sources (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 description TEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE fund_entries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_id INT UNSIGNED NOT NULL,
 amount_cents BIGINT NOT NULL CHECK(amount_cents > 0),
 entry_date DATE NOT NULL,
 reference VARCHAR(150) NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(source_id) REFERENCES fund_sources(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE projects (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_id INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 source_id INT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 description TEXT NOT NULL,
 location VARCHAR(190) NOT NULL,
 committee VARCHAR(190) NOT NULL,
 beneficiaries VARCHAR(255) NOT NULL,
 beneficiary_count INT UNSIGNED NOT NULL DEFAULT 0,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 planned_cents BIGINT NOT NULL CHECK(planned_cents >= 0),
 budget_cents BIGINT NOT NULL DEFAULT 0 CHECK(budget_cents >= 0),
 released_cents BIGINT NOT NULL DEFAULT 0 CHECK(released_cents >= 0),
 status ENUM('Pending','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
 progress TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK(progress <= 100),
 review_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
 remarks TEXT NOT NULL,
 photo_path VARCHAR(100) NULL,
 published BOOLEAN NOT NULL DEFAULT FALSE,
 publication_requested BOOLEAN NOT NULL DEFAULT FALSE,
 reviewed_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK(end_date >= start_date),
 CHECK(released_cents <= budget_cents),
 FOREIGN KEY(owner_id) REFERENCES users(id),
 FOREIGN KEY(category_id) REFERENCES categories(id),
 FOREIGN KEY(source_id) REFERENCES fund_sources(id),
 FOREIGN KEY(reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE progress_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL,
 progress TINYINT UNSIGNED NOT NULL CHECK(progress <= 100),
 details TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(project_id) REFERENCES projects(id),
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE fund_requests (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 from_project_id INT UNSIGNED NULL,
 requested_by INT UNSIGNED NOT NULL,
 type ENUM('Allocation','Realignment','Disbursement') NOT NULL,
 amount_cents BIGINT NOT NULL CHECK(amount_cents > 0),
 reason TEXT NOT NULL,
 document_path VARCHAR(100) NULL,
 status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
 decision_notes TEXT NULL,
 decided_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 decided_at TIMESTAMP NULL,
 FOREIGN KEY(project_id) REFERENCES projects(id),
 FOREIGN KEY(from_project_id) REFERENCES projects(id),
 FOREIGN KEY(requested_by) REFERENCES users(id),
 FOREIGN KEY(decided_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE expenses (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 recorded_by INT UNSIGNED NOT NULL,
 amount_cents BIGINT NOT NULL CHECK(amount_cents > 0),
 expense_date DATE NOT NULL,
 description TEXT NOT NULL,
 payee VARCHAR(190) NOT NULL,
 receipt_date DATE NOT NULL,
 receipt_reference VARCHAR(120) NOT NULL,
 receipt_path VARCHAR(100) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(project_id) REFERENCES projects(id),
 FOREIGN KEY(recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(40) NOT NULL,
 module VARCHAR(50) NOT NULL,
 details TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX audit_time(created_at)
) ENGINE=InnoDB;
CREATE TABLE login_attempts (
 key_hash CHAR(64) PRIMARY KEY,
 attempts INT NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- Existing installations: select your skprofund database in phpMyAdmin, then import this file once.
CREATE TABLE public_feedback (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NULL,
 user_id INT UNSIGNED NULL,
 type ENUM('Suggestion','Comment') NOT NULL,
 subject VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 display_name VARCHAR(100) NOT NULL DEFAULT 'Anonymous',
 status ENUM('New','Reviewed','Archived') NOT NULL DEFAULT 'New',
 reviewed_by INT UNSIGNED NULL,
 review_notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL,
 FOREIGN KEY(project_id) REFERENCES projects(id),
 FOREIGN KEY(user_id) REFERENCES users(id),
 FOREIGN KEY(reviewed_by) REFERENCES users(id),
 INDEX feedback_status(status,created_at)
) ENGINE=InnoDB;
CREATE TABLE feedback_rate_limits (
 client_hash CHAR(64) PRIMARY KEY,
 attempts INT NOT NULL DEFAULT 0,
 window_started DATETIME NOT NULL
) ENGINE=InnoDB;
