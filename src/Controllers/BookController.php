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
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare("
                SELECT b.*, u.name AS owner_name, c.name AS category_name, c.slug AS category_slug 
                FROM books b 
                INNER JOIN users u ON b.owner_id = u.id 
                LEFT JOIN categories c ON b.category_id = c.id 
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

            // Pagination parameters
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 6;
            $offset = ($page - 1) * $limit;

            // Base query conditions
            $whereSql = " WHERE 1=1";
            $params = [];

            if (!empty($_GET['category'])) {
                $whereSql .= " AND c.slug = :category";
                $params['category'] = $_GET['category'];
            }

            if (!empty($_GET['condition'])) {
                $whereSql .= " AND b.book_condition = :condition";
                $params['condition'] = $_GET['condition'];
            }

            if (!empty($_GET['type'])) {
                $whereSql .= " AND b.listing_type = :type";
                $params['type'] = $_GET['type'];
            }

            // 1. Get Total Count
            $countSql = "SELECT COUNT(b.id) FROM books b LEFT JOIN categories c ON b.category_id = c.id" . $whereSql;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = (int)$countStmt->fetchColumn();

            // 2. Fetch Paginated Records
            $sql = "
            SELECT b.*, u.name AS owner_name, c.name AS category_name, c.slug AS category_slug 
            FROM books b 
            INNER JOIN users u ON b.owner_id = u.id 
            LEFT JOIN categories c ON b.category_id = c.id 
            {$whereSql}
            ORDER BY b.id DESC
            LIMIT :limit OFFSET :offset
        ";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $val) {
                $stmt->bindValue(":$key", $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $books = $stmt->fetchAll();

            $totalPages = ceil($totalItems / $limit);

            echo json_encode([
                "data" => $books,
                "pagination" => [
                    "current_page" => $page,
                    "limit"        => $limit,
                    "total_items"  => $totalItems,
                    "total_pages"  => $totalPages
                ]
            ]);
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
