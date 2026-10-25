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
