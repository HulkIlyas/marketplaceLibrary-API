<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once '../config.php';

// Get JSON input body
$data = json_decode(file_get_contents('php://input'), true);

$name = $data['name'] ?? null;
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['message' => 'All fields are required.']);
    exit();
}

// Check for existing duplicate email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['message' => 'Email is already registered.']);
    exit();
}

// pasword strong 
if (!$name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['message' => 'All fields are required.']);
    exit();
}

// Password strength check: min 6 chars, at least 1 letter and 1 number
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['message' => 'Password must be at least 6 characters long.']);
    exit();
}

if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    echo json_encode(['message' => 'Password must contain at least one letter and one number.']);
    exit();
}

// Hash password and insert user
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$insertStmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");

if ($insertStmt->execute([$name, $email, $hashedPassword])) {
    http_response_code(201);
    echo json_encode(['message' => 'User registered successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to register user.']);
}

