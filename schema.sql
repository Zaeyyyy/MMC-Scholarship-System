-- Scholarship Management System — database schema
-- Import this once in phpMyAdmin (or `mysql -u root -p < schema.sql`)
-- before running the PHP app.

CREATE DATABASE IF NOT EXISTS scholarship_db;
USE scholarship_db;

-- Accounts that can log in and manage the system
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  school_id VARCHAR(100) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Academic years a scholarship can be offered under
CREATE TABLE IF NOT EXISTS academic_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(50) NOT NULL,           -- e.g. "2026-2027"
  term VARCHAR(50) NOT NULL,            -- e.g. "1st Semester"
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('Upcoming','Open','Closed') NOT NULL DEFAULT 'Upcoming',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Scholarship programs, each tied to one academic year
CREATE TABLE IF NOT EXISTS scholarship_programs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  slots INT NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Required documents, each tied to one scholarship program
CREATE TABLE IF NOT EXISTS requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scholarship_program_id INT NOT NULL,
  document_name VARCHAR(150) NOT NULL,
  notes VARCHAR(255) NULL,
  mandatory TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Applications table: tracks which student applied to which program
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  scholarship_program_id INT NOT NULL,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('For Review','Pending','Accepted','Rejected') NOT NULL DEFAULT 'For Review',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
