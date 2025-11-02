-- Database: haroon_jewellery

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    theme_preference ENUM('light', 'dark') DEFAULT 'light',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    remember_token VARCHAR(255),
    token_expiry TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('customer', 'admin', 'manager') DEFAULT 'customer',
    permissions JSON NULL,
    last_login DATETIME NULL,
    is_super_admin BOOLEAN DEFAULT FALSE
);

INSERT INTO users (name, email, mobile, password, role, is_super_admin, status, created_at)
VALUES (
    'Administrator',
    'admin@haroonjewellery.com',
    '+923001234567',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin',
    TRUE,
    'active',
    NOW()
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_description TEXT,
    sku VARCHAR(100) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    weight DECIMAL(8,2),
    dimensions VARCHAR(100),
    material VARCHAR(100),
    gemstone VARCHAR(100),
    category_id INT,
    featured BOOLEAN DEFAULT FALSE,
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    image VARCHAR(255),
    gallery_images JSON,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Product images table
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_mobile VARCHAR(15) NOT NULL,
    customer_address TEXT NOT NULL,
    customer_city VARCHAR(100) NOT NULL,
    customer_country VARCHAR(100) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('jazzcash_mobile','jazzcash_card' ,'cod', 'bank') DEFAULT 'jazzcash_mobile',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    jazzcash_transaction_id VARCHAR(255),
    jazzcash_mobile_number VARCHAR(15),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Shopping cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- JazzCash transactions table
CREATE TABLE jazzcash_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    pp_TxnRefNo VARCHAR(255) UNIQUE NOT NULL,
    pp_MerchantID VARCHAR(255) NOT NULL,
    pp_Amount DECIMAL(10,2) NOT NULL,
    pp_MobileNumber VARCHAR(15) NOT NULL,
    pp_ResponseCode VARCHAR(10),
    pp_ResponseMessage TEXT,
    pp_BankID VARCHAR(50),
    pp_SecureHash VARCHAR(255),
    pp_Language VARCHAR(10) DEFAULT 'EN',
    pp_Version VARCHAR(10) DEFAULT '1.1',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE card_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    pp_TxnRefNo VARCHAR(255) UNIQUE NOT NULL,
    pp_MerchantID VARCHAR(255) NOT NULL,
    pp_Amount DECIMAL(10,2) NOT NULL,
    pp_CardNumber VARCHAR(20),
    pp_CardExpiry VARCHAR(10),
    pp_CardHolderName VARCHAR(255),
    pp_ResponseCode VARCHAR(10),
    pp_ResponseMessage TEXT,
    pp_SecureHash VARCHAR(255),
    pp_Language VARCHAR(10) DEFAULT 'EN',
    pp_Version VARCHAR(10) DEFAULT '1.1',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Sliders table
CREATE TABLE sliders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255),
    description TEXT,
    image VARCHAR(255) NOT NULL,
    button_text VARCHAR(50),
    button_link VARCHAR(255),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'read', 'replied') DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'email', 'url') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('store_name', 'Haroon Jewellery', 'text'),
('store_email', 'info@haroonjewellery.com', 'email'),
('store_phone', '+92 300 1234567', 'text'),
('store_address', 'Main Boulevard, Lahore, Pakistan', 'textarea'),
('jazzcash_merchant_id', 'YOUR_MERCHANT_ID', 'text'),
('jazzcash_password', 'YOUR_PASSWORD', 'text'),
('jazzcash_salt', 'YOUR_SALT', 'text'),
('jazzcash_return_url', 'https://yourdomain.com/payment-callback.php', 'url'),
('currency', 'PKR', 'text'),
('shipping_cost', '200', 'number'),
('jazzcash_card_merchant_id', 'YOUR_CARD_MERCHANT_ID', 'text'),
('jazzcash_card_password', 'YOUR_CARD_PASSWORD', 'text'),
('jazzcash_card_salt', 'YOUR_CARD_SALT', 'text'),
('default_theme', 'light', 'text');

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(order_status);
CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_cart_session ON cart(session_id);