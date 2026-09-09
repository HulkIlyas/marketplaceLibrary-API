<?php

require_once __DIR__ . '/autoloader.php';

echo "Starting Database Migration...\n";

$host   = 'localhost';
$user   = 'root';
$pass   = 'secret123'; // Replace with your MySQL password
$dbName = 'project';   // Set to your project database

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 1. Create and select 'project' database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbName`;");
    echo "Database '$dbName' ready.\n";

    // Disable foreign key checks to safely drop and recreate tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Drop old incompatible tables if they exist
    $pdo->exec("DROP TABLE IF EXISTS books;");
    $pdo->exec("DROP TABLE IF EXISTS users;");

    // 2. Create users table with INT UNSIGNED id
    $sqlUsers = "CREATE TABLE users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        email_verified_at DATETIME NULL,
        email_verification_token VARCHAR(255) NULL,
        email_verification_expires_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sqlUsers);
    echo "Table 'users' created successfully.\n";

    // 3. Create books table with matching INT UNSIGNED owner_id
    $sqlBooks = "CREATE TABLE books (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(150) NOT NULL,
        owner_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_books_owner 
            FOREIGN KEY (owner_id) 
            REFERENCES users(id) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sqlBooks);
    echo "Table 'books' created successfully.\n";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

echo "Migration completed successfully!\n";