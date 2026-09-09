<?php

namespace App\Config;

use App\Config\Database;
use PDO;
use PDOException;

class UserSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        $users = [
            [
                'name'     => 'Admin User',
                'email'    => 'admin@example.com',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
            ],
            [
                'name'     => 'John Doe',
                'email'    => 'john@example.com',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
            ],
            [
                'name'     => 'Jane Smith',
                'email'    => 'jane@example.com',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
            ]
        ];

        $sql = "INSERT INTO users (name, email, password) 
                VALUES (:name, :email, :password) 
                ON DUPLICATE KEY UPDATE name = VALUES(name);";

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($users as $user) {
                $stmt->execute([
                    'name'     => $user['name'],
                    'email'    => $user['email'],
                    'password' => $user['password'],
                ]);
            }

            echo "User seeding completed successfully.\n";
        } catch (PDOException $e) {
            echo "Seeding failed: " . $e->getMessage() . "\n";
        }
    }
}