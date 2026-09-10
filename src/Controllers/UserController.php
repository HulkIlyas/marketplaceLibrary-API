<?php

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Utils\JWT;
use PDO;
use PDOException;

class UserController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }
    /**
     * POST /users/login or POST /login
     */
    public function login(array $data): void
    {
        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["error" => "Email and password are required"]);
            return;
        }

        $stmt = $this->db->prepare("SELECT id, name, email, password, is_active FROM users WHERE email = :email");
        $stmt->execute(['email' => $data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid credentials"]);
            return;
        }

        if (!$user['is_active']) {
            http_response_code(403);
            echo json_encode(["error" => "Account is inactive"]);
            return;
        }

        // Generate JWT payload
        $token = JWT::generate([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'name'    => $user['name']
        ]);

        echo json_encode([
            "message" => "Login successful",
            "token"   => $token
        ]);
    }
    /**
     * Automatically creates the 'users' table if it does not exist
     */
    public function createTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        try {
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Table creation failed: " . $e->getMessage()]);
            return false;
        }
    }

    /**
     * GET /users or GET /users?id={id}
     * Retrieve all users or a single user by ID.
     */
    public function get(?int $id = null): void
    {
        $currentUser = AuthMiddleware::authenticate();
        try {

            if ($id !== null) {
                $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch();

                if (!$user) {
                    http_response_code(404);
                    echo json_encode(["error" => "User not found"]);
                    return;
                }

                echo json_encode(["data" => $user]);
                return;
            }

            // Get all users
            $stmt = $this->db->query("SELECT * FROM users ORDER BY id DESC");
            $users = $stmt->fetchAll();

            echo json_encode(["data" => $users]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * POST /users
     * Insert a new user.
     */
    public function create(array $data): void
    {

        // Simple Validation
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["error" => "Name, email, and password are required"]);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid email format"]);
            return;
        }

        try {
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $hashedPassword,
                // enable
            ]);

            $newId = $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                "message" => "User created successfully",
                "id"      => (int) $newId
            ]);
        } catch (PDOException $e) {
            // Handle duplicate email error (MySQL error 23000)
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["error" => "Email already exists"]);
                return;
            }

            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * PUT /users?id={id}
     * Update an existing user.
     */
    public function update(int $id, array $data): void
    {

        $currentUser = AuthMiddleware::authenticate();
        if (empty($data['name']) || empty($data['email'])) {
            http_response_code(400);
            echo json_encode(["error" => "Name and email are required"]);
            return;
        }

        try {
            // Check if user exists
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE id = :id");
            $checkStmt->execute(['id' => $id]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(["error" => "User not found"]);
                return;
            }

            // Update user details
            $sql = "UPDATE users SET name = :name, email = :email WHERE id = :id";
            $params = [
                'name'  => $data['name'],
                'email' => $data['email'],
                'id'    => $id,
            ];

            // Optionally update password if provided
            if (!empty($data['password'])) {
                $sql = "UPDATE users SET name = :name, email = :email, password = :password WHERE id = :id";
                $params['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(["message" => "User updated successfully"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * DELETE /users?id={id}
     * Delete a user by ID.
     */
    public function delete(int $id): void
    {

        $currentUser = AuthMiddleware::authenticate();
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "User not found or already deleted"]);
                return;
            }

            echo json_encode(["message" => "User deleted successfully"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}
