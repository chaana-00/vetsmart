-- VetSmart database schema
-- Import with:  mysql -u root -p < database/vetsmart.sql   (or use phpMyAdmin > Import)
CREATE DATABASE IF NOT EXISTS vetsmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vetsmart;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  designation VARCHAR(120) NULL,
  company VARCHAR(120) NULL,
  phone VARCHAR(15) NULL,
  username VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notif_seen_id INT UNSIGNED NOT NULL DEFAULT 0,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS farms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  farm_name VARCHAR(150) NOT NULL,
  farm_code VARCHAR(30) NOT NULL UNIQUE,
  contact1 VARCHAR(15) NULL,
  contact2 VARCHAR(15) NULL,
  contact3 VARCHAR(15) NULL,
  owner_name VARCHAR(150) NULL,
  owner_contact1 VARCHAR(15) NULL,
  owner_contact2 VARCHAR(15) NULL,
  farm_type ENUM('Broiler','Layer','Broiler & Layer','Other') NOT NULL DEFAULT 'Broiler',
  capacity INT UNSIGNED NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (farm_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS farm_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  location_name VARCHAR(200) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  INDEX (location_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  batch_no VARCHAR(50) NOT NULL UNIQUE,
  visit_datetime DATETIME NULL,
  cage_no VARCHAR(50) NULL,
  breed VARCHAR(100) NULL,
  hatchery VARCHAR(100) NULL,
  total_input INT UNSIGNED NOT NULL DEFAULT 0,
  vaccination TEXT NULL,
  treatment_history TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES farm_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS broiler_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(20) NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  registration_number VARCHAR(60) NULL,
  visit_date DATETIME NULL,
  flock_size INT NULL,
  age VARCHAR(30) NULL,
  average_weight VARCHAR(30) NULL,
  weight_gain VARCHAR(30) NULL,
  feed_intake VARCHAR(50) NULL,
  mortality VARCHAR(30) NULL,
  mortality_pct VARCHAR(30) NULL,
  complaint TEXT NULL,
  clinical_signs TEXT NULL,
  dd VARCHAR(255) NULL,
  test_request TEXT NULL,
  test_report TEXT NULL,
  recommendation TEXT NULL,
  treatment TEXT NULL,
  follow_ups TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES farm_locations(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS layer_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_no VARCHAR(20) NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  registration_number VARCHAR(60) NULL,
  visit_date DATETIME NULL,
  flock_size INT NULL,
  age VARCHAR(30) NULL,
  average_age VARCHAR(30) NULL,
  egg_production VARCHAR(30) NULL,
  egg_production_pct VARCHAR(30) NULL,
  feed_intake VARCHAR(50) NULL,
  mortality VARCHAR(30) NULL,
  mortality_pct VARCHAR(30) NULL,
  complaint TEXT NULL,
  clinical_signs TEXT NULL,
  post_mortem_changes TEXT NULL,
  dd VARCHAR(255) NULL,
  test_request TEXT NULL,
  test_report TEXT NULL,
  recommendation TEXT NULL,
  treatment TEXT NULL,
  follow_ups TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES farm_locations(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS record_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_type ENUM('broiler','layer') NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (record_type, record_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  presc_no VARCHAR(20) NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  num_birds INT UNSIGNED NULL,
  remarks TEXT NULL,
  presc_datetime DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescription_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT UNSIGNED NOT NULL,
  drug VARCHAR(200) NOT NULL,
  indication VARCHAR(200) NULL,
  duration VARCHAR(100) NULL,
  total_volume VARCHAR(100) NULL,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity log + realtime notifications (one table: every submission is also history)
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED NOT NULL,
  actor_name VARCHAR(120) NOT NULL,
  type VARCHAR(30) NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (actor_id), INDEX (created_at)
) ENGINE=InnoDB;

-- Default administrator.  Username: admin@vetsmart.lk   Password: password
-- CHANGE THIS PASSWORD after first login (Admin > Manage Users > Edit).
INSERT INTO users (full_name, designation, company, phone, username, password_hash, role)
SELECT 'System Administrator','Administrator','Farm Chemie','0112345678','admin@vetsmart.lk',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='admin@vetsmart.lk');
