<?php

require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/UserModel.php';

class RoomController {

    private RoomModel $roomModel;
    private UserModel $userModel;

    public function __construct() {
        $this->roomModel = new RoomModel();
        $this->userModel = new UserModel();
    }

    /**
     * POST /api/rooms/join
     * Estudiante ingresa a sala con nombre + código
     */
    public function join(Request $request, array $payload = []): void {
        $name = trim($request->input('name', ''));
        $code = trim($request->input('code', ''));

        if (!$name) {
            Response::error('El nombre es requerido');
        }

        if (!$code) {
            Response::error('El código de sala es requerido');
        }

        $room = $this->roomModel->findByCode($code);

        if (!$room) {
            Response::error('Código de sala inválido o sala inactiva', 404);
        }

        // Crear estudiante con room_id y group_type de la sala
        $id   = $this->userModel->createStudentWithRoom($name, $room['id'], $room['group_type']);
        $user = $this->userModel->findById($id);

        unset($user['password']);

        require_once __DIR__ . '/../vendor/autoload.php';
        $secret  = $_ENV['JWT_SECRET'] ?? 'secret';
        $expiry  = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
        $jwtPayload = [
            'sub'   => $user['id'],
            'name'  => $user['name'],
            'role'  => $user['role'],
            'iat'   => time(),
            'exp'   => time() + $expiry,
        ];
        $token = \Firebase\JWT\JWT::encode($jwtPayload, $secret, 'HS256');

        Response::created([
            'token' => $token,
            'user'  => $user,
            'room'  => [
                'code'       => $room['code'],
                'name'       => $room['name'],
                'group_type' => $room['group_type'],
            ],
        ], 'Bienvenido a la sala ' . $room['name']);
    }

    /**
     * GET /api/admin/rooms
     */
    public function index(Request $request, array $payload): void {
        Response::success($this->roomModel->all());
    }

    /**
     * POST /api/admin/rooms
     */
    public function store(Request $request, array $payload): void {
        $code      = strtoupper(trim($request->input('code', '')));
        $name      = trim($request->input('name', ''));
        $groupType = $request->input('group_type', 'experimental');

        if (!$code || !$name) {
            Response::error('Código y nombre son requeridos');
        }

        $validGroups = ['control', 'experimental'];
        if (!in_array($groupType, $validGroups, true)) {
            Response::error('Tipo de grupo inválido');
        }

        try {
            $id   = $this->roomModel->create($code, $name, $groupType);
            $room = $this->roomModel->findById($id);
            Response::created($room, 'Sala creada');
        } catch (\Exception $e) {
            Response::error('El código ya existe', 409);
        }
    }

    /**
     * PATCH /api/admin/rooms/{id}/toggle
     */
    public function toggle(Request $request, array $payload, string $id): void {
        $this->roomModel->toggle((int) $id);
        $room = $this->roomModel->findById((int) $id);
        Response::success($room, 'Sala actualizada');
    }

    /**
     * DELETE /api/admin/rooms/{id}
     */
    public function destroy(Request $request, array $payload, string $id): void {
        $this->roomModel->delete((int) $id);
        Response::success(null, 'Sala eliminada');
    }
}