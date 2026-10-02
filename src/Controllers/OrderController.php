<?php
namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use PDO;
use PDOException;

class OrderController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * POST /orders
     * Create a new order with order items using a transaction.
     */
    public function create(array $data): void
    {
        // Authenticate request (Optional: allow guest checkout by removing or making conditional)
        $currentUser = AuthMiddleware::authenticate();
        $userId = $currentUser['user_id'] ?? null;

        // Validation
        if (empty($data['full_name']) || empty($data['email']) || empty($data['address']) || empty($data['city'])) {
            http_response_code(400);
            echo json_encode(["error" => "Full name, email, address, and city are required."]);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid email format."]);
            return;
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            http_response_code(400);
            echo json_encode(["error" => "Order items are required."]);
            return;
        }

        $deliveryFee = 30.00;
        $subtotal = 0;

        foreach ($data['items'] as $item) {
            if (empty($item['book_id']) || empty($item['title']) || !isset($item['price'])) {
                http_response_code(400);
                echo json_encode(["error" => "Each item must have a book_id, title, and price."]);
                return;
            }
            $subtotal += (float) $item['price'];
        }

        $grandTotal = $subtotal + $deliveryFee;

        try {
            // Begin Transaction
            $this->db->beginTransaction();

            // 1. Insert order header
            $sqlOrder = "INSERT INTO orders (user_id, full_name, email, address, city, postal_code, total_amount)
                         VALUES (:user_id, :full_name, :email, :address, :city, :postal_code, :total_amount)";
            
            $stmtOrder = $this->db->prepare($sqlOrder);
            $stmtOrder->execute([
                ':user_id'      => $userId,
                ':full_name'    => $data['full_name'],
                ':email'        => $data['email'],
                ':address'      => $data['address'],
                ':city'         => $data['city'],
                ':postal_code'  => $data['postal_code'] ?? null,
                ':total_amount' => $grandTotal
            ]);

            $orderId = (int) $this->db->lastInsertId();

            // 2. Insert line items
            $sqlItem = "INSERT INTO order_items (order_id, book_id, title, price)
                        VALUES (:order_id, :book_id, :title, :price)";
            $stmtItem = $this->db->prepare($sqlItem);

            foreach ($data['items'] as $item) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':book_id'  => $item['book_id'],
                    ':title'    => $item['title'],
                    ':price'    => $item['price']
                ]);
            }

            // Commit transaction
            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                "message"  => "Order placed successfully",
                "order_id" => $orderId,
                "total"    => $grandTotal
            ]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            http_response_code(500);
            echo json_encode(["error" => "Order processing failed: " . $e->getMessage()]);
        }
    }

    /**
     * GET /orders or GET /orders?id={id}
     * Retrieve order history or a single order with its items.
     */
    public function get(?int $id = null): void
    {
        $currentUser = AuthMiddleware::authenticate();
        $userId = $currentUser['user_id'];

        try {
            // Single Order Detail
            if ($id !== null) {
                $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id");
                $stmt->execute(['id' => $id, 'user_id' => $userId]);
                $order = $stmt->fetch();

                if (!$order) {
                    http_response_code(404);
                    echo json_encode(["error" => "Order not found"]);
                    return;
                }

                // Fetch line items for this order
                $itemStmt = $this->db->prepare("SELECT id, book_id, title, price FROM order_items WHERE order_id = :order_id");
                $itemStmt->execute(['order_id' => $id]);
                $order['items'] = $itemStmt->fetchAll();

                echo json_encode(["data" => $order]);
                return;
            }

            // List all orders for authenticated user
            $stmt = $this->db->prepare("
                SELECT o.*, COUNT(oi.id) AS total_items 
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = :user_id
                GROUP BY o.id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $orders = $stmt->fetchAll();

            echo json_encode(["data" => $orders]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }

    /**
     * PUT /orders/status?id={id}
     * Update order status (Admin function).
     */
    public function updateStatus(int $id, array $data): void
    {
        $currentUser = AuthMiddleware::authenticate();

        if (empty($data['status'])) {
            http_response_code(400);
            echo json_encode(["error" => "Status is required"]);
            return;
        }

        $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($data['status'], $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid status value"]);
            return;
        }

        try {
            $stmt = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $data['status'], 'id' => $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Order not found or status unchanged"]);
                return;
            }

            echo json_encode(["message" => "Order status updated successfully"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}
