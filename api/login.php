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

$stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    json_response(['error' => 'Invalid username or password'], 401);
}

session_regenerate_id(true);
$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];

json_response([
    'message' => 'Login successful',
    'user' => ['id' => $user['id'], 'username' => $user['username']],
]);
