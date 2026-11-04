-- Single-restaurant schema (MySQL 8). bin/setup.php runs this file; every statement is idempotent.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'customer',          -- customer | owner
  verified_at DATETIME NULL,
  delivery_address TEXT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  session_version INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  purpose VARCHAR(20) NOT NULL,                           -- verify | reset
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_changes (
  user_id INT PRIMARY KEY,
  new_email VARCHAR(254) NOT NULL UNIQUE,
  old_email VARCHAR(254) NOT NULL,
  session_version INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_email_changes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
  id CHAR(48) PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,                              -- menu | slide | logo
  path VARCHAR(100) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Restaurant profile, opening hours and ordering options, edited by the owner.
CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(60) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  photo_id CHAR(48) NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  ingredients TEXT NOT NULL,                              -- ingredients and allergens
  price_minor INT NOT NULL,                               -- price in pence/cents
  status VARCHAR(20) NOT NULL DEFAULT 'available',        -- available | sold_out | hidden
  featured TINYINT NOT NULL DEFAULT 0,
  vegetarian TINYINT NOT NULL DEFAULT 0,
  spicy TINYINT NOT NULL DEFAULT 0,
  available_days INT NOT NULL DEFAULT 127,                -- bitmask, Monday = 1 ... Sunday = 64
  available_from CHAR(5) NULL,                            -- HH:MM, NULL = whenever the restaurant is open
  available_until CHAR(5) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX menu_items_listing (status, category_id, sort_order),
  CONSTRAINT fk_menu_items_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_menu_items_photo FOREIGN KEY (photo_id) REFERENCES files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS slides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  photo_id CHAR(48) NOT NULL,
  heading VARCHAR(160) NOT NULL DEFAULT '',
  subheading VARCHAR(240) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_slides_photo FOREIGN KEY (photo_id) REFERENCES files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(20) NOT NULL UNIQUE,
  request_key CHAR(64) NOT NULL UNIQUE,                   -- makes a double-submitted checkout create one order
  customer_id INT NOT NULL,
  fulfillment VARCHAR(20) NOT NULL,                       -- pickup | delivery
  status VARCHAR(20) NOT NULL DEFAULT 'placed',           -- awaiting_payment | placed | confirmed | preparing | ready | completed | cancelled
  payment_method VARCHAR(20) NOT NULL DEFAULT 'on_delivery', -- online (Stripe: Apple Pay, Google Pay, card) | on_delivery
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',   -- unpaid | pending | paid | refunded | failed
  payment_intent_id VARCHAR(64) NULL UNIQUE,
  paid_at DATETIME NULL,
  refunded_at DATETIME NULL,
  subtotal_minor INT NOT NULL,
  delivery_fee_minor INT NOT NULL DEFAULT 0,
  total_minor INT NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  delivery_address TEXT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  rider_latitude DECIMAL(10,7) NULL,
  rider_longitude DECIMAL(10,7) NULL,
  rider_updated_at DATETIME NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX orders_status (status, created_at),
  INDEX orders_customer (customer_id, created_at),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_item_id INT NULL,
  name VARCHAR(120) NOT NULL,
  quantity INT NOT NULL,
  unit_price_minor INT NOT NULL,
  total_minor INT NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY order_events_once (order_id, status),
  CONSTRAINT fk_order_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_links (
  order_id INT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  CONSTRAINT fk_delivery_links_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dish reviews from customers who ordered the dish. There are no restaurant-level reviews or favourites.
CREATE TABLE IF NOT EXISTS dish_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  menu_item_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  review_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY dish_reviews_once (menu_item_id, user_id),
  CONSTRAINT fk_dish_reviews_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_dish_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(400) NOT NULL,
  path VARCHAR(200) NOT NULL,
  event_key VARCHAR(120) NOT NULL UNIQUE,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX notifications_user (user_id, read_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  subject VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',              -- new | resolved
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_contact_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(254) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rate_key CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX attempt_lookup (rate_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
