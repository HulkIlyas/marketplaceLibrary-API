<?php
require __DIR__ . '/../config.php';

if (!empty($_SESSION['user_id'])) {
    json_response([
        'authenticated' => true,
        'user' => ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username']],
    ]);
}

json_response(['authenticated' => false], 401);
