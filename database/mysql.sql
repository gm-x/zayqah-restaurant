-- Optional fresh-database phpMyAdmin import. bin/setup.php normally initializes tables automatically.
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 full_name VARCHAR(120) NOT NULL, dob VARCHAR(10) NOT NULL, phone VARCHAR(30) NOT NULL,
 email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(20) NOT NULL DEFAULT 'customer', verified_at DATETIME NULL,
 delivery_address TEXT NULL, session_version INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tokens (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER NOT NULL, purpose VARCHAR(20) NOT NULL,
 token_hash VARCHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS categories (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(80) NOT NULL UNIQUE, icon VARCHAR(16) NOT NULL DEFAULT '🍽️', active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS files (
 id VARCHAR(48) PRIMARY KEY, owner_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL,
 path VARCHAR(100) NOT NULL, mime VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS seller_applications (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER NOT NULL UNIQUE,
 business_name VARCHAR(120) NOT NULL, address TEXT NOT NULL, business_phone VARCHAR(30) NOT NULL,
 logo_id VARCHAR(48) NOT NULL, cnic_encrypted TEXT NOT NULL,
 cnic_front VARCHAR(48) NOT NULL, cnic_back VARCHAR(48) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending', review_note TEXT NULL, reviewed_by INTEGER NULL,
 reviewed_at DATETIME NULL, submitted_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS menu_items (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, seller_id INTEGER NOT NULL, category_id INTEGER NOT NULL,
 photo_id VARCHAR(48) NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NOT NULL,
 ingredients TEXT NOT NULL, price_paisa INTEGER NOT NULL, expires_at DATETIME NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'published', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, recipient VARCHAR(254) NOT NULL, subject VARCHAR(200) NOT NULL,
 body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS attempts (id INTEGER PRIMARY KEY AUTO_INCREMENT, rate_key VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, admin_id INTEGER NOT NULL, action VARCHAR(200) NOT NULL,
 target_id INTEGER NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX menu_expiry ON menu_items(status, expires_at);
CREATE INDEX attempt_lookup ON attempts(rate_key,created_at);
ALTER TABLE tokens ADD CONSTRAINT fk_tokens_user_id FOREIGN KEY (user_id) REFERENCES users(id);
ALTER TABLE files ADD CONSTRAINT fk_files_owner_id FOREIGN KEY (owner_id) REFERENCES users(id);
ALTER TABLE seller_applications ADD CONSTRAINT fk_seller_applications_user_id FOREIGN KEY (user_id) REFERENCES users(id);
ALTER TABLE seller_applications ADD CONSTRAINT fk_seller_applications_logo_id FOREIGN KEY (logo_id) REFERENCES files(id);
ALTER TABLE seller_applications ADD CONSTRAINT fk_seller_applications_cnic_front FOREIGN KEY (cnic_front) REFERENCES files(id);
ALTER TABLE seller_applications ADD CONSTRAINT fk_seller_applications_cnic_back FOREIGN KEY (cnic_back) REFERENCES files(id);
ALTER TABLE seller_applications ADD CONSTRAINT fk_seller_applications_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id);
ALTER TABLE menu_items ADD CONSTRAINT fk_menu_items_seller_id FOREIGN KEY (seller_id) REFERENCES users(id);
ALTER TABLE menu_items ADD CONSTRAINT fk_menu_items_category_id FOREIGN KEY (category_id) REFERENCES categories(id);
ALTER TABLE menu_items ADD CONSTRAINT fk_menu_items_photo_id FOREIGN KEY (photo_id) REFERENCES files(id);
ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_logs_admin_id FOREIGN KEY (admin_id) REFERENCES users(id);

-- Update 02: account email changes and community restaurant reviews.
CREATE TABLE IF NOT EXISTS seller_reviews (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 seller_id INTEGER NOT NULL,
 user_id INTEGER NOT NULL,
 rating INTEGER NOT NULL, review_text TEXT NOT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE (seller_id, user_id),
 CONSTRAINT fk_seller_reviews_seller FOREIGN KEY (seller_id) REFERENCES users(id),
 CONSTRAINT fk_seller_reviews_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS email_changes (
 user_id INTEGER PRIMARY KEY,
 CONSTRAINT fk_email_changes_user FOREIGN KEY (user_id) REFERENCES users(id),
 new_email VARCHAR(254) NOT NULL UNIQUE,
 old_email VARCHAR(254) NOT NULL,
 session_version INTEGER NOT NULL,
 token_hash VARCHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
