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