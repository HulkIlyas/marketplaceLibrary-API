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
        // Fetch user IDs dynamically
        $stmtUsers = $this->db->query("SELECT id FROM users");
        $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

        // Fetch category IDs dynamically
        $stmtCategories = $this->db->query("SELECT id FROM categories");
        $categoryIds = $stmtCategories->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userIds) || empty($categoryIds)) {
            echo "Error: Users and Categories must be seeded before Books.\n";
            return;
        }

        // Reference books list across various categories
        $bookTemplates = [
            // Non-Fiction & Self-Help
            ['title' => 'Atomic Habits', 'author' => 'James Clear'],
            ['title' => 'The Psychology of Money', 'author' => 'Morgan Housel'],
            ['title' => 'Deep Work', 'author' => 'Cal Newport'],
            ['title' => 'Thinking, Fast and Slow', 'author' => 'Daniel Kahneman'],
            ['title' => 'Rich Dad Poor Dad', 'author' => 'Robert T. Kiyosaki'],
            ['title' => 'Can\'t Hurt Me', 'author' => 'David Goggins'],
            ['title' => 'Essentialism', 'author' => 'Greg McKeown'],
            ['title' => 'Ego Is the Enemy', 'author' => 'Ryan Holiday'],
            ['title' => 'Outliers', 'author' => 'Malcolm Gladwell'],
            ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari'],

            // Tech & Software Development
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin'],
            ['title' => 'The Pragmatic Programmer', 'author' => 'Andrew Hunt'],
            ['title' => 'Design Patterns', 'author' => 'Erich Gamma'],
            ['title' => 'Refactoring', 'author' => 'Martin Fowler'],
            ['title' => 'You Don\'t Know JS', 'author' => 'Kyle Simpson'],
            ['title' => 'Designing Data-Intensive Applications', 'author' => 'Martin Kleppmann'],
            ['title' => 'Code Complete', 'author' => 'Steve McConnell'],
            ['title' => 'Introduction to Algorithms', 'author' => 'Thomas H. Cormen'],

            // Manga & Light Novels
            ['title' => 'Attack on Titan Vol. 1', 'author' => 'Hajime Isayama'],
            ['title' => 'Demon Slayer Vol. 1', 'author' => 'Koyoharu Gotouge'],
            ['title' => 'One Piece Vol. 1', 'author' => 'Eiichiro Oda'],
            ['title' => 'Jujutsu Kaisen Vol. 1', 'author' => 'Gege Akutami'],
            ['title' => 'Chainsaw Man Vol. 1', 'author' => 'Tatsuki Fujimoto'],
            ['title' => 'Death Note Vol. 1', 'author' => 'Tsugumi Ohba'],
            ['title' => 'Berserk Deluxe Edition 1', 'author' => 'Kentaro Miura'],
            ['title' => 'Tokyo Ghoul Vol. 1', 'author' => 'Sui Ishida'],

            // Fiction & Classics
            ['title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald'],
            ['title' => '1984', 'author' => 'George Orwell'],
            ['title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee'],
            ['title' => 'The Hobbit', 'author' => 'J.R.R. Tolkien'],
            ['title' => 'Dune', 'author' => 'Frank Herbert'],
            ['title' => 'Fahrenheit 451', 'author' => 'Ray Bradbury'],
            ['title' => 'The Alchemist', 'author' => 'Paulo Coelho'],
            ['title' => 'Crime and Punishment', 'author' => 'Fyodor Dostoevsky']
        ];

        $conditions = ['Like new', 'Very good', 'Good condition'];
        $listingTypes = ['BUY', 'SELL', 'EXCHANGE'];
        $colors = ['#d4a359', '#3b6e8c', '#2b3a4a', '#a84332', '#4a7c59', '#6c5ce7', '#e17055', '#00b894'];

        $sql = "INSERT INTO books 
            (category_id, owner_id, title, author, price, book_condition, listing_type, edition, cover_image, cover_color) 
            VALUES 
            (:category_id, :owner_id, :title, :author, :price, :book_condition, :listing_type, :edition, :cover_image, :cover_color)";

        $stmt = $this->db->prepare($sql);

        // Target generating 150 items
        $totalRecords = 150;
        $templateCount = count($bookTemplates);

        for ($i = 0; $i < $totalRecords; $i++) {
            $template = $bookTemplates[$i % $templateCount];

            // Append a volume or edition suffix after the first cycle to ensure unique entries
            $suffix = $i >= $templateCount ? " (Vol. " . ceil(($i + 1) / $templateCount) . ")" : "";
            $title = $template['title'] . $suffix;

            // Random or calculated attributes
            $categoryId   = $categoryIds[$i % count($categoryIds)];
            $ownerId      = $userIds[$i % count($userIds)];
            $price        = rand(60, 350); // Generates price between 60 MAD and 350 MAD
            $condition    = $conditions[$i % count($conditions)];
            $listingType  = $listingTypes[$i % count($listingTypes)];
            $coverColor   = $colors[$i % count($colors)];

            $stmt->execute([
                'category_id'    => $categoryId,
                'owner_id'       => $ownerId,
                'title'          => $title,
                'author'         => $template['author'],
                'price'          => sprintf('%.2f', $price),
                'book_condition' => $condition,
                'listing_type'   => $listingType,
                'edition'        => 'MARKETPLACE EDITION',
                'cover_image'    => null,
                'cover_color'    => $coverColor
            ]);
        }

        echo "150 books seeded successfully!\n";
    }
}
