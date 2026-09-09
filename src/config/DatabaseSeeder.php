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
                'email_verified_at' => date('Y-m-d H:i:s'),
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

    private function seedBooks(): void
    {
        // Fetch user IDs
        $stmt = $this->db->query("SELECT id FROM users ORDER BY id ASC LIMIT 2");
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userIds)) {
            echo "No users found to assign books to.\n";
            return;
        }

        $books = [
            [
                'title'    => 'Clean Code',
                'author'   => 'Robert C. Martin',
                'owner_id' => $userIds[0],
            ],
            [
                'title'    => 'Design Patterns',
                'author'   => 'Erich Gamma et al.',
                'owner_id' => $userIds[0],
            ],
            [
                'title'    => 'The Pragmatic Programmer',
                'author'   => 'Andrew Hunt & David Thomas',
                'owner_id' => $userIds[1] ?? $userIds[0],
            ],
        ];

        $sql = "INSERT INTO books (title, author, owner_id) VALUES (:title, :author, :owner_id)";
        $stmt = $this->db->prepare($sql);

        foreach ($books as $book) {
            $stmt->execute($book);
        }

        echo "Books seeded successfully.\n";
    }
}