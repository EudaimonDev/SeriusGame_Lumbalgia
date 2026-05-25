<?php

function handleCors(): void {
    $allowed = [
        'http://localhost:4200',
        'https://TU_USUARIO.github.io',   // <- cambia esto cuando tengas el repo
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

handleCors();