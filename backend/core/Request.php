<?php

class Request {
    public string $method;
    public string $uri;
    public array  $body;
    public array  $query;
    public array  $headers;

    public function __construct() {
        $this->method  = $_SERVER['REQUEST_METHOD'];
        $this->uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->query   = $_GET;
        $this->body    = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    private function parseBody(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseHeaders(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }

    public function input(string $key, mixed $default = null): mixed {
        return $this->body[$key] ?? $default;
    }

    public function bearerToken(): ?string {
    // Intento 1: header estándar
    $auth = $this->headers['authorization'] ?? '';

    // Intento 2: Apache a veces lo pasa como REDIRECT_HTTP_AUTHORIZATION
    if (!$auth) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    // Intento 3: Apache con mod_rewrite lo pasa como HTTP_AUTHORIZATION
    if (!$auth) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }

    // Intento 4: algunas configs de Laragon lo pasan como este key
    if (!$auth) {
        $auth = apache_request_headers()['Authorization'] ?? '';
    }

    if (str_starts_with($auth, 'Bearer ')) {
        return substr($auth, 7);
    }

    return null;
}
}