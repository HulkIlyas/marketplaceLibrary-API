<?php
// 1. Get origin dynamically from request headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8001';

// 2. Send dynamic CORS headers
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// 3. Immediately exit for preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/autoloader.php';

// Parse Request Method & URI Path
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$path   = parse_url($rawUri, PHP_URL_PATH) ?? '/';

// Normalize path: trim extra slashes (e.g., "/login/" becomes "/login")
$normalizedPath = '/' . trim($path, '/');

// Parse incoming request body
$inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

// 1. Explicit Custom Routes (BEFORE dynamic controller lookup)
if (($normalizedPath === '/login' || $normalizedPath === '/users/login') && $method === 'POST') {
    $controller = new \App\Controllers\UserController();
    $controller->login($inputData);
    exit;
}

// 2. Clean up path slashes to extract the resource
$uriSegments = array_values(array_filter(explode('/', trim($path, '/'))));
$resource    = end($uriSegments);
// $resource = substr($resource, 22);

if (
    empty($resource) || $resource === 'index.php'
    || $resource === ''
) {
    http_response_code(200);
    echo json_encode(["message" => "API is running"]);
    exit;
}

// 3. Map URI resource (e.g. "users") to Controller Class (e.g. "UserController")


$singularResource = (substr($resource, -1) === 's') ? substr($resource, 0, -1) : $resource;
$controllerName   = ucfirst($singularResource) . 'Controller';

$controllerClass  = "App\\Controllers\\" . $controllerName;

// Check if Controller Class exists
if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo json_encode(["error" => "Resource '$resource' not found"]);
    exit;
}

// Instantiate Controller
$controller = new $controllerClass();

// Extract ID from query parameters (?id=1)
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// 4. Dispatch request to controller action
switch ($method) {
    case 'GET':
        $controller->get($id);
        break;

    case 'POST':
        $controller->create($inputData);
        break;

    case 'PUT':
    case 'PATCH':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required parameter: ?id="]);
            exit;
        }
        $controller->update($id, $inputData);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required parameter: ?id="]);
            exit;
        }
        $controller->delete($id);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method Not Allowed"]);
        break;
}
