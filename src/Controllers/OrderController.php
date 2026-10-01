<?php
namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use PDO;
use Throwable;

class OrderController
{
    private PDO $db;
    public function __construct() { $this->db = Database::getConnection(); }
    private function reply(array $body, int $status = 200): void {
        http_response_code($status);
        echo json_encode($body);
    }
    private function fail(string $code, int $status): void {
        if ($this->db->inTransaction()) $this->db->rollBack();
        $this->reply(['error' => $code, 'code' => $code], $status);
    }
    public function create(array $data): void {
        $buyer = (int) AuthMiddleware::authenticate()['user_id'];
        $ids = $data['book_ids'] ?? null;
        if (!is_array($ids) || !$ids || count($ids) > 100) { $this->fail('invalidBooks', 422); return; }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) { $this->fail('invalidBooks', 422); return; }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC); // Consistent lock order for overlapping multi-book orders.
        $contact = [];
        foreach (['full_name'=>100,'email'=>255,'address'=>500,'city'=>100,'postal_code'=>30] as $field=>$max) {
            if (!isset($data[$field]) || !is_string($data[$field])) { $this->fail('invalidContact',422); return; }
            $contact[$field] = trim($data[$field]);
            if ($contact[$field] === '' || mb_strlen($contact[$field]) > $max) { $this->fail('invalidContact',422); return; }
        }
        if (!filter_var($contact['email'],FILTER_VALIDATE_EMAIL)) { $this->fail('invalidContact',422); return; }
        try {
            $this->db->beginTransaction();
            $books = [];
            $query = $this->db->prepare('SELECT * FROM books WHERE id=? FOR UPDATE');
            foreach ($ids as $id) {
                $query->execute([$id]);
                $book = $query->fetch();
                if (!$book) { $this->fail('bookMissing',404); return; }
                if ((int)$book['owner_id'] === $buyer) { $this->fail('ownListing',422); return; }
                if (!in_array($book['listing_type'],['SELL','SELL_OR_EXCHANGE'],true)) { $this->fail('notForSale',422); return; }
                if ($book['listing_status'] !== 'ACTIVE') { $this->fail('unavailable',409); return; }
                if ((float)$book['price'] <= 0) { $this->fail('invalidPrice',422); return; }
                $books[] = $book;
            }
            $insert = $this->db->prepare('INSERT INTO orders (buyer_id,full_name,email,address,city,postal_code) VALUES (?,?,?,?,?,?)');
            $insert->execute([$buyer,...array_values($contact)]);
            $orderId = (int)$this->db->lastInsertId();
            $item = $this->db->prepare('INSERT INTO order_items (order_id,book_id,seller_id,title,price) VALUES (?,?,?,?,?)');
            $reserve = $this->db->prepare("UPDATE books SET listing_status='RESERVED' WHERE id=?");
            foreach ($books as $book) {
                $item->execute([$orderId,$book['id'],$book['owner_id'],$book['title'],$book['price']]);
                $reserve->execute([$book['id']]);
            }
            $result = $this->buyerOrders($buyer, $orderId)[0];
            $this->db->commit();
            $this->reply(['data'=>$result],201);
        } catch (Throwable $e) { $this->fail('orderFailed',500); }
    }
    private function buyerOrders(int $buyer, ?int $id=null): array {
        $query = $this->db->prepare('SELECT * FROM orders WHERE buyer_id=?'.($id ? ' AND id=?' : '').' ORDER BY id DESC');
        $query->execute($id ? [$buyer,$id] : [$buyer]);
        $orders = $query->fetchAll();
        $items = $this->db->prepare('SELECT i.*, u.name AS seller_name FROM order_items i JOIN users u ON u.id=i.seller_id WHERE i.order_id=? ORDER BY i.id');
        foreach ($orders as &$order) {
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        return $orders;
    }
    public function mine(): void {
        $buyer = (int)AuthMiddleware::authenticate()['user_id'];
        try { $this->reply(['data'=>$this->buyerOrders($buyer)]); }
        catch (Throwable $e) { $this->fail('loadFailed',500); }
    }
    public function seller(): void {
        $seller = (int)AuthMiddleware::authenticate()['user_id'];
        try {
            $q = $this->db->prepare("SELECT i.*, o.full_name AS buyer_name, o.city,
                CASE WHEN i.status IN ('ACCEPTED','IN_PROGRESS') THEN o.address ELSE NULL END AS address,
                CASE WHEN i.status IN ('ACCEPTED','IN_PROGRESS') THEN o.postal_code ELSE NULL END AS postal_code,
                b.cover_image
                FROM order_items i JOIN orders o ON o.id=i.order_id JOIN books b ON b.id=i.book_id
                WHERE i.seller_id=? ORDER BY i.id DESC");
            $q->execute([$seller]);
            $rows = $q->fetchAll();
            foreach ($rows as &$row) {
                if ($row['cover_image']) {
                    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
                    $row['cover_url'] = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').'/public/'.ltrim($row['cover_image'],'/');
                } else { $row['cover_url'] = null; }
                unset($row['cover_image']);
            }
            $this->reply(['data'=>$rows]);
        } catch (Throwable $e) { $this->fail('loadFailed',500); }
    }
    public function status(string $id, array $data): void {
        $seller = (int)AuthMiddleware::authenticate()['user_id'];
        if (!ctype_digit($id) || (int)$id <= 0) { $this->fail('itemMissing',404); return; }
        $next = $data['status'] ?? null;
        $allowed = ['PENDING'=>['ACCEPTED','DECLINED'],'ACCEPTED'=>['IN_PROGRESS'],'IN_PROGRESS'=>['DELIVERED']];
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('SELECT * FROM order_items WHERE id=? FOR UPDATE');
            $q->execute([$id]); $item = $q->fetch();
            if (!$item) { $this->fail('itemMissing',404); return; }
            if ((int)$item['seller_id'] !== $seller) { $this->fail('forbidden',403); return; }
            if (!is_string($next) || !in_array($next,$allowed[$item['status']] ?? [],true)) { $this->fail('invalidTransition',409); return; }
            $q = $this->db->prepare('UPDATE order_items SET status=? WHERE id=?');
            $q->execute([$next,$id]);
            if (in_array($next,['DECLINED','DELIVERED'],true)) {
                $q = $this->db->prepare("UPDATE books SET listing_status=? WHERE id=? AND listing_status='RESERVED'");
                $q->execute([$next === 'DECLINED' ? 'ACTIVE' : 'SOLD',$item['book_id']]);
            }
            $this->db->commit();
            $item['status'] = $next;
            $this->reply(['data'=>$item]);
        } catch (Throwable $e) { $this->fail('updateFailed',500); }
    }
}
