<?php

namespace App\Middleware;

use App\Utils\JWT;

class AuthMiddleware
{
    /**
     * Authenticates the incoming request.
     * Returns decoded user array or terminates execution with 401 Unauthorized.
     */
    public static function authenticate(): array
    {
        $headers = self::getAuthorizationHeader();

        if (!$headers || !preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized: Missing or malformed Authorization header"]);
            exit;
        }

        $token = $matches[1];
        $decoded = JWT::validate($token);

        if (!$decoded) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized: Token is invalid or has expired"]);
            exit;
        }

        return $decoded;
    }

    /**
     * Get Authorization header from server context
     */
    private static function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                return trim($requestHeaders['Authorization']);
            }
        }

        return null;
    }
}
