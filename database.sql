-- ============================================
-- STOCK APP — Base de données complète v2.0
-- ============================================

CREATE DATABASE IF NOT EXISTS stock_app;
USE stock_app;

-- ============================================
-- TABLE USERS (avec rôle et photo)
-- ============================================
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100)  NOT NULL,
    email      VARCHAR(100)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    photo      VARCHAR(255)  DEFAULT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE PRODUCTS
-- ============================================
CREATE TABLE products (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)   NOT NULL,
    quantity   INT            NOT NULL DEFAULT 0,
    price      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE SALES
-- ============================================
CREATE TABLE sales (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT            NOT NULL,
    quantity_sold INT            NOT NULL,
    sale_price    DECIMAL(10,2)  NOT NULL,
    sold_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Table fournisseurs
CREATE TABLE suppliers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    phone      VARCHAR(20)  DEFAULT NULL,
    email      VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Table achats
CREATE TABLE purchases (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT            NOT NULL,
    supplier_id   INT            NOT NULL,
    quantity      INT            NOT NULL,
    unit_price    DECIMAL(10,2)  NOT NULL,
    purchased_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    type       VARCHAR(50)  NOT NULL DEFAULT 'stock_alert',
    message    TEXT         NOT NULL,
    product_id INT          DEFAULT NULL,
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
-- ============================================
-- COMPTE ADMIN PAR DÉFAUT
-- Email    : admin@stock.com
-- Password : admin123
-- ============================================
INSERT INTO users (username, email, password, role)
VALUES (
    'Administrateur',
    'admin@stock.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutKiziK',
    'admin'
);