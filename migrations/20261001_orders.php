<?php
// Additive and repeatable. Never run the legacy destructive migrate.php for this feature.
require_once dirname(__DIR__) . '/autoloader.php';
$db = App\Config\Database::getConnection();
$exists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='books' AND COLUMN_NAME='listing_status'")->fetchColumn();
if (!$exists) {
    $db->exec("ALTER TABLE books ADD listing_status ENUM('ACTIVE','RESERVED','SOLD') NOT NULL DEFAULT 'ACTIVE', ADD INDEX idx_listing_status (listing_status)");
}
$db->exec("CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL,
    address VARCHAR(500) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL, book_id INT UNSIGNED NOT NULL, seller_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('PENDING','ACCEPTED','IN_PROGRESS','DELIVERED','DECLINED') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_book (order_id,book_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Orders migration complete. Existing records preserved.\n";
