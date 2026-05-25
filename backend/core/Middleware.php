<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Middleware {

    public static function auth(Request $request): array {
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Token requerido');
            return [];
        }

        try {
            $secret  = $_ENV['JWT_SECRET'] ?? 'secret_default';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido o expirado');
             return [];
        }
    }

    public static function admin(array $payload): void {
        if (($payload['role'] ?? '') !== 'admin') {
            Response::forbidden('Se requiere rol de administrador');
        }
    }
}