<?php

class Response {

    public static function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function success(mixed $data = null, string $message = 'OK'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    public static function created(mixed $data = null, string $message = 'Creado'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], 201);
    }

    public static function error(string $message, int $status = 400): void {
        self::json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $status);
    }

    public static function unauthorized(string $message = 'No autorizado'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void {
        self::error($message, 404);
    }
}