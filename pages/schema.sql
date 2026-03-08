-- MDRRMO San Ildefonso Web App Schema
-- Run this in your MySQL database (e.g. via phpMyAdmin)

--mdrrmo_db

SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE barangays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  municipality VARCHAR(100) NOT NULL DEFAULT 'San Ildefonso',
  province VARCHAR(100) NOT NULL DEFAULT 'Bulacan',
  center_lat DECIMAL(10,7) DEFAULT NULL,
  center_lng DECIMAL(10,7) DEFAULT NULL,
  boundary_polygon JSON DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_barangay_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('citizen','admin','coordinator') NOT NULL DEFAULT 'citizen',
  barangay_id INT UNSIGNED NOT NULL,
  house_number VARCHAR(50) NOT NULL,
  is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  otp_code_hash VARCHAR(255) DEFAULT NULL,
  otp_expires_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evacuation_centers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  barangay_id INT UNSIGNED NOT NULL,
  address VARCHAR(255) NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  max_capacity_people INT UNSIGNED NOT NULL,
  max_capacity_families INT UNSIGNED DEFAULT 0,
  status ENUM('available','near_capacity','full','temp_shelter','closed') NOT NULL DEFAULT 'available',
  coordinator_user_id INT UNSIGNED DEFAULT NULL,
  notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_centers_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
  CONSTRAINT fk_centers_coordinator FOREIGN KEY (coordinator_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evac_registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  center_id INT UNSIGNED NOT NULL,
  family_head_name VARCHAR(150) NOT NULL,
  barangay_id INT UNSIGNED NOT NULL,
  adults INT UNSIGNED NOT NULL DEFAULT 0,
  children INT UNSIGNED NOT NULL DEFAULT 0,
  seniors INT UNSIGNED NOT NULL DEFAULT 0,
  pwds INT UNSIGNED NOT NULL DEFAULT 0,
  total_members INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_evac_center FOREIGN KEY (center_id) REFERENCES evacuation_centers(id),
  CONSTRAINT fk_evac_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
  CONSTRAINT fk_evac_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disasters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('typhoon','flood','earthquake','heat','landslide','other') NOT NULL,
  level TINYINT UNSIGNED NOT NULL, -- 1-5
  title VARCHAR(200) NOT NULL,
  status ENUM('planned','ongoing','resolved') NOT NULL DEFAULT 'planned',
  description TEXT,
  started_at DATETIME DEFAULT NULL,
  ended_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  type ENUM('general','disaster') NOT NULL DEFAULT 'general',
  disaster_id INT UNSIGNED DEFAULT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_disaster FOREIGN KEY (disaster_id) REFERENCES disasters(id),
  CONSTRAINT fk_ann_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weather_snapshots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  temp_c DECIMAL(5,2) NOT NULL,
  humidity DECIMAL(5,2) NOT NULL,
  heat_index DECIMAL(5,2) DEFAULT NULL,
  condition_text VARCHAR(255) NOT NULL,
  level ENUM('low','medium','high','extreme') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ready_bag_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  disaster_type ENUM('typhoon','flood','earthquake','heat','landslide','general') NOT NULL,
  level_min TINYINT UNSIGNED NOT NULL DEFAULT 1,
  level_max TINYINT UNSIGNED NOT NULL DEFAULT 5,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed basic barangays for San Ildefonso (add/adjust as needed)
INSERT INTO barangays (name) VALUES
('Akle'), ('Alagao'), ('Bagong Barrio'), ('Bubulong Malaki'), ('Calasag'), ('Garlang'),
('Makapilapil'), ('Malipampang'), ('Masapinit'), ('Pala-Pala'), ('Pangclara'), ('Poblacion'),
('Pulong Bahay'), ('Pulong Tamo'), ('Sapang Dayap'), ('Sapang Putol'), ('Sapang Putik'),
('Sto. Cristo'), ('Telepatio');

