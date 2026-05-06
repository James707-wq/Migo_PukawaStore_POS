-- ============================================================
--  Pukawa Store POS System - Database Schema
--  Tables Only (Database must be created in cPanel first)
-- ============================================================

-- -------------------------------------------------------
-- USERS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id     INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(50)  NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  full_name   VARCHAR(100) NOT NULL,
  role        ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- CATEGORIES
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  category_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(80) NOT NULL UNIQUE,
  created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (category_name) VALUES
  ('Beverages'),('Snacks'),('Canned Goods'),('Dairy'),
  ('Bread & Pastry'),('Personal Care'),('Household'),('Tobacco'),('Frozen Foods');

-- -------------------------------------------------------
-- PRODUCTS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  product_id      INT AUTO_INCREMENT PRIMARY KEY,
  barcode         VARCHAR(50)    UNIQUE,
  product_name    VARCHAR(150)   NOT NULL,
  category_id     INT            NOT NULL,
  price           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  cost_price      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  stock_quantity  INT            NOT NULL DEFAULT 0,
  low_stock_level INT            NOT NULL DEFAULT 5,
  expiration_date DATE,
  image_path      VARCHAR(255),
  is_active       TINYINT(1)     NOT NULL DEFAULT 1,
  created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

INSERT INTO products (barcode, product_name, category_id, price, cost_price, stock_quantity, low_stock_level, expiration_date) VALUES
  ('4800888003428', 'Coca-Cola 1.5L',          1,  65.00, 50.00, 48, 10, '2025-12-31'),
  ('4806520480026', 'C2 Apple 500ml',           1,  25.00, 18.00, 60, 15, '2025-10-15'),
  ('4800007040021', 'Mineral Water 500ml',      1,  12.00,  8.00, 100,20, '2026-06-30'),
  ('4800167027718', 'Oishi Prawn Crackers',     2,  22.00, 15.00,  3,  5, '2025-09-01'),
  ('4800024130018', 'Piattos Cheese 85g',       2,  45.00, 32.00, 25, 10, '2025-11-20'),
  ('4800888901019', 'Jack n Jill Potato Chips', 2,  35.00, 25.00,  2,  5, '2025-08-30'),
  ('4806519102035', 'Purefoods Corned Beef',    3,  58.00, 42.00, 30, 10, '2026-03-15'),
  ('4800888005217', 'Ligo Sardines in Tomato',  3,  32.00, 22.00, 40, 10, '2026-01-10'),
  ('4800008430014', 'Bear Brand Powdered Milk', 4, 155.00,120.00, 15,  5, '2025-12-01'),
  ('4800033032016', 'Eden Cheese 160g',         4,  95.00, 72.00,  4,  5, '2025-07-20'),
  ('4800888810018', 'Gardenia White Bread',     5,  55.00, 42.00, 10,  5, '2025-05-10'),
  ('4800888111012', 'Colgate Total 175g',       6,  99.00, 75.00, 20,  8, '2027-01-01'),
  ('4800888210019', 'Safeguard Bar Soap 135g',  6,  45.00, 32.00, 18,  8, '2027-06-15'),
  ('4800888440015', 'Ariel Powder 1kg',         7, 140.00,105.00,  8,  5, '2027-12-31'),
  ('4800888550013', 'Lucky Me Pancit Canton',   2,  17.00, 11.00, 75, 20, '2025-12-01');

-- -------------------------------------------------------
-- TRANSACTIONS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id     INT AUTO_INCREMENT PRIMARY KEY,
  transaction_no     VARCHAR(30)   NOT NULL UNIQUE,
  cashier_id         INT           NOT NULL,
  subtotal           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  change_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method     ENUM('cash','gcash','card') NOT NULL DEFAULT 'cash',
  status             ENUM('completed','voided','refunded') NOT NULL DEFAULT 'completed',
  notes              TEXT,
  transaction_date   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TRANSACTION ITEMS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaction_items (
  item_id        INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT           NOT NULL,
  product_id     INT           NOT NULL,
  product_name   VARCHAR(150)  NOT NULL,
  barcode        VARCHAR(50),
  quantity       INT           NOT NULL DEFAULT 1,
  unit_price     DECIMAL(10,2) NOT NULL,
  subtotal       DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
  FOREIGN KEY (product_id)     REFERENCES products(product_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- RETURNS / REFUNDS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS returns (
  return_id         INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id    INT            NOT NULL,
  return_no         VARCHAR(30)    NOT NULL UNIQUE,
  returned_by       INT            NOT NULL,
  refund_amount     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  refund_method     ENUM('cash','gcash','card') NOT NULL DEFAULT 'cash',
  reason            VARCHAR(255)   NOT NULL,
  notes             TEXT,
  status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  return_date       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
  FOREIGN KEY (returned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- RETURN ITEMS (which items were returned)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS return_items (
  return_item_id    INT AUTO_INCREMENT PRIMARY KEY,
  return_id         INT            NOT NULL,
  product_id        INT            NOT NULL,
  product_name      VARCHAR(150)   NOT NULL,
  quantity          INT            NOT NULL DEFAULT 1,
  unit_price        DECIMAL(10,2)  NOT NULL,
  refund_amount     DECIMAL(10,2)  NOT NULL,
  FOREIGN KEY (return_id) REFERENCES returns(return_id),
  FOREIGN KEY (product_id) REFERENCES products(product_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- VIEWS (commented out for InfinityFree compatibility)
-- -------------------------------------------------------
-- These VIEWs can be created manually if needed
-- CREATE OR REPLACE VIEW v_daily_sales AS
--   SELECT
--     DATE(transaction_date)  AS sale_date,
--     COUNT(*)                AS total_transactions,
--     SUM(total_amount)       AS total_revenue,
--     SUM(discount_amount)    AS total_discounts
--   FROM transactions
--   WHERE status = 'completed'
--   GROUP BY DATE(transaction_date);

-- CREATE OR REPLACE VIEW v_product_sales AS
--   SELECT
--     p.product_id,
--     p.product_name,
--     p.barcode,
--     c.category_name,
--     SUM(ti.quantity)  AS total_sold,
--     SUM(ti.subtotal)  AS total_revenue
--   FROM transaction_items ti
--   JOIN products p    ON ti.product_id = p.product_id
--   JOIN categories c  ON p.category_id = c.category_id
--   JOIN transactions t ON ti.transaction_id = t.transaction_id
--   WHERE t.status = 'completed'
--   GROUP BY p.product_id;

-- Done!
SELECT 'Pukawa Store POS database installed successfully.' AS message;
