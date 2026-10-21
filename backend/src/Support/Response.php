<?php

namespace App\Support;

class Response
{
    public static function json(mixed $data = null, string $message = '', int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        foreach ($headers as $key => $val) {
            header("{$key}: {$val}");
        }

        $payload = [];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($message !== '') {
            $payload['message'] = $message;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');

        $payload = [
            'message' => $message,
        ];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function validationError(array $errors, string $message = 'Validasi gagal'): void
    {
        self::error($message, 422, $errors);
    }

    public static function csv(string $content, string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo $content;
        exit;
    }

    public static function download(string $content, string $filename, string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
