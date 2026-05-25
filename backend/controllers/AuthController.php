<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;

class AuthController {

    private UserModel $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    // ── Estudiante: registro con nombre y edad ──────────────

    public function studentRegister(Request $request, array $payload = []): void {
        $name = trim($request->input('name', ''));
        $age  = (int) $request->input('age', 0);

        if (!$name) {
            Response::error('El nombre es requerido');
        }

        if ($age < 16 || $age > 60) {
            Response::error('La edad debe estar entre 16 y 60 años');
        }

        // Buscar si ya existe un estudiante con ese nombre y edad
        $existing = $this->userModel->findByNameAndAge($name, $age);

        if ($existing) {
            // Estudiante ya registrado — reutilizar
            unset($existing['password']);
            $token = $this->generateToken($existing);

            Response::success([
                'token'      => $token,
                'user'       => $existing,
                'is_new'     => false,
                'message'    => '¡Bienvenido de vuelta, ' . $existing['name'] . '!',
            ], 'Sesión recuperada');
        } else {
            // Nuevo estudiante
            $id   = $this->userModel->createStudent($name, $age);
            $user = $this->userModel->findById($id);

            unset($user['password']);
            $token = $this->generateToken($user);

            Response::created([
                'token'   => $token,
                'user'    => $user,
                'is_new'  => true,
            ], 'Bienvenido al juego');
        }
    }

    

    // ── Admin: login con email y password ───────────────────

    public function adminLogin(Request $request, array $payload = []): void {
        $email    = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (!$email || !$password) {
            Response::error('Email y contraseña son requeridos');
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || $user['role'] !== 'admin') {
            Response::unauthorized('Credenciales incorrectas');
        }

        if (!password_verify($password, $user['password'])) {
            Response::unauthorized('Credenciales incorrectas');
        }

        unset($user['password']);
        $token = $this->generateToken($user);

        Response::success([
            'token' => $token,
            'user'  => $user,
        ], 'Login exitoso');
    }

    /**
 * POST /api/auth/forgot-password
 * Body: { "email": "admin@lumbalgia.com" }
 */
public function forgotPassword(Request $request, array $payload = []): void {
    $email = trim($request->input('email', ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email inválido');
    }

    $user = $this->userModel->findByEmail($email);

    // Por seguridad, siempre respondemos lo mismo
    // aunque el email no exista (evita enumerar usuarios)
    if (!$user || $user['role'] !== 'admin') {
        Response::success(null, 'Si el correo existe, recibirás las instrucciones en breve');
    }

    // Generar nueva contraseña aleatoria
    $newPassword = $this->generatePassword();

    // Actualizar en BD
    $this->userModel->updatePassword((int) $user['id'], $newPassword);

    // Enviar correo
    require_once __DIR__ . '/../services/MailService.php';
    $sent = MailService::sendPasswordRecovery($email, $user['name'], $newPassword);

    if (!$sent) {
        Response::error('No se pudo enviar el correo. Intenta más tarde.', 500);
    }

    Response::success(null, 'Si el correo existe, recibirás las instrucciones en breve');
}

private function generatePassword(int $length = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

    // ── Mantener login genérico por compatibilidad ───────────

    public function login(Request $request, array $payload = []): void {
        $this->adminLogin($request, $payload);
    }

    public function register(Request $request, array $payload = []): void {
        $this->studentRegister($request, $payload);
    }

    // ── Privados ────────────────────────────────────────────

    private function generateToken(array $user): string {
        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 86400);

        $payload = [
            'sub'   => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'] ?? null,
            'role'  => $user['role'],
            'iat'   => time(),
            'exp'   => time() + $expiry,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}