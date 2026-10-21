<?php

namespace App\Support;

class Request
{
    private static ?array $jsonBody = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return rtrim($uri, '/') ?: '/';
    }

    public static function body(): array
    {
        if (self::$jsonBody !== null) {
            return self::$jsonBody;
        }

        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                self::$jsonBody = $decoded;
                return self::$jsonBody;
            }
        }

        self::$jsonBody = $_POST;
        return self::$jsonBody;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::body();
        return $body[$key] ?? $_GET[$key] ?? $default;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function allQuery(): array
    {
        return $_GET;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public static function bearerToken(): ?string
    {
        $auth = self::header('Authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}

