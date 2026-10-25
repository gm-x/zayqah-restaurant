CREATE TABLE IF NOT EXISTS seller_reviews (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 seller_id INTEGER NOT NULL REFERENCES users(id),
 user_id INTEGER NOT NULL REFERENCES users(id),
 rating INTEGER NOT NULL, review_text TEXT NOT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 UNIQUE (seller_id, user_id)
);
CREATE TABLE IF NOT EXISTS email_changes (
 user_id INTEGER PRIMARY KEY REFERENCES users(id),
 new_email VARCHAR(254) NOT NULL UNIQUE,
 old_email VARCHAR(254) NOT NULL,
 session_version INTEGER NOT NULL,
 token_hash VARCHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL
);
