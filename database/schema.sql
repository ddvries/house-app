CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  role ENUM('admin','gebruiker') NOT NULL DEFAULT 'gebruiker',
  preferred_language ENUM('en','nl','de','fr','es') NOT NULL DEFAULT 'en',
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS role ENUM('admin','gebruiker') NOT NULL DEFAULT 'gebruiker' AFTER email;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS preferred_language ENUM('en','nl','de','fr','es') NOT NULL DEFAULT 'en' AFTER role;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER password_hash;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) NULL AFTER email_verified_at;

-- Mark existing users (created before email verification) as already verified
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL AND email_verification_token IS NULL;

CREATE TABLE IF NOT EXISTS houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  city VARCHAR(120) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_houses_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_houses_owner (owner_user_id),
  INDEX idx_houses_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  floor ENUM('Kelder','Begane Grond','Eerste Verdieping','Tweede Verdieping','Zolder') NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_rooms_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
  INDEX idx_rooms_house (house_id),
  INDEX idx_rooms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  type ENUM('Verf','Tegel','Hout','Behang','Overig') NOT NULL,
  name VARCHAR(220) NOT NULL,
  color_hex CHAR(7) NOT NULL DEFAULT '',
  description TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_materials_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_materials_room (room_id),
  INDEX idx_materials_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS material_store_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id INT UNSIGNED NOT NULL,
  url VARCHAR(2048) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_links_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
  INDEX idx_links_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL UNIQUE,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL,
  CONSTRAINT fk_attachments_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
  INDEX idx_attachments_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
