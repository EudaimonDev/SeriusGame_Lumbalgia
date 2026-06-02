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
        $age  = (int)$request->input('age', 0);

        if (!$name) Response::error('El nombre es requerido');
        if (!$code) Response::error('El código de sala es requerido');

        $room = $this->roomModel->findByCode($code);
        if (!$room) Response::error('Código de sala inválido o sala inactiva', 404);

        // Buscar si ya existe un estudiante con ese nombre
        $existing = $this->userModel->findByName($name);

        if ($existing) {
            // Actualizar sala, grupo y edad si ya existe
            $this->userModel->updateRoomAndAge($existing['id'], $room['id'], $room['group_type'], $age);
            $user = $this->userModel->findById($existing['id']);
        } else {
            // Crear nuevo estudiante
            $id   = $this->userModel->createStudentWithRoom($name, $room['id'], $room['group_type'], $age);
            $user = $this->userModel->findById($id);
        }

        unset($user['password']);

        require_once __DIR__ . '/../vendor/autoload.php';
        $secret  = $_ENV['JWT_SECRET'] ?? 'secret';
        $expiry  = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
        $jwtPayload = [
            'sub'  => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'iat'  => time(),
            'exp'  => time() + $expiry,
        ];
        $token = \Firebase\JWT\JWT::encode($jwtPayload, $secret, 'HS256');

        Response::created([
            'token' => $token,
            'user'  => $user,
            'room'  => [
                'code'       => $room['code'],
                'name'       => $room['name'],
                'group_type' => $room['group_type'],
                'phase'      => $room['phase'] ?? 'game',
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
        $code           = strtoupper(trim($request->input('code', '')));
        $name           = trim($request->input('name', ''));
        $groupType      = $request->input('group_type', 'experimental');
        $phase          = $request->input('phase', 'game');
        $questionsCount = (int) $request->input('questions_count', 10);
        $difficulty     = $request->input('difficulty', 'adaptive');
        $categoryIds    = $request->input('category_ids', null);

        if (!$code || !$name) {
            Response::error('Código y nombre son requeridos');
        }

        $validGroups    = ['control', 'experimental'];
        $validPhases    = ['pretest', 'game', 'posttest'];
        $validDifficulties = ['easy', 'medium', 'hard', 'adaptive'];

        if (!in_array($groupType, $validGroups, true)) {
            Response::error('Tipo de grupo inválido');
        }
        if (!in_array($phase, $validPhases, true)) {
            Response::error('Fase inválida');
        }
        if (!in_array($difficulty, $validDifficulties, true)) {
            Response::error('Dificultad inválida');
        }
        if ($questionsCount < 1 || $questionsCount > 50) {
            Response::error('El número de preguntas debe estar entre 1 y 50');
        }

        try {
            $id   = $this->roomModel->create(
                $code, $name, $groupType, $phase,
                $questionsCount, $difficulty, $categoryIds
            );
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

    /**
 * PATCH /api/admin/rooms/{id}/phase
 */
/*public function updatePhase(Request $request, array $payload, string $id): void {
    $phase = trim($request->input('phase', ''));

    $validPhases = ['pretest', 'game', 'posttest'];
    if (!in_array($phase, $validPhases, true)) {
        Response::error('Fase inválida. Valores: pretest, game, posttest');
    }

    $room = $this->roomModel->findById((int) $id);
    if (!$room) {
        Response::notFound('Sala no encontrada');
    }

    Database::connect()
        ->prepare('UPDATE rooms SET phase = ? WHERE id = ?')
        ->execute([$phase, (int) $id]);

    $room = $this->roomModel->findById((int) $id);
    Response::success($room, 'Fase actualizada');
}*/

/**
 * PUT /api/admin/rooms/{id}
 */
public function update(Request $request, array $payload, string $id): void {
    $name           = trim($request->input('name', ''));
    $groupType      = $request->input('group_type', 'experimental');
    $phase          = $request->input('phase', 'game');
    $questionsCount = (int) $request->input('questions_count', 10);
    $difficulty     = $request->input('difficulty', 'adaptive');
    $categoryIds    = $request->input('category_ids', null);

    if (!$name) Response::error('El nombre es requerido');

    $validGroups       = ['control', 'experimental'];
    $validPhases       = ['pretest', 'game', 'posttest'];
    $validDifficulties = ['easy', 'medium', 'hard', 'adaptive'];

    if (!in_array($groupType, $validGroups, true))       Response::error('Tipo de grupo inválido');
    if (!in_array($phase, $validPhases, true))           Response::error('Fase inválida');
    if (!in_array($difficulty, $validDifficulties, true)) Response::error('Dificultad inválida');
    if ($questionsCount < 1 || $questionsCount > 50)     Response::error('Número de preguntas inválido');

    $this->roomModel->update((int) $id, [
        'name'            => $name,
        'group_type'      => $groupType,
        'phase'           => $phase,
        'questions_count' => $questionsCount,
        'difficulty'      => $difficulty,
        'category_ids'    => $categoryIds ?: null,
    ]);

    $room = $this->roomModel->findById((int) $id);
    Response::success($room, 'Sala actualizada');
}


}