-- ============================================
-- AURORA Platform Database Schema
-- aurora_db
-- ============================================

CREATE DATABASE IF NOT EXISTS aurora_db;
USE aurora_db;

-- ---- USERS TABLE ----
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password      VARCHAR(255)        NOT NULL,
    business_name VARCHAR(150)        NOT NULL,
    phone         VARCHAR(20)         DEFAULT NULL,
    role          ENUM('vendor','owner') DEFAULT 'vendor',
    created_at    TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);

-- ---- PRODUCTS TABLE ----
CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    name        VARCHAR(150) NOT NULL,
    category    VARCHAR(100) NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    target_sales INT          DEFAULT 100,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---- SALES TABLE ----
CREATE TABLE IF NOT EXISTS sales (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    product_id   INT           NOT NULL,
    user_id      INT           NOT NULL,
    quantity     INT           NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    sale_date    DATE          NOT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- ---- INVENTORY TABLE ----
CREATE TABLE IF NOT EXISTS inventory (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    product_id   INT NOT NULL,
    user_id      INT NOT NULL,
    stock_qty    INT DEFAULT 0,
    min_stock    INT DEFAULT 10,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- ---- REVIEWS TABLE ----
CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT          NOT NULL,
    user_id     INT          NOT NULL,
    reviewer    VARCHAR(100) NOT NULL,
    rating      INT          CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT         NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);