-- Meridian Market
-- Import this file in phpMyAdmin or the MySQL client.
-- It creates the database, tables, and fictional sample data.
-- Re-importing replaces the sample marketplace data.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_status_events;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS order_counters;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS seller_profiles;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role ENUM('customer', 'seller', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    shop_name VARCHAR(80) NOT NULL,
    shop_slug VARCHAR(120) NOT NULL,
    bio VARCHAR(500) NOT NULL,
    location VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_seller_user (user_id),
    UNIQUE KEY uq_seller_slug (shop_slug),
    CONSTRAINT fk_seller_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    image VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    discount_type ENUM('percent', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    maximum_discount DECIMAL(10,2) NULL,
    usage_limit INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    sku VARCHAR(40) NOT NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id, status),
    KEY idx_products_seller (seller_id, status),
    KEY idx_products_featured (featured, status),
    KEY idx_products_price (price),
    KEY idx_products_created (created_at),
    CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users (id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_images_product (product_id, display_order),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_carts_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_counters (
    day_key CHAR(8) NOT NULL PRIMARY KEY,
    seq_value INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(24) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    coupon_id INT UNSIGNED NULL,
    payment_method ENUM('paypal', 'cod', 'bank') NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'unpaid',
    transaction_id VARCHAR(80) NULL,
    order_status ENUM('pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    shipping_name VARCHAR(80) NOT NULL,
    shipping_email VARCHAR(120) NOT NULL,
    shipping_phone VARCHAR(20) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    cancellation_reason VARCHAR(255) NULL,
    cancelled_at DATETIME NULL,
    stock_restored TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_number (order_number),
    UNIQUE KEY uq_orders_transaction (transaction_id),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (order_status, payment_status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(160) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_items_order (order_id),
    KEY idx_items_seller (seller_id, order_id),
    KEY idx_items_product (product_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id),
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_items_seller FOREIGN KEY (seller_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    payment_method ENUM('paypal', 'cod', 'bank') NOT NULL,
    transaction_id VARCHAR(80) NULL,
    provider_reference VARCHAR(80) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('unpaid', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'unpaid',
    response_data TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_order (order_id),
    UNIQUE KEY uq_payments_transaction (transaction_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    actor_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_events_order (order_id, id),
    CONSTRAINT fk_events_order FOREIGN KEY (order_id) REFERENCES orders (id),
    CONSTRAINT fk_events_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_product (user_id, product_id),
    KEY idx_reviews_product (product_id, status),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    email VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_resets_hash (token_hash),
    KEY idx_resets_user (user_id),
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(500) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Development passwords are documented in README.md.
-- Admin must change the password on first sign-in.
INSERT INTO users (id, name, email, password, phone, address, role, status, must_change_password, created_at, updated_at) VALUES
(1, 'Avery Quinn', 'avery.quinn@meridianmarket.test', '$2y$12$gY3n8GUl5eqfwAEYZyaTk.OUQHXtcrFxEMUdWUEa4pXhOypNmwDoC', '+852 5550 0101', '18 Harbour Admin Dock, Central, Hong Kong', 'admin', 'active', 1, '2026-01-08 09:00:00', '2026-01-08 09:00:00'),
(2, 'Lila Brook', 'lila.brook@cedarclay.example', '$2y$12$nt5cA.id4iZts3WyTtB65.U6tXx0LH.oLQwxsMcGjyL0aovXt.6WG', '+1 555 0142', '14 Kiln Street, Portland, Oregon', 'seller', 'active', 0, '2026-02-02 10:00:00', '2026-02-02 10:00:00'),
(3, 'Owen Park', 'owen.park@northline.example', '$2y$12$nt5cA.id4iZts3WyTtB65.U6tXx0LH.oLQwxsMcGjyL0aovXt.6WG', '+1 555 0177', '88 Trailhead Road, Boulder, Colorado', 'seller', 'active', 0, '2026-02-03 10:00:00', '2026-02-03 10:00:00'),
(4, 'Nora Kim', 'nora.kim@sableatelier.example', '$2y$12$nt5cA.id4iZts3WyTtB65.U6tXx0LH.oLQwxsMcGjyL0aovXt.6WG', '+1 555 0190', '5 Atelier Lane, Savannah, Georgia', 'seller', 'active', 0, '2026-02-04 10:00:00', '2026-02-04 10:00:00'),
(5, 'Elena Voss', 'elena.voss@example.com', '$2y$12$goh/EmmtmIylY5zEPoT3O.lUEQhVgGaqn5TCnKESEcxC/1BbVbcgC', '+1 555 2011', '402 Cedar Avenue, Apt 3, Burlington, Vermont', 'customer', 'active', 0, '2026-03-12 11:20:00', '2026-03-12 11:20:00'),
(6, 'Marcus Hale', 'marcus.hale@example.com', '$2y$12$goh/EmmtmIylY5zEPoT3O.lUEQhVgGaqn5TCnKESEcxC/1BbVbcgC', '+1 555 2022', '77 North Wharf, Apt 12, Seattle, Washington', 'customer', 'active', 0, '2026-03-18 14:05:00', '2026-03-18 14:05:00'),
(7, 'Priya Nandakumar', 'priya.nandakumar@example.com', '$2y$12$goh/EmmtmIylY5zEPoT3O.lUEQhVgGaqn5TCnKESEcxC/1BbVbcgC', '+1 555 2033', '19 Orchard Close, Austin, Texas', 'customer', 'active', 0, '2026-04-02 09:40:00', '2026-04-02 09:40:00'),
(8, 'Jonah Ellison', 'jonah.ellison@example.com', '$2y$12$goh/EmmtmIylY5zEPoT3O.lUEQhVgGaqn5TCnKESEcxC/1BbVbcgC', '+1 555 2044', '250 Pier Street, Portland, Maine', 'customer', 'active', 0, '2026-04-16 16:15:00', '2026-04-16 16:15:00'),
(9, 'Amira Soltani', 'amira.soltani@example.com', '$2y$12$goh/EmmtmIylY5zEPoT3O.lUEQhVgGaqn5TCnKESEcxC/1BbVbcgC', '+1 555 2055', '8 Lantern Court, Santa Fe, New Mexico', 'customer', 'active', 0, '2026-05-01 13:25:00', '2026-05-01 13:25:00');

INSERT INTO seller_profiles (user_id, shop_name, shop_slug, bio, location, created_at, updated_at) VALUES
(2, 'Cedar & Clay', 'cedar-clay', 'Stoneware, linen, and quiet objects for the table and the home.', 'Portland, Oregon', '2026-02-02 10:10:00', '2026-02-02 10:10:00'),
(3, 'Northline Goods', 'northline-goods', 'Trail-ready gear and cold-weather layers made for long weekends.', 'Boulder, Colorado', '2026-02-03 10:10:00', '2026-02-03 10:10:00'),
(4, 'Sable Atelier', 'sable-atelier', 'Paper goods, small rituals, and a pantry shelf of slow staples.', 'Savannah, Georgia', '2026-02-04 10:10:00', '2026-02-04 10:10:00');

INSERT INTO categories (id, name, slug, description, image, status, created_at, updated_at) VALUES
(1, 'Home & Living', 'home-living', 'Textiles and objects that settle a room.', 'assets/images/categories/home.jpg', 'active', '2026-02-10 09:00:00', '2026-02-10 09:00:00'),
(2, 'Kitchen', 'kitchen', 'Boards, bowls, and brewing tools.', 'assets/images/categories/kitchen.jpg', 'active', '2026-02-10 09:05:00', '2026-02-10 09:05:00'),
(3, 'Apparel', 'apparel', 'Layers for weather that changes its mind.', 'assets/images/categories/apparel.jpg', 'active', '2026-02-10 09:10:00', '2026-02-10 09:10:00'),
(4, 'Outdoor', 'outdoor', 'Carry, camp, and look-out kit.', 'assets/images/categories/outdoor.jpg', 'active', '2026-02-10 09:15:00', '2026-02-10 09:15:00'),
(5, 'Stationery', 'stationery', 'Journals, ink, and desk tools.', 'assets/images/categories/stationery.jpg', 'active', '2026-02-10 09:20:00', '2026-02-10 09:20:00'),
(6, 'Wellness', 'wellness', 'Small rituals for the end of the day.', 'assets/images/categories/wellness.jpg', 'active', '2026-02-10 09:25:00', '2026-02-10 09:25:00'),
(7, 'Lighting', 'lighting', 'Lamps with a warm, low glow.', 'assets/images/categories/lighting.jpg', 'active', '2026-02-10 09:30:00', '2026-02-10 09:30:00'),
(8, 'Audio', 'audio', 'Sound for porches and picnic tables.', 'assets/images/categories/audio.jpg', 'active', '2026-02-10 09:35:00', '2026-02-10 09:35:00'),
(9, 'Kids & Play', 'kids-play', 'Toys with a calm palette.', 'assets/images/categories/kids.jpg', 'active', '2026-02-10 09:40:00', '2026-02-10 09:40:00'),
(10, 'Pantry', 'pantry', 'Cocoa, honey, and evening tea.', 'assets/images/categories/pantry.jpg', 'active', '2026-02-10 09:45:00', '2026-02-10 09:45:00');

INSERT INTO coupons (id, code, discount_type, discount_value, minimum_amount, maximum_discount, usage_limit, used_count, start_date, expiry_date, status, created_at, updated_at) VALUES
(1, 'WELCOME10', 'percent', 10.00, 40.00, 25.00, 200, 1, '2026-01-01', '2026-12-31', 'active', '2026-01-10 09:00:00', '2026-09-25 12:00:00'),
(2, 'MAKER25', 'fixed', 25.00, 120.00, NULL, 80, 1, '2026-01-01', '2026-12-31', 'active', '2026-01-10 09:10:00', '2026-09-08 15:00:00'),
(3, 'STUDIO15', 'percent', 15.00, 80.00, 40.00, 100, 0, '2026-01-01', '2026-11-30', 'active', '2026-03-01 09:00:00', '2026-03-01 09:00:00'),
(4, 'ARCHIVE20', 'percent', 20.00, 50.00, 30.00, 40, 0, '2026-01-01', '2026-02-01', 'inactive', '2026-01-02 09:00:00', '2026-02-02 09:00:00');

INSERT INTO products (id, seller_id, category_id, name, slug, description, price, compare_price, stock, sku, status, featured, created_at, updated_at) VALUES
(1, 2, 2, 'Stoneware Pour-Over Carafe', 'stoneware-pour-over-carafe', 'A speckled stoneware carafe sized for a slow morning brew, with a flared lip that pours cleanly.', 48.00, 58.00, 18, 'CC-CARAFE-01', 'active', 1, '2026-06-02 10:00:00', '2026-06-02 10:00:00'),
(2, 2, 1, 'Oat Linen Throw', 'oat-linen-throw', 'A washed linen throw in oat, heavy enough for a sofa and light enough to fold over a chair.', 76.00, NULL, 12, 'CC-THROW-04', 'active', 1, '2026-06-08 10:00:00', '2026-06-08 10:00:00'),
(3, 2, 2, 'Oak Serving Board', 'oak-serving-board', 'A long oak board with a juice groove and a handle cut for passing plates across a table.', 42.00, NULL, 20, 'CC-BOARD-02', 'active', 0, '2026-06-14 10:00:00', '2026-06-14 10:00:00'),
(4, 2, 7, 'Ceramic Table Lamp', 'ceramic-table-lamp', 'A matte ceramic lamp with a paper shade that keeps the light low and amber.', 89.00, NULL, 7, 'CC-LAMP-07', 'active', 1, '2026-06-20 10:00:00', '2026-06-20 10:00:00'),
(5, 2, 2, 'Speckled Ramen Bowls', 'speckled-ramen-bowls', 'A set of four deep bowls with a speckled glaze that hides the everyday wear of noodles and soup.', 64.00, NULL, 15, 'CC-BOWL-04', 'active', 0, '2026-06-26 10:00:00', '2026-06-26 10:00:00'),
(6, 2, 2, 'Espresso Cup Pair', 'espresso-cup-pair', 'Two hand-thrown cups with a short foot so they sit steady beside a kettle.', 36.00, NULL, 22, 'CC-CUPS-02', 'active', 0, '2026-07-02 10:00:00', '2026-07-02 10:00:00'),
(7, 2, 2, 'Wool Felt Trivets', 'wool-felt-trivets', 'A trio of dense wool trivets that protect wood without sliding when a pot lands.', 28.00, NULL, 30, 'CC-TRIVET-03', 'active', 0, '2026-07-08 10:00:00', '2026-07-08 10:00:00'),
(8, 2, 1, 'Matte Bud Vase', 'matte-bud-vase', 'A narrow vase for a single branch, finished in a soft matte glaze.', 32.00, NULL, 16, 'CC-VASE-01', 'active', 0, '2026-07-14 10:00:00', '2026-07-14 10:00:00'),
(9, 2, 1, 'Linen Napkin Set', 'linen-napkin-set', 'Four linen napkins with a narrow hem, meant to be washed often and left unironed.', 34.00, NULL, 25, 'CC-NAPKIN-04', 'active', 0, '2026-07-20 10:00:00', '2026-07-20 10:00:00'),
(10, 2, 6, 'Cedar Scent Candle', 'cedar-scent-candle', 'A soy candle poured in a reusable cup, scented with cedar and a little smoke.', 24.00, NULL, 40, 'CC-CANDLE-01', 'active', 0, '2026-07-26 10:00:00', '2026-07-26 10:00:00'),
(11, 3, 3, 'Merino Trail Beanie', 'merino-trail-beanie', 'A merino beanie that packs into a pocket and still feels warm on a windy ridge.', 32.00, NULL, 28, 'NL-BEANIE-01', 'active', 0, '2026-06-04 11:00:00', '2026-06-04 11:00:00'),
(12, 3, 4, 'Canvas Weekender', 'canvas-weekender', 'A waxed canvas duffel with a leather grab handle and a pocket sized for a book.', 128.00, 148.00, 9, 'NL-DUFFEL-01', 'active', 1, '2026-06-18 11:00:00', '2026-06-18 11:00:00'),
(13, 3, 4, 'Field Binoculars', 'field-binoculars', 'Compact binoculars with a clear field of view for ridgelines, harbors, and ball games.', 96.00, NULL, 11, 'NL-BINOC-08', 'active', 1, '2026-07-01 11:00:00', '2026-07-01 11:00:00'),
(14, 3, 3, 'Recycled Shell Jacket', 'recycled-shell-jacket', 'A light shell cut from recycled fabric, with a hood that stays put when the wind picks up.', 154.00, 180.00, 8, 'NL-JACKET-02', 'active', 0, '2026-07-12 11:00:00', '2026-07-12 11:00:00'),
(15, 3, 4, 'Aluminum Camp Mug', 'aluminum-camp-mug', 'A single-wall camp mug that clicks onto a pack strap and cools faster than it looks.', 22.00, NULL, 34, 'NL-MUG-01', 'active', 0, '2026-07-22 11:00:00', '2026-07-22 11:00:00'),
(16, 3, 8, 'Speaker Lantern', 'speaker-lantern', 'A small lantern that plays music and throws a warm pool of light on a table.', 79.00, 92.00, 13, 'NL-LANTERN-03', 'active', 1, '2026-08-02 11:00:00', '2026-08-02 11:00:00'),
(17, 3, 3, 'Wool Hiking Socks', 'wool-hiking-socks', 'A two-pack of cushioned wool socks with a snug heel that stays in place.', 26.00, NULL, 40, 'NL-SOCK-02', 'active', 0, '2026-08-12 11:00:00', '2026-08-12 11:00:00'),
(18, 3, 4, 'Waxed Cotton Tote', 'waxed-cotton-tote', 'A structured tote that shrugs off a light rain and stands open while you pack it.', 48.00, NULL, 18, 'NL-TOTE-01', 'active', 0, '2026-08-20 11:00:00', '2026-08-20 11:00:00'),
(19, 3, 4, 'Brass Whistle Keyring', 'brass-whistle-keyring', 'A solid brass whistle on a split ring, loud enough for a trailhead and small enough for a pocket.', 18.00, NULL, 0, 'NL-WHISTLE-01', 'active', 0, '2026-08-28 11:00:00', '2026-08-28 11:00:00'),
(20, 3, 3, 'Packable Rain Shell', 'packable-rain-shell', 'A packable shell that stuffs into its own chest pocket when the weather clears.', 88.00, NULL, 10, 'NL-RAIN-04', 'active', 0, '2026-09-06 11:00:00', '2026-09-06 11:00:00'),
(21, 4, 5, 'Cotton Ruled Journal', 'cotton-ruled-journal', 'A clothbound journal with creamy ruled paper and a ribbon that actually stays in the page.', 18.00, NULL, 50, 'SA-JOURNAL-01', 'active', 1, '2026-06-06 12:00:00', '2026-06-06 12:00:00'),
(22, 4, 5, 'Dusk Ink Set', 'dusk-ink-set', 'Four small ink bottles in dusk colors, mixed for fountain pens and brush notes.', 27.00, NULL, 24, 'SA-INK-04', 'active', 0, '2026-06-22 12:00:00', '2026-06-22 12:00:00'),
(23, 4, 5, 'Brass Desk Scissors', 'brass-desk-scissors', 'Short brass scissors with a precise tip for thread, twine, and paper.', 21.00, NULL, 19, 'SA-SCISSOR-01', 'active', 0, '2026-07-06 12:00:00', '2026-07-06 12:00:00'),
(24, 4, 6, 'Herbal Steam Kit', 'herbal-steam-kit', 'A linen pouch of herbs and a ceramic cup for a short evening steam.', 34.00, NULL, 17, 'SA-STEAM-02', 'active', 0, '2026-07-18 12:00:00', '2026-07-18 12:00:00'),
(25, 4, 6, 'Clay Face Cup', 'clay-face-cup', 'A small clay cup used for a rinse of mineral water at the sink.', 29.00, NULL, 21, 'SA-FACECUP-01', 'active', 0, '2026-08-01 12:00:00', '2026-08-01 12:00:00'),
(26, 4, 9, 'Wooden Animal Puzzle', 'wooden-animal-puzzle', 'Six chunky wooden animals that stack into a little forest scene.', 38.00, NULL, 14, 'SA-PUZZLE-01', 'active', 1, '2026-08-15 12:00:00', '2026-08-15 12:00:00'),
(27, 4, 9, 'Knit Fox Rattle', 'knit-fox-rattle', 'A soft knit fox with a quiet wooden rattle sewn inside.', 26.00, NULL, 18, 'SA-RATTLE-01', 'active', 0, '2026-09-01 12:00:00', '2026-09-01 12:00:00'),
(28, 4, 10, 'Single-Origin Cocoa Tin', 'single-origin-cocoa-tin', 'A tin of stone-ground cocoa for warm milk, with a spoon that fits the rim.', 16.00, NULL, 26, 'SA-COCOA-01', 'active', 0, '2026-09-10 12:00:00', '2026-09-10 12:00:00'),
(29, 4, 10, 'Wildflower Honey', 'wildflower-honey', 'A small jar of wildflower honey with a peel-away seal and a short ingredient list.', 19.00, NULL, 4, 'SA-HONEY-01', 'active', 0, '2026-09-18 12:00:00', '2026-09-18 12:00:00'),
(30, 4, 10, 'Evening Tea Sampler', 'evening-tea-sampler', 'Six sachets of caffeine-free evening tea, packed in a flat tin for travel.', 28.00, 34.00, 20, 'SA-TEA-03', 'active', 0, '2026-10-01 12:00:00', '2026-10-01 12:00:00');

INSERT INTO product_images (product_id, image_path, display_order, created_at) VALUES
(1, 'uploads/products/seed-01.jpg', 0, '2026-06-02 10:00:00'),
(1, 'uploads/products/seed-01b.jpg', 1, '2026-06-02 10:00:00'),
(2, 'uploads/products/seed-02.jpg', 0, '2026-06-08 10:00:00'),
(3, 'uploads/products/seed-03.jpg', 0, '2026-06-14 10:00:00'),
(4, 'uploads/products/seed-04.jpg', 0, '2026-06-20 10:00:00'),
(4, 'uploads/products/seed-04b.jpg', 1, '2026-06-20 10:00:00'),
(5, 'uploads/products/seed-05.jpg', 0, '2026-06-26 10:00:00'),
(6, 'uploads/products/seed-06.jpg', 0, '2026-07-02 10:00:00'),
(7, 'uploads/products/seed-07.jpg', 0, '2026-07-08 10:00:00'),
(8, 'uploads/products/seed-08.jpg', 0, '2026-07-14 10:00:00'),
(9, 'uploads/products/seed-09.jpg', 0, '2026-07-20 10:00:00'),
(10, 'uploads/products/seed-10.jpg', 0, '2026-07-26 10:00:00'),
(11, 'uploads/products/seed-11.jpg', 0, '2026-06-04 11:00:00'),
(12, 'uploads/products/seed-12.jpg', 0, '2026-06-18 11:00:00'),
(12, 'uploads/products/seed-12b.jpg', 1, '2026-06-18 11:00:00'),
(13, 'uploads/products/seed-13.jpg', 0, '2026-07-01 11:00:00'),
(14, 'uploads/products/seed-14.jpg', 0, '2026-07-12 11:00:00'),
(15, 'uploads/products/seed-15.jpg', 0, '2026-07-22 11:00:00'),
(16, 'uploads/products/seed-16.jpg', 0, '2026-08-02 11:00:00'),
(16, 'uploads/products/seed-16b.jpg', 1, '2026-08-02 11:00:00'),
(17, 'uploads/products/seed-17.jpg', 0, '2026-08-12 11:00:00'),
(18, 'uploads/products/seed-18.jpg', 0, '2026-08-20 11:00:00'),
(19, 'uploads/products/seed-19.jpg', 0, '2026-08-28 11:00:00'),
(20, 'uploads/products/seed-20.jpg', 0, '2026-09-06 11:00:00'),
(21, 'uploads/products/seed-21.jpg', 0, '2026-06-06 12:00:00'),
(21, 'uploads/products/seed-21b.jpg', 1, '2026-06-06 12:00:00'),
(22, 'uploads/products/seed-22.jpg', 0, '2026-06-22 12:00:00'),
(23, 'uploads/products/seed-23.jpg', 0, '2026-07-06 12:00:00'),
(24, 'uploads/products/seed-24.jpg', 0, '2026-07-18 12:00:00'),
(25, 'uploads/products/seed-25.jpg', 0, '2026-08-01 12:00:00'),
(26, 'uploads/products/seed-26.jpg', 0, '2026-08-15 12:00:00'),
(26, 'uploads/products/seed-26b.jpg', 1, '2026-08-15 12:00:00'),
(27, 'uploads/products/seed-27.jpg', 0, '2026-09-01 12:00:00'),
(28, 'uploads/products/seed-28.jpg', 0, '2026-09-10 12:00:00'),
(29, 'uploads/products/seed-29.jpg', 0, '2026-09-18 12:00:00'),
(30, 'uploads/products/seed-30.jpg', 0, '2026-10-01 12:00:00');

INSERT INTO wishlists (user_id, product_id, created_at) VALUES
(5, 12, '2026-09-01 09:00:00'),
(5, 16, '2026-09-02 09:00:00'),
(6, 4, '2026-09-04 09:00:00'),
(7, 29, '2026-09-06 09:00:00'),
(8, 19, '2026-09-08 09:00:00');

INSERT INTO order_counters (day_key, seq_value) VALUES
('20260902', 1),
('20260908', 1),
('20260914', 1),
('20260918', 1),
('20260921', 1),
('20260925', 1),
('20260928', 1),
('20261001', 1),
('20261003', 1),
('20261005', 1);

INSERT INTO orders (id, order_number, user_id, subtotal, discount_amount, shipping_fee, total_amount, coupon_id, payment_method, payment_status, transaction_id, order_status, shipping_name, shipping_email, shipping_phone, shipping_address, cancellation_reason, cancelled_at, stock_restored, created_at, updated_at) VALUES
(1, 'ORD-20260902-000001', 5, 124.00, 0.00, 8.00, 132.00, NULL, 'paypal', 'paid', 'CAPTURE-SAMPLE-0001', 'completed', 'Elena Voss', 'elena.voss@example.com', '+1 555 2011', '402 Cedar Avenue, Apt 3, Burlington, Vermont', NULL, NULL, 0, '2026-09-02 11:15:00', '2026-09-12 16:00:00'),
(2, 'ORD-20260908-000002', 6, 150.00, 25.00, 0.00, 125.00, 2, 'paypal', 'paid', 'CAPTURE-SAMPLE-0002', 'shipped', 'Marcus Hale', 'marcus.hale@example.com', '+1 555 2022', '77 North Wharf, Apt 12, Seattle, Washington', NULL, NULL, 0, '2026-09-08 15:20:00', '2026-09-20 09:00:00'),
(3, 'ORD-20260914-000003', 7, 65.00, 0.00, 8.00, 73.00, NULL, 'cod', 'unpaid', NULL, 'processing', 'Priya Nandakumar', 'priya.nandakumar@example.com', '+1 555 2033', '19 Orchard Close, Austin, Texas', NULL, NULL, 0, '2026-09-14 10:05:00', '2026-09-16 11:00:00'),
(4, 'ORD-20260918-000004', 8, 96.00, 0.00, 8.00, 104.00, NULL, 'paypal', 'paid', 'CAPTURE-SAMPLE-0004', 'paid', 'Jonah Ellison', 'jonah.ellison@example.com', '+1 555 2044', '250 Pier Street, Portland, Maine', NULL, NULL, 0, '2026-09-18 13:40:00', '2026-09-18 13:45:00'),
(5, 'ORD-20260921-000005', 9, 89.00, 0.00, 8.00, 97.00, NULL, 'paypal', 'unpaid', NULL, 'pending', 'Amira Soltani', 'amira.soltani@example.com', '+1 555 2055', '8 Lantern Court, Santa Fe, New Mexico', NULL, NULL, 0, '2026-09-21 18:10:00', '2026-09-21 18:10:00'),
(6, 'ORD-20260925-000006', 5, 58.00, 5.80, 8.00, 60.20, 1, 'paypal', 'paid', 'CAPTURE-SAMPLE-0006', 'completed', 'Elena Voss', 'elena.voss@example.com', '+1 555 2011', '402 Cedar Avenue, Apt 3, Burlington, Vermont', NULL, NULL, 0, '2026-09-25 12:30:00', '2026-10-02 15:00:00'),
(7, 'ORD-20260928-000007', 6, 154.00, 0.00, 0.00, 154.00, NULL, 'paypal', 'refunded', 'CAPTURE-SAMPLE-0007', 'cancelled', 'Marcus Hale', 'marcus.hale@example.com', '+1 555 2022', '77 North Wharf, Apt 12, Seattle, Washington', 'Changed my mind before it shipped', '2026-09-29 09:12:00', 1, '2026-09-28 16:45:00', '2026-09-29 09:12:00'),
(8, 'ORD-20261001-000008', 7, 64.00, 0.00, 8.00, 72.00, NULL, 'cod', 'paid', 'COD-SAMPLE-0008', 'completed', 'Priya Nandakumar', 'priya.nandakumar@example.com', '+1 555 2033', '19 Orchard Close, Austin, Texas', NULL, NULL, 0, '2026-10-01 09:20:00', '2026-10-04 17:00:00'),
(9, 'ORD-20261003-000009', 8, 64.00, 0.00, 8.00, 72.00, NULL, 'cod', 'unpaid', NULL, 'pending', 'Jonah Ellison', 'jonah.ellison@example.com', '+1 555 2044', '250 Pier Street, Portland, Maine', NULL, NULL, 0, '2026-10-03 19:05:00', '2026-10-03 19:05:00'),
(10, 'ORD-20261005-000010', 9, 101.00, 0.00, 8.00, 109.00, NULL, 'paypal', 'paid', 'CAPTURE-SAMPLE-0010', 'shipped', 'Amira Soltani', 'amira.soltani@example.com', '+1 555 2055', '8 Lantern Court, Santa Fe, New Mexico', NULL, NULL, 0, '2026-10-05 11:50:00', '2026-10-06 08:30:00');

INSERT INTO order_items (order_id, product_id, seller_id, product_name, quantity, unit_price, subtotal, created_at) VALUES
(1, 1, 2, 'Stoneware Pour-Over Carafe', 1, 48.00, 48.00, '2026-09-02 11:15:00'),
(1, 2, 2, 'Oat Linen Throw', 1, 76.00, 76.00, '2026-09-02 11:15:00'),
(2, 12, 3, 'Canvas Weekender', 1, 128.00, 128.00, '2026-09-08 15:20:00'),
(2, 15, 3, 'Aluminum Camp Mug', 1, 22.00, 22.00, '2026-09-08 15:20:00'),
(3, 21, 4, 'Cotton Ruled Journal', 1, 18.00, 18.00, '2026-09-14 10:05:00'),
(3, 29, 4, 'Wildflower Honey', 1, 19.00, 19.00, '2026-09-14 10:05:00'),
(3, 30, 4, 'Evening Tea Sampler', 1, 28.00, 28.00, '2026-09-14 10:05:00'),
(4, 13, 3, 'Field Binoculars', 1, 96.00, 96.00, '2026-09-18 13:40:00'),
(5, 4, 2, 'Ceramic Table Lamp', 1, 89.00, 89.00, '2026-09-21 18:10:00'),
(6, 11, 3, 'Merino Trail Beanie', 1, 32.00, 32.00, '2026-09-25 12:30:00'),
(6, 17, 3, 'Wool Hiking Socks', 1, 26.00, 26.00, '2026-09-25 12:30:00'),
(7, 14, 3, 'Recycled Shell Jacket', 1, 154.00, 154.00, '2026-09-28 16:45:00'),
(8, 26, 4, 'Wooden Animal Puzzle', 1, 38.00, 38.00, '2026-10-01 09:20:00'),
(8, 27, 4, 'Knit Fox Rattle', 1, 26.00, 26.00, '2026-10-01 09:20:00'),
(9, 5, 2, 'Speckled Ramen Bowls', 1, 64.00, 64.00, '2026-10-03 19:05:00'),
(10, 16, 3, 'Speaker Lantern', 1, 79.00, 79.00, '2026-10-05 11:50:00'),
(10, 15, 3, 'Aluminum Camp Mug', 1, 22.00, 22.00, '2026-10-05 11:50:00');

INSERT INTO payments (order_id, payment_method, transaction_id, provider_reference, amount, currency, status, response_data, created_at, updated_at) VALUES
(1, 'paypal', 'CAPTURE-SAMPLE-0001', 'SAMPLEPAY-0001', 132.00, 'USD', 'paid', '{"note":"Sample sandbox record"}', '2026-09-02 11:16:00', '2026-09-02 11:16:00'),
(2, 'paypal', 'CAPTURE-SAMPLE-0002', 'SAMPLEPAY-0002', 125.00, 'USD', 'paid', '{"note":"Sample sandbox record"}', '2026-09-08 15:22:00', '2026-09-08 15:22:00'),
(3, 'cod', NULL, NULL, 73.00, 'USD', 'unpaid', NULL, '2026-09-14 10:05:00', '2026-09-14 10:05:00'),
(4, 'paypal', 'CAPTURE-SAMPLE-0004', 'SAMPLEPAY-0004', 104.00, 'USD', 'paid', '{"note":"Sample sandbox record"}', '2026-09-18 13:44:00', '2026-09-18 13:44:00'),
(5, 'paypal', NULL, NULL, 97.00, 'USD', 'unpaid', NULL, '2026-09-21 18:10:00', '2026-09-21 18:10:00'),
(6, 'paypal', 'CAPTURE-SAMPLE-0006', 'SAMPLEPAY-0006', 60.20, 'USD', 'paid', '{"note":"Sample sandbox record"}', '2026-09-25 12:32:00', '2026-09-25 12:32:00'),
(7, 'paypal', 'CAPTURE-SAMPLE-0007', 'SAMPLEPAY-0007', 154.00, 'USD', 'refunded', '{"note":"Sample refund after cancellation"}', '2026-09-28 16:46:00', '2026-09-29 09:12:00'),
(8, 'cod', 'COD-SAMPLE-0008', NULL, 72.00, 'USD', 'paid', '{"note":"Collected on delivery"}', '2026-10-01 09:20:00', '2026-10-04 17:00:00'),
(9, 'cod', NULL, NULL, 72.00, 'USD', 'unpaid', NULL, '2026-10-03 19:05:00', '2026-10-03 19:05:00'),
(10, 'paypal', 'CAPTURE-SAMPLE-0010', 'SAMPLEPAY-0010', 109.00, 'USD', 'paid', '{"note":"Sample sandbox record"}', '2026-10-05 11:52:00', '2026-10-05 11:52:00');

INSERT INTO order_status_events (order_id, status, note, actor_id, created_at) VALUES
(1, 'pending', 'Order placed', 5, '2026-09-02 11:15:00'),
(1, 'paid', 'PayPal payment verified', 5, '2026-09-02 11:16:00'),
(1, 'processing', 'Packed at the studio', 1, '2026-09-04 10:00:00'),
(1, 'shipped', 'Handed to the carrier', 1, '2026-09-06 10:00:00'),
(1, 'completed', 'Delivered', 1, '2026-09-12 16:00:00'),
(2, 'pending', 'Order placed', 6, '2026-09-08 15:20:00'),
(2, 'paid', 'PayPal payment verified', 6, '2026-09-08 15:22:00'),
(2, 'processing', 'Packed at the studio', 1, '2026-09-12 10:00:00'),
(2, 'shipped', 'On the way', 1, '2026-09-20 09:00:00'),
(3, 'pending', 'Order placed', 7, '2026-09-14 10:05:00'),
(3, 'processing', 'Preparing cash-on-delivery parcel', 1, '2026-09-16 11:00:00'),
(4, 'pending', 'Order placed', 8, '2026-09-18 13:40:00'),
(4, 'paid', 'PayPal payment verified', 8, '2026-09-18 13:45:00'),
(5, 'pending', 'Order placed', 9, '2026-09-21 18:10:00'),
(6, 'pending', 'Order placed', 5, '2026-09-25 12:30:00'),
(6, 'paid', 'PayPal payment verified', 5, '2026-09-25 12:32:00'),
(6, 'completed', 'Delivered', 1, '2026-10-02 15:00:00'),
(7, 'pending', 'Order placed', 6, '2026-09-28 16:45:00'),
(7, 'paid', 'PayPal payment verified', 6, '2026-09-28 16:46:00'),
(7, 'cancelled', 'Changed my mind before it shipped', 6, '2026-09-29 09:12:00'),
(8, 'pending', 'Order placed', 7, '2026-10-01 09:20:00'),
(8, 'paid', 'Cash collected on delivery', 1, '2026-10-04 17:00:00'),
(8, 'completed', 'Delivered', 1, '2026-10-04 17:05:00'),
(9, 'pending', 'Order placed', 8, '2026-10-03 19:05:00'),
(10, 'pending', 'Order placed', 9, '2026-10-05 11:50:00'),
(10, 'paid', 'PayPal payment verified', 9, '2026-10-05 11:52:00'),
(10, 'shipped', 'Left the studio', 1, '2026-10-06 08:30:00');

INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, status, created_at, updated_at) VALUES
(1, 5, 1, 5, 'The carafe pours without dripping and looks right on a wood counter.', 'published', '2026-09-14 09:00:00', '2026-09-14 09:00:00'),
(2, 5, 1, 4, 'The linen is weighty and softened after one wash. I would buy the larger size too.', 'published', '2026-09-14 09:10:00', '2026-09-14 09:10:00'),
(12, 6, 2, 5, 'The weekender holds a long weekend without looking overstuffed.', 'published', '2026-09-22 08:40:00', '2026-09-22 08:40:00'),
(21, 7, 3, 5, 'The paper takes ink well and the cloth cover does not feel delicate.', 'published', '2026-09-20 18:00:00', '2026-09-20 18:00:00'),
(13, 8, 4, 4, 'Clear glass and a strap that does not twist. Good for the harbor walk.', 'published', '2026-09-21 10:15:00', '2026-09-21 10:15:00'),
(11, 5, 6, 4, 'Warm without itching, and it fits under a hood.', 'published', '2026-10-03 11:00:00', '2026-10-03 11:00:00'),
(17, 5, 6, 3, 'Comfortable, though the cuff felt a little loose on the first hike.', 'hidden', '2026-10-03 11:05:00', '2026-10-03 12:00:00'),
(26, 7, 8, 5, 'The animals are chunky enough for small hands and the paint is even.', 'published', '2026-10-05 09:30:00', '2026-10-05 09:30:00'),
(16, 9, 10, 5, 'The lantern is brighter than I expected and the speaker is pleasant at low volume.', 'published', '2026-10-06 09:00:00', '2026-10-06 09:00:00');

INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES
(5, 'Order delivered', 'Order ORD-20260902-000001 is completed.', 'order', 1, '2026-09-12 16:00:00'),
(6, 'Order shipped', 'Order ORD-20260908-000002 is on the way.', 'order', 0, '2026-09-20 09:00:00'),
(2, 'New sale', 'You have items in a recent marketplace order.', 'order', 0, '2026-10-03 19:05:00'),
(1, 'New marketplace order', 'ORD-20261005-000010 was placed.', 'order', 0, '2026-10-05 11:50:00');

SET FOREIGN_KEY_CHECKS = 1;
