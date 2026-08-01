<?php
/**
 * config.php
 * Shared setup for every API endpoint: CORS, session, DB connection,
 * and small JSON response helpers.
 */

// ---- CORS -------------------------------------------------------------
// Allow the frontend (served from a different port/origin) to call this
// API and send cookies (needed for PHP sessions to work cross-origin).
$allowedOrigins = [
    'http://localhost:5500',   // e.g. VS Code "Live Server"
    'http://127.0.0.1:5500',
    'http://localhost:3000',
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Preflight requests end here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Session ------------------------------------------------------------
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax', // use 'None' + secure=true if frontend/backend are on different domains over HTTPS
]);
session_start();

// ---- Database -----------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'marketplace_library');
define('DB_USER', 'root');
define('DB_PASS', 'ilyas');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    json_response(['error' => 'Database connection failed'], 500);
}

// ---- Helpers --------------------------------------------------------------
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_json_body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
