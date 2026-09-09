<?php

require_once __DIR__ . '/autoloader.php';

use App\Config\DatabaseSeeder;

echo "Starting Database Seeding...\n";

$seeder = new DatabaseSeeder();
$seeder->run();