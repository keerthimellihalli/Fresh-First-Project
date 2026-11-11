CREATE DATABASE IF NOT EXISTS perishable_stock_db;

USE perishable_stock_db;


-- 1. USERS TABLE
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Store Manager', 'Staff') NOT NULL
);


-- 2. PRODUCTS TABLE
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    purchase_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    storage_temperature DECIMAL(5,2),
    storage_condition VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_dates CHECK (expiry_date > purchase_date)
);


-- 3. RULES TABLE
-- Rule values are stored here instead of directly inside program code.
CREATE TABLE rules (
    rule_id INT AUTO_INCREMENT PRIMARY KEY,
    rule_code VARCHAR(10) NOT NULL UNIQUE,
    rule_name VARCHAR(100) NOT NULL,
    rule_description VARCHAR(255) NOT NULL,
    rule_value VARCHAR(100),
    result VARCHAR(100) NOT NULL
);


-- 4. VERIFICATIONS TABLE
CREATE TABLE verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    verification_date DATE NOT NULL,
    expiry_status VARCHAR(50),
    quantity_status VARCHAR(50),
    storage_status VARCHAR(50),
    record_status VARCHAR(50),
    overall_status VARCHAR(100),

    FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE
);


-- 5. ACTIONS TABLE
CREATE TABLE actions (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    priority VARCHAR(30) NOT NULL,
    suggested_action VARCHAR(100) NOT NULL,
    action_status VARCHAR(30) DEFAULT 'Pending',
    action_date DATE,

    FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE
);


-- 6. HISTORY TABLE
CREATE TABLE history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE,

    FOREIGN KEY (performed_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
);


-- RULE VALUES
INSERT INTO rules
(rule_code, rule_name, rule_description, rule_value, result)
VALUES

('R1',
 'Expiry Check',
 'Expiry date is earlier than today',
 'expiry_date < today',
 'EXPIRED'),

('R2',
 'Near Expiry',
 'Product is within the near-expiry period',
 '0 <= days_left <= threshold',
 'NEAR EXPIRY'),

('R3',
 'Valid Expiry',
 'Product is beyond the near-expiry period',
 'days_left > threshold',
 'VALID'),

('R4',
 'Quantity Check',
 'Quantity must be greater than zero and a whole number',
 'quantity > 0',
 'VALID QUANTITY'),

('R5',
 'Storage Check',
 'Recorded temperature must be within allowed range',
 'Category-specific range',
 'STORAGE ISSUE'),

('R6',
 'Missing Data Check',
 'Required product information must not be blank',
 'Name, category, dates, storage',
 'INCOMPLETE RECORD'),

('R7',
 'Date Logic Check',
 'Expiry date must be after purchase date',
 'expiry_date > purchase_date',
 'INVALID DATES');