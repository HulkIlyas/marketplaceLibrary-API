<?php

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class BookController
{
    private const MAX_IMAGES = 5;
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    private const ALLOWED_GENRES = [
        'Fiction', 'Non-fiction', 'Education', 'Technology', 'Business',
        'Self-development', 'History', 'Manga', 'Other',
    ];
    private const ALLOWED_CONDITIONS = [
        'New', 'Like New', 'Very Good', 'Good', 'Acceptable',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** GET /books or GET /books?id={id} */
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
                    $this->error('Book not found', 404);
                    return;
                }

                $books = [$book];
                $this->attachImages($books);
                echo json_encode(['data' => $books[0]]);
                return;
            }

            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 6;
            $offset = ($page - 1) * $limit;
            $whereSql = ' WHERE 1=1';
            $params = [];

            if (!empty($_GET['category'])) {
                $whereSql .= ' AND c.slug = :category';
                $params['category'] = $_GET['category'];
            }
            if (!empty($_GET['condition'])) {
                $whereSql .= ' AND b.book_condition = :condition';
                $params['condition'] = $_GET['condition'];
            }
            if (!empty($_GET['type'])) {
                $whereSql .= ' AND b.listing_type = :type';
                $params['type'] = $_GET['type'];
            }

            $countSql = 'SELECT COUNT(b.id) FROM books b LEFT JOIN categories c ON b.category_id = c.id' . $whereSql;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $totalItems = (int) $countStmt->fetchColumn();

            $stmt = $this->db->prepare("
                SELECT b.*, u.name AS owner_name, c.name AS category_name, c.slug AS category_slug
                FROM books b
                INNER JOIN users u ON b.owner_id = u.id
                LEFT JOIN categories c ON b.category_id = c.id
                {$whereSql}
                ORDER BY b.id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $books = $stmt->fetchAll();
            $this->attachImages($books);

            echo json_encode([
                'data' => $books,
                'pagination' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'total_items' => $totalItems,
                    'total_pages' => (int) ceil($totalItems / $limit),
                ],
            ]);
        } catch (PDOException $e) {
            $this->error('Unable to retrieve books', 500);
        }
    }

    /** POST /books (multipart/form-data) */
    public function create(array $data): void
    {
        $currentUser = AuthMiddleware::authenticate();
        $ownerId = (int) ($currentUser['user_id'] ?? 0);
        if ($ownerId <= 0) {
            $this->error('Unauthorized: Token does not contain a valid user ID', 401);
            return;
        }

        $validation = $this->validateListing($data);
        if (isset($validation['error'])) {
            $this->error($validation['error'], 422);
            return;
        }

        try {
            $images = $this->validateImages($_FILES['photos'] ?? null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 422);
            return;
        }

        $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'books';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $this->error('Unable to prepare the image upload directory', 500);
            return;
        }

        $movedFiles = [];
        try {
            $categoryId = $this->booksCategoryId();
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO books
                    (category_id, owner_id, title, author, isbn, genre, price, book_condition,
                     listing_type, description, city, cover_image)
                VALUES
                    (:category_id, :owner_id, :title, :author, :isbn, :genre, :price, :book_condition,
                     :listing_type, :description, :city, NULL)
            ");
            $stmt->execute([
                'category_id' => $categoryId,
                'owner_id' => $ownerId,
                'title' => $validation['title'],
                'author' => $validation['author'],
                'isbn' => $validation['isbn'],
                'genre' => $validation['genre'],
                'price' => $validation['price'],
                'book_condition' => $validation['book_condition'],
                'listing_type' => $validation['listing_type'],
                'description' => $validation['description'],
                'city' => $validation['city'],
            ]);

            $bookId = (int) $this->db->lastInsertId();
            $imageStmt = $this->db->prepare("
                INSERT INTO book_images (book_id, image_path, sort_order, is_cover)
                VALUES (:book_id, :image_path, :sort_order, :is_cover)
            ");
            $responseImages = [];

            foreach ($images as $index => $image) {
                $filename = bin2hex(random_bytes(16)) . '.' . $image['extension'];
                $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($image['tmp_name'], $absolutePath)) {
                    throw new RuntimeException('Unable to store an uploaded image');
                }
                $movedFiles[] = $absolutePath;
                $relativePath = 'uploads/books/' . $filename;

                $imageStmt->execute([
                    'book_id' => $bookId,
                    'image_path' => $relativePath,
                    'sort_order' => $index,
                    'is_cover' => $index === 0 ? 1 : 0,
                ]);
                $responseImages[] = [
                    'url' => $this->publicUrl($relativePath),
                    'is_cover' => $index === 0,
                ];
            }

            $coverPath = 'uploads/books/' . basename($movedFiles[0]);
            $coverStmt = $this->db->prepare('UPDATE books SET cover_image = :cover_image WHERE id = :id');
            $coverStmt->execute(['cover_image' => $coverPath, 'id' => $bookId]);
            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'message' => 'Book listing created successfully',
                'data' => [
                    'id' => $bookId,
                    'title' => $validation['title'],
                    'author' => $validation['author'],
                    'owner_id' => $ownerId,
                    'listing_type' => $validation['listing_type'],
                    'price' => $validation['price'],
                    'images' => $responseImages,
                ],
            ]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            foreach ($movedFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $this->error('Unable to create book listing', 500);
        }
    }

    /** GET /books/mine: ownership comes exclusively from the JWT. */
    public function mine(): void
    {
        $user = AuthMiddleware::authenticate();
        try {
            $stmt = $this->db->prepare('SELECT id, title, author, genre, book_condition, listing_type, price, city, created_at, cover_image FROM books WHERE owner_id = :owner_id ORDER BY id DESC');
            $stmt->execute(['owner_id' => (int) ($user['user_id'] ?? 0)]);
            $books = $stmt->fetchAll();
            $this->attachImages($books);
            echo json_encode(['data' => $books]);
        } catch (PDOException $e) {
            $this->error('Unable to retrieve your listings', 500);
        }
    }

    private function ownedBook(int $id, int $ownerId, string $action, bool $lock = false): ?array
    {
        $stmt = $this->db->prepare('SELECT id, owner_id, cover_image FROM books WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        $book = $stmt->fetch();
        if (!$book) {
            $this->error('Book not found', 404);
            return null;
        }
        if ((int) $book['owner_id'] !== $ownerId) {
            $this->error("You are not allowed to $action this listing", 403);
            return null;
        }
        return $book;
    }

    /** PUT /books?id={id} */
    public function update(int $id, array $data): void
    {
        $user = AuthMiddleware::authenticate();
        $ownerId = (int) ($user['user_id'] ?? 0);
        try {
            if (!$this->ownedBook($id, $ownerId, 'edit')) {
                return;
            }
            if (empty($data['title']) || empty($data['author'])) {
                $this->error('Title and author are required', 400);
                return;
            }
            $stmt = $this->db->prepare('UPDATE books SET title = :title, author = :author WHERE id = :id AND owner_id = :owner_id');
            $stmt->execute(['title' => $data['title'], 'author' => $data['author'], 'id' => $id, 'owner_id' => $ownerId]);
            echo json_encode(['message' => 'Book updated successfully']);
        } catch (PDOException $e) {
            $this->error('Unable to update book', 500);
        }
    }

    /** DELETE /books?id={id} */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $ownerId = (int) ($user['user_id'] ?? 0);
        try {
            $this->db->beginTransaction();
            $book = $this->ownedBook($id, $ownerId, 'delete', true);
            if (!$book) {
                $this->db->rollBack();
                return;
            }
            $images = $this->db->prepare('SELECT image_path FROM book_images WHERE book_id = :id');
            $images->execute(['id' => $id]);
            $paths = $images->fetchAll(PDO::FETCH_COLUMN);
            if ($book['cover_image']) $paths[] = $book['cover_image'];
            $stmt = $this->db->prepare('DELETE FROM books WHERE id = :id AND owner_id = :owner_id');
            $stmt->execute(['id' => $id, 'owner_id' => $ownerId]);
            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->error('Unable to delete book', 500);
            return;
        }
        // Files are removed only after the DB commit; failed cleanup cannot undo deletion.
        foreach (array_unique($paths) as $path) {
            $this->removeUploadedImage($path);
        }
        echo json_encode(['message' => 'Book deleted successfully']);
    }

    private function removeUploadedImage(string $path): void
    {
        if (!preg_match('~^uploads/books/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$~D', $path)) return;
        $root = realpath(dirname(__DIR__, 2) . '/public/uploads/books');
        $candidate = dirname(__DIR__, 2) . '/public/' . $path;
        if ($root === false || is_link($candidate)) return;
        $file = realpath($candidate);
        if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) return;
        try {
            // Preserve a file if another listing references it.
            $stmt = $this->db->prepare('SELECT (SELECT COUNT(*) FROM book_images WHERE image_path = ?) + (SELECT COUNT(*) FROM books WHERE cover_image = ?)');
            $stmt->execute([$path, $path]);
            if ((int) $stmt->fetchColumn() === 0 && !@unlink($file)) {
                error_log('Unable to clean up deleted listing image: ' . $path);
            }
        } catch (PDOException $e) {
            error_log('Unable to check deleted listing image references');
        }
    }

    private function validateListing(array $data): array
    {
        foreach ([
            'title' => 'Title is required',
            'author' => 'Author is required',
            'description' => 'Description is required',
        ] as $field => $message) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return ['error' => $message];
            }
        }

        // The current UI calls this field "category"; it is stored separately as a book genre.
        $genre = trim((string) ($data['genre'] ?? $data['category'] ?? ''));
        if ($genre === '') {
            return ['error' => 'Genre is required'];
        }
        if (!in_array($genre, self::ALLOWED_GENRES, true)) {
            return ['error' => 'Invalid genre'];
        }

        $condition = trim((string) ($data['book_condition'] ?? $data['condition'] ?? ''));
        if ($condition === '') {
            return ['error' => 'Book condition is required'];
        }
        if (!in_array($condition, self::ALLOWED_CONDITIONS, true)) {
            return ['error' => 'Invalid book condition'];
        }

        $listingType = $this->normalizeListingType((string) ($data['listing_type'] ?? ''));
        if ($listingType === null) {
            return ['error' => 'Listing type must be Sell, Exchange, or Sell or Exchange'];
        }

        $price = trim((string) ($data['price'] ?? ''));
        if ($listingType === 'EXCHANGE') {
            $normalizedPrice = '0.00';
        } else {
            if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
                return ['error' => 'Price must be greater than 0 for sell listings'];
            }
            if ((float) $price > 99999999.99) {
                return ['error' => 'Price is too large'];
            }
            $normalizedPrice = number_format((float) $price, 2, '.', '');
        }

        $title = trim((string) $data['title']);
        $author = trim((string) $data['author']);
        $description = trim((string) $data['description']);
        $isbn = $this->nullableString($data['isbn'] ?? null);
        $city = $this->nullableString($data['city'] ?? null);

        if (mb_strlen($title) > 255) {
            return ['error' => 'Title must not exceed 255 characters'];
        }
        if (mb_strlen($author) > 150) {
            return ['error' => 'Author must not exceed 150 characters'];
        }
        if (mb_strlen($description) > 1000) {
            return ['error' => 'Description must not exceed 1000 characters'];
        }
        if ($isbn !== null && mb_strlen($isbn) > 20) {
            return ['error' => 'ISBN must not exceed 20 characters'];
        }
        if ($city !== null && mb_strlen($city) > 100) {
            return ['error' => 'City must not exceed 100 characters'];
        }

        return [
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'genre' => $genre,
            'book_condition' => $condition,
            'listing_type' => $listingType,
            'price' => $normalizedPrice,
            'description' => $description,
            'city' => $city,
        ];
    }

    private function normalizeListingType(string $value): ?string
    {
        $key = strtoupper(str_replace([' ', '-'], '_', trim($value)));
        return match ($key) {
            'SELL' => 'SELL',
            'EXCHANGE' => 'EXCHANGE',
            'SELL_OR_EXCHANGE' => 'SELL_OR_EXCHANGE',
            default => null,
        };
    }

    private function validateImages(?array $files): array
    {
        if ($files === null || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])) {
            throw new RuntimeException('At least one photo is required');
        }

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
        if (count($names) > self::MAX_IMAGES) {
            throw new RuntimeException('A maximum of 5 photos is allowed');
        }

        $validated = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ($names as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                throw new RuntimeException('Each photo must be 5 MB or smaller');
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('A photo could not be uploaded');
            }

            $size = (int) ($sizes[$index] ?? 0);
            if ($size <= 0 || $size > self::MAX_IMAGE_SIZE) {
                throw new RuntimeException('Each photo must be 5 MB or smaller');
            }

            $tmpName = (string) ($tmpNames[$index] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('A photo upload is invalid');
            }

            $mime = $finfo->file($tmpName);
            if (!is_string($mime) || !isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
                throw new RuntimeException('Photos must be JPEG, PNG, or WebP images');
            }
            $validated[] = [
                'tmp_name' => $tmpName,
                'extension' => self::ALLOWED_IMAGE_TYPES[$mime],
            ];
        }

        if ($validated === []) {
            throw new RuntimeException('At least one photo is required');
        }
        return $validated;
    }

    private function booksCategoryId(): int
    {
        $stmt = $this->db->query("SELECT id FROM categories WHERE slug = 'books' LIMIT 1");
        $categoryId = (int) $stmt->fetchColumn();
        if ($categoryId <= 0) {
            throw new RuntimeException("The marketplace 'Books' category is not configured");
        }
        return $categoryId;
    }

    private function attachImages(array &$books): void
    {
        if ($books === []) {
            return;
        }
        $ids = array_map(static fn(array $book): int => (int) $book['id'], $books);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT book_id, image_path, sort_order, is_cover
            FROM book_images
            WHERE book_id IN ($placeholders)
            ORDER BY book_id, sort_order
        ");
        $stmt->execute($ids);

        $imagesByBook = [];
        foreach ($stmt->fetchAll() as $image) {
            $imagesByBook[(int) $image['book_id']][] = [
                'url' => $this->publicUrl($image['image_path']),
                'is_cover' => (bool) $image['is_cover'],
            ];
        }
        foreach ($books as &$book) {
            $book['images'] = $imagesByBook[(int) $book['id']] ?? [];
        }
        unset($book);
    }

    private function publicUrl(string $relativePath): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $basePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
        return $scheme . '://' . $host . $basePath . '/public/' . ltrim($relativePath, '/');
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }

    private function error(string $message, int $status): void
    {
        http_response_code($status);
        echo json_encode(['error' => $message]);
    }
}
