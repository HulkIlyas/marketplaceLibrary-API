<?php

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use PDO;
use PDOException;

class BookController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * GET /books or GET /books?id={id}
     */
    public function get(?int $id = null): void
    {

        $currentUser = AuthMiddleware::authenticate();
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare("
                    SELECT b.id, b.title, b.author, b.owner_id, u.name as owner_name, b.created_at 
                    FROM books b 
                    JOIN users u ON b.owner_id = u.id 
                    WHERE b.id = :id
                ");
                $stmt->execute(['id' => $id]);
                $book = $stmt->fetch();

                if (!$book) {
                    http_response_code(404);
                    echo json_encode(["error" => "Book not found"]);
                    return;
                }

                echo json_encode(["data" => $book]);
                return;
            }

            // Get all books
            $stmt = $this->db->query("
                SELECT b.id, b.title, b.author, b.owner_id, u.name as owner_name, b.created_at 
                FROM books b 
                JOIN users u ON b.owner_id = u.id 
                ORDER BY b.id DESC
            ");
            $books = $stmt->fetchAll();

            echo json_encode(["data" => $books]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * POST /books
     */
    public function create(array $data): void
    {

        $currentUser = AuthMiddleware::authenticate();
        if (empty($data['title']) || empty($data['author']) || empty($data['owner_id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Title, author, and owner_id are required"]);
            return;
        }

        try {
            $sql = "INSERT INTO books (title, author, owner_id) VALUES (:title, :author, :owner_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'title'    => $data['title'],
                'author'   => $data['author'],
                'owner_id' => $data['owner_id'],
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "Book created successfully",
                "id"      => (int) $this->db->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * PUT /books?id={id}
     */
    public function update(int $id, array $data): void
    {

        $currentUser = AuthMiddleware::authenticate();
        if (empty($data['title']) || empty($data['author'])) {
            http_response_code(400);
            echo json_encode(["error" => "Title and author are required"]);
            return;
        }

        try {
            $sql = "UPDATE books SET title = :title, author = :author WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'title'  => $data['title'],
                'author' => $data['author'],
                'id'     => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Book not found or no changes made"]);
                return;
            }

            echo json_encode(["message" => "Book updated successfully"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * DELETE /books?id={id}
     */
    public function delete(int $id): void
    {

        $currentUser = AuthMiddleware::authenticate();
        try {
            $stmt = $this->db->prepare("DELETE FROM books WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Book not found"]);
                return;
            }

            echo json_encode(["message" => "Book deleted successfully"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}
