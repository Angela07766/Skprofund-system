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
