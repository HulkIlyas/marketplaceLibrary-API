<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    json_response(['error' => 'All fields are required'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Invalid email address'], 422);
}

if (strlen($password) < 6) {
    json_response(['error' => 'Password must be at least 6 characters'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    json_response(['error' => 'Email already exists'], 409);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
);

$stmt->execute([
    $name,
    $email,
    $hashed
]);

json_response(['message' => 'Account created successfully'], 201);
