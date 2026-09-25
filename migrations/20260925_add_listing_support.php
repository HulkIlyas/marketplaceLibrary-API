<?php

require_once dirname(__DIR__) . '/autoloader.php';

use App\Config\Database;
$pdo = Database::getConnection();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column
    ");
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    if (!columnExists($pdo, 'books', 'isbn')) {
        $pdo->exec('ALTER TABLE books ADD COLUMN isbn VARCHAR(20) NULL AFTER author');
    }
    if (!columnExists($pdo, 'books', 'genre')) {
        $pdo->exec('ALTER TABLE books ADD COLUMN genre VARCHAR(100) NULL AFTER isbn');
    }
    if (!columnExists($pdo, 'books', 'description')) {
        $pdo->exec('ALTER TABLE books ADD COLUMN description TEXT NULL AFTER edition');
    }
    if (!columnExists($pdo, 'books', 'city')) {
        $pdo->exec('ALTER TABLE books ADD COLUMN city VARCHAR(100) NULL AFTER description');
    }

    // BUY remains only for historical rows; seller-created listings cannot use it.
    $pdo->exec("ALTER TABLE books MODIFY listing_type ENUM('BUY','SELL','EXCHANGE','SELL_OR_EXCHANGE') NOT NULL DEFAULT 'BUY'");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS book_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            book_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_cover BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_book_images_book_sort (book_id, sort_order),
            CONSTRAINT fk_book_images_book
                FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "Marketplace listing migration completed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Marketplace listing migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
