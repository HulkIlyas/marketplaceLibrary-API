<?php

// ============================================================
// 1. CORS
// ============================================================

$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8001';

header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/autoloader.php';


// ============================================================
// 2. Request information
// ============================================================

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';

$path = parse_url($rawUri, PHP_URL_PATH) ?? '/';

$normalizedPath = '/' . trim($path, '/');


// ============================================================
// 3. Request body
// ============================================================

$inputData = json_decode(
    file_get_contents('php://input'),
    true
) ?? $_POST ?? [];


// ============================================================
// 4. CUSTOM ROUTES
// ============================================================
//
// Add as many custom routes as you want here.
//
// Examples:
//
// /login
// /users/me
// /users/{id}/orders
// /customers/{id}/transactions
//
// {id} is automatically extracted and passed to the controller.
//

$routes = [

    [
        'method'     => 'POST',
        'path'       => '/login',
        'controller' => \App\Controllers\UserController::class,
        'action'     => 'login',
    ],

    [
        'method'     => 'POST',
        'path'       => '/users/login',
        'controller' => \App\Controllers\UserController::class,
        'action'     => 'login',
    ],



];


// ============================================================
// 5. Route matcher
// ============================================================

function matchRoute(string $pattern, string $path): ?array
{
    $patternParts = array_values(
        array_filter(
            explode('/', trim($pattern, '/'))
        )
    );

    $pathParts = array_values(
        array_filter(
            explode('/', trim($path, '/'))
        )
    );

    // Different number of segments = no match
    if (count($patternParts) !== count($pathParts)) {
        return null;
    }

    $params = [];

    foreach ($patternParts as $index => $patternPart) {

        // Dynamic parameter: {id}, {userId}, {customerId}, etc.
        if (
            strlen($patternPart) >= 2 &&
            $patternPart[0] === '{' &&
            $patternPart[strlen($patternPart) - 1] === '}'
        ) {
            $parameterName = substr(
                $patternPart,
                1,
                -1
            );

            $params[$parameterName] = $pathParts[$index];

            continue;
        }

        // Static path must match exactly
        if ($patternPart !== $pathParts[$index]) {
            return null;
        }
    }

    return $params;
}


// ============================================================
// 6. Check custom routes FIRST
// ============================================================

foreach ($routes as $route) {

    if ($method !== $route['method']) {
        continue;
    }

    $params = matchRoute(
        $route['path'],
        $normalizedPath
    );

    if ($params === null) {
        continue;
    }

    // Check controller
    if (!class_exists($route['controller'])) {
        http_response_code(500);

        echo json_encode([
            "error" => "Controller not found"
        ]);

        exit;
    }

    $controller = new $route['controller']();

    // Check action
    if (!method_exists($controller, $route['action'])) {
        http_response_code(500);

        echo json_encode([
            "error" => "Controller action not found"
        ]);

        exit;
    }

    /*
     * Build arguments.
     *
     * Example:
     *
     * /users/25/orders
     *
     * params:
     *
     * [
     *     'id' => '25'
     * ]
     *
     * becomes:
     *
     * $controller->getByUser('25');
     */

    $arguments = array_values($params);

    // For POST/PUT/PATCH/DELETE custom routes,
    // you may want the body as the last argument.
    if (
        in_array(
            $method,
            ['POST', 'PUT', 'PATCH'],
            true
        )
    ) {
        $arguments[] = $inputData;
    }

    $controller->{$route['action']}(...$arguments);

    exit;
}


// ============================================================
// 7. Root API
// ============================================================

if (
    empty($normalizedPath) ||
    $normalizedPath === '/' ||
    $normalizedPath === '/index.php'
) {
    http_response_code(200);

    echo json_encode([
        "message" => "API is running"
    ]);

    exit;
}


// ============================================================
// 8. Dynamic Controller Routes
// ============================================================
//
// Examples:
//
// GET    /users
// GET    /users/123       (using ?id=123 currently)
// POST   /users
// PUT    /users?id=123
// DELETE /users?id=123
//
// Your existing dynamic controller system is preserved.
//

$uriSegments = array_values(
    array_filter(
        explode('/', trim($path, '/'))
    )
);

$resource = end($uriSegments);


// ============================================================
// 9. Resource validation
// ============================================================

if (
    empty($resource) ||
    $resource === 'index.php'
) {
    http_response_code(200);

    echo json_encode([
        "message" => "API is running"
    ]);

    exit;
}


// ============================================================
// 10. Resource → Controller
// ============================================================
//
// users      → UserController
// customers  → CustomerController
// products   → ProductController
//

$singularResource =
    (substr($resource, -1) === 's')
    ? substr($resource, 0, -1)
    : $resource;

$controllerName =
    ucfirst($singularResource) . 'Controller';

$controllerClass =
    "App\\Controllers\\" . $controllerName;


// ============================================================
// 11. Check Controller
// ============================================================

if (!class_exists($controllerClass)) {

    http_response_code(404);

    echo json_encode([
        "error" => "Resource '$resource' not found"
    ]);

    exit;
}


// ============================================================
// 12. Instantiate Controller
// ============================================================

$controller = new $controllerClass();


// ============================================================
// 13. Existing ?id=123 support
// ============================================================

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : null;


// ============================================================
// 14. Dynamic Controller Dispatch
// ============================================================

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

            echo json_encode([
                "error" => "Missing required parameter: ?id="
            ]);

            exit;
        }

        $controller->update(
            $id,
            $inputData
        );

        break;


    case 'DELETE':

        if (!$id) {

            http_response_code(400);

            echo json_encode([
                "error" => "Missing required parameter: ?id="
            ]);

            exit;
        }

        $controller->delete($id);

        break;


    default:

        http_response_code(405);

        echo json_encode([
            "error" => "Method Not Allowed"
        ]);

        break;
}
