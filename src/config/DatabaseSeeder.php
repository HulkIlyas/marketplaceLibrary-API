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
        // Fetch user IDs
        $stmtUsers = $this->db->query("SELECT id FROM users ORDER BY id ASC LIMIT 2");
        $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userIds)) {
            echo "No users found to assign books to.\n";
            return;
        }

        // Fetch category IDs keyed by slug
        $stmtCategories = $this->db->query("SELECT slug, id FROM categories");
        $categories = $stmtCategories->fetchAll(PDO::FETCH_KEY_PAIR);

        if (empty($categories)) {
            echo "No categories found. Run seedCategories first.\n";
            return;
        }

        $books = [
            [
                'title'       => 'Clean Code',
                'author'      => 'Robert C. Martin',
                'owner_id'    => $userIds[0],
                'category_id' => $categories['books'] ?? reset($categories),
            ],
            [
                'title'       => 'Design Patterns',
                'author'      => 'Erich Gamma et al.',
                'owner_id'    => $userIds[0],
                'category_id' => $categories['books'] ?? reset($categories),
            ],
            [
                'title'       => 'The Pragmatic Programmer',
                'author'      => 'Andrew Hunt & David Thomas',
                'owner_id'    => $userIds[1] ?? $userIds[0],
                'category_id' => $categories['books'] ?? reset($categories),
            ],
        ];

        $sql = "INSERT INTO books (title, author, owner_id, category_id) 
                VALUES (:title, :author, :owner_id, :category_id)";
        $stmt = $this->db->prepare($sql);

        foreach ($books as $book) {
            $stmt->execute($book);
        }

        echo "Books seeded successfully.\n";
    }
}
