<?php

namespace App\Config;

use App\Config\Database;
use PDO;
use PDOException;

class DatabaseSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        try {
            // Disable foreign key checks during seeding
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");

            $this->seedUsers();
            $this->seedCategories();
            $this->seedBooks();

            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            echo "Database seeding finished successfully!\n";
        } catch (PDOException $e) {
            echo "Seeding failed: " . $e->getMessage() . "\n";
        }
    }

    private function seedUsers(): void
    {
        $users = [
            [
                'name'              => 'Ilyas Admin',
                'email'             => 'admin@marketplace.com',
                'password'          => password_hash('password123', PASSWORD_BCRYPT),
                'is_active'         => 1,
                'email_verified_at' => date('Y-m-d H:i:s')
            ],
            [
                'name'              => 'John Doe',
                'email'             => 'john@example.com',
                'password'          => password_hash('password123', PASSWORD_BCRYPT),
                'is_active'         => 1,
                'email_verified_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name'              => 'Jane Smith',
                'email'             => 'jane@example.com',
                'password'          => password_hash('password123', PASSWORD_BCRYPT),
                'is_active'         => 1,
                'email_verified_at' => null, // Unverified account
            ]
        ];

        $sql = "INSERT INTO users (name, email, password, is_active, email_verified_at) 
                VALUES (:name, :email, :password, :is_active, :email_verified_at)
                ON DUPLICATE KEY UPDATE name = VALUES(name);";

        $stmt = $this->db->prepare($sql);

        foreach ($users as $user) {
            $stmt->execute($user);
        }

        echo "Users seeded successfully.\n";
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Books',      'slug' => 'books',      'icon' => 'fa-solid fa-book'],
            ['name' => 'Stationery', 'slug' => 'stationery', 'icon' => 'fa-solid fa-pencil'],
            ['name' => 'Digital',    'slug' => 'digital',    'icon' => 'fa-solid fa-laptop'],
            ['name' => 'School',     'slug' => 'school',     'icon' => 'fa-solid fa-school'],
            ['name' => 'Art',        'slug' => 'art',        'icon' => 'fa-solid fa-palette'],
            ['name' => 'Manga',      'slug' => 'manga',      'icon' => 'fa-solid fa-book-open'],
        ];

        $sql = "INSERT INTO categories (name, slug, icon) 
                VALUES (:name, :slug, :icon)
                ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon)";

        $stmt = $this->db->prepare($sql);

        foreach ($categories as $category) {
            $stmt->execute($category);
        }

        echo "Categories seeded successfully.\n";
    }

   private function seedBooks(): void
{
    $stmtUsers = $this->db->query("SELECT id FROM users ORDER BY id ASC LIMIT 2");
    $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) return;

    $stmtCategories = $this->db->query("SELECT slug, id FROM categories");
    $categories = $stmtCategories->fetchAll(PDO::FETCH_KEY_PAIR);

    $defaultCat = $categories['books'] ?? reset($categories);

    $books = [
        [
            'title'          => 'Atomic Habits',
            'author'         => 'James Clear',
            'price'          => 180.00,
            'book_condition' => 'Very good',
            'listing_type'   => 'BUY',
            'edition'        => 'MARKETPLACE EDITION',
            'cover_color'    => '#d4a359',
            'owner_id'       => $userIds[0],
            'category_id'    => $defaultCat
        ],
        [
            'title'          => 'The Psychology of Money',
            'author'         => 'Morgan Housel',
            'price'          => 150.00,
            'book_condition' => 'Like new',
            'listing_type'   => 'SELL',
            'edition'        => 'MARKETPLACE EDITION',
            'cover_color'    => '#3b6e8c',
            'owner_id'       => $userIds[0],
            'category_id'    => $defaultCat
        ],
        [
            'title'          => 'Clean Code',
            'author'         => 'Robert C. Martin',
            'price'          => 210.00,
            'book_condition' => 'Good condition',
            'listing_type'   => 'EXCHANGE',
            'edition'        => 'MARKETPLACE EDITION',
            'cover_color'    => '#2b3a4a',
            'owner_id'       => $userIds[1] ?? $userIds[0],
            'category_id'    => $defaultCat
        ],
        [
            'title'          => 'Deep Work',
            'author'         => 'Cal Newport',
            'price'          => 170.00,
            'book_condition' => 'Very good',
            'listing_type'   => 'BUY',
            'edition'        => 'MARKETPLACE EDITION',
            'cover_color'    => '#a84332',
            'owner_id'       => $userIds[0],
            'category_id'    => $defaultCat
        ]
    ];

    $sql = "INSERT INTO books 
            (title, author, price, book_condition, listing_type, edition, cover_color, owner_id, category_id) 
            VALUES 
            (:title, :author, :price, :book_condition, :listing_type, :edition, :cover_color, :owner_id, :category_id)";
    
    $stmt = $this->db->prepare($sql);

    foreach ($books as $book) {
        $stmt->execute($book);
    }

    echo "Books seeded successfully.\n";
}
}
