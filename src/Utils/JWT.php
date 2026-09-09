<?php

namespace App\Utils;

class JWT
{
    private static string $secretKey = 'YOUR_SUPER_SECRET_KEY_HERE_CHANGE_ME'; // Change this in production
    private static int $expiration = 3600 * 24; // Token lifetime: 24 hours (in seconds)

    /**
     * Generate a JWT for a given payload
     */
    public static function generate(array $payload): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        // Add standard claims
        $payload['iat'] = time();
        $payload['exp'] = time() + self::$expiration;

        $base64UrlHeader  = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", self::$secretKey, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    /**
     * Validate and decode a JWT. Returns payload array or null if invalid/expired.
     */
    public static function validate(string $jwt): ?array
    {
        $tokenParts = explode('.', $jwt);

        if (count($tokenParts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $tokenParts;

        // Re-generate signature to compare
        $validSignature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secretKey, true)
        );

        if (!hash_equals($validSignature, $signature)) {
            return null; // Signature verification failed
        }

        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);

        // Check if token has expired
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return null; // Token expired
        }

        return $decodedPayload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
