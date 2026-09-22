<?php

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use PDO;
use PDOException;

class CategoryController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Automatically creates the 'categories' table if it does not exist
     */
    public function createTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            icon VARCHAR(255) NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
     * GET /categories or GET /categories?id={id}
     * Retrieve all categories or a single category by ID.
     */
    public function get(?int $id = null): void
    {
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $category = $stmt->fetch();

                if (!$category) {
                    http_response_code(404);
                    echo json_encode(["error" => "Category not found"]);
                    return;
                }

                echo json_encode(["data" => $category]);
                return;
            }

            // Get all categories
            $stmt = $this->db->query("SELECT * FROM categories ORDER BY id ASC");
            $categories = $stmt->fetchAll();

            echo json_encode(["data" => $categories]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * POST /categories
     * Insert a new category.
     */
    public function create(array $data): void
    {
        $currentUser = AuthMiddleware::authenticate();

        if (empty($data['name']) || empty($data['slug'])) {
            http_response_code(400);
            echo json_encode(["error" => "Category name and slug are required"]);
            return;
        }

        try {
            $sql = "INSERT INTO categories (name, icon, slug) VALUES (:name, :icon, :slug)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'icon' => $data['icon'] ?? null,
                'slug' => $data['slug']
            ]);

            $newId = $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                "message" => "Category created successfully",
                "id"      => (int) $newId
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["error" => "Category slug already exists"]);
                return;
            }

            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * PUT /categories?id={id}
     * Update an existing category.
     */
    public function update(int $id, array $data): void
    {
        $currentUser = AuthMiddleware::authenticate();

        if (empty($data['name']) || empty($data['slug'])) {
            http_response_code(400);
            echo json_encode(["error" => "Category name and slug are required"]);
            return;
        }

        try {
            // Check if category exists
            $checkStmt = $this->db->prepare("SELECT id FROM categories WHERE id = :id");
            $checkStmt->execute(['id' => $id]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(["error" => "Category not found"]);
                return;
            }

            // Update category details
            $sql = "UPDATE categories SET name = :name, icon = :icon, slug = :slug WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'icon' => $data['icon'] ?? null,
                'slug' => $data['slug'],
                'id'   => $id,
            ]);

            echo json_encode(["message" => "Category updated successfully"]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["error" => "Category slug already exists"]);
                return;
            }

            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * DELETE /categories?id={id}
     * Delete a category by ID.
     */
    public function delete(int $id): void
    {
        $currentUser = AuthMiddleware::authenticate();

        try {
            $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Category not found or already deleted"]);
                return;
            }

            echo json_encode(["message" => "Category deleted successfully"]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["error" => "Cannot delete category because it contains books"]);
                return;
            }

            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}
