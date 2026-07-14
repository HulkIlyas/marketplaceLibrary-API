<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '' || $password === '') {
    json_response(['error' => 'Username and password are required'], 422);
}

if (strlen($password) < 6) {
    json_response(['error' => 'Password must be at least 6 characters'], 422);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    json_response(['error' => 'Username already taken'], 409);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->execute([$username, $hashed]);

json_response(['message' => 'Account created successfully'], 201);
