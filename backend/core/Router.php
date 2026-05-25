<?php

class Router {
    private array $routes = [];

    public function get(string $path, string $handler, array $middleware = []): void {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, string $handler, array $middleware = []): void {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, string $handler, array $middleware = []): void {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, string $handler, array $middleware = []): void {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, string $handler, array $middleware): void {
        // Convierte /questions/{id} en regex /questions/(\d+)
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'middleware');
    }

public function dispatch(): void {
    
    $request = new Request();

    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $uri       = $request->uri;

    if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
        $uri = substr($uri, strlen($scriptDir));
    }

    if (empty($uri)) $uri = '/';
    

    foreach ($this->routes as $route) {
        if ($route['method'] !== $request->method) continue;

        if (!preg_match($route['pattern'], $uri, $matches)) continue;

        array_shift($matches);

        $payload = [];
        foreach ($route['middleware'] as $mw) {
            if ($mw === 'auth')  $payload = Middleware::auth($request);
            if ($mw === 'admin') Middleware::admin($payload);
        }

        [$class, $method] = explode('@', $route['handler']);
        require_once __DIR__ . "/../controllers/{$class}.php";

        $controller = new $class();
        $controller->$method($request, $payload, ...$matches);
        return;
    }

    Response::notFound('Ruta no encontrada');
}

public function patch(string $path, string $handler, array $middleware = []): void {
    $this->add('PATCH', $path, $handler, $middleware);
}

}