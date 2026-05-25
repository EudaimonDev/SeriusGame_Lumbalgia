<?php
require_once __DIR__ . '/../services/GeminiService.php';
require_once __DIR__ . '/../models/QuestionModel.php';
require_once __DIR__ . '/../config/database.php';

class AdminController {

    private QuestionModel $questionModel;

    public function __construct() {
        $this->questionModel = new QuestionModel();
    }

    /**
     * GET /api/admin/questions
     * Query params opcionales: ?difficulty=easy&category_id=1
     */
    public function index(Request $request, array $payload): void {
        $filters = [
            'difficulty'  => $request->query['difficulty']  ?? null,
            'category_id' => $request->query['category_id'] ?? null,
        ];

        $questions = $this->questionModel->all($filters);
        Response::success($questions);
    }

    /**
     * POST /api/admin/questions
     */
    public function store(Request $request, array $payload): void {
        $data = $this->validateQuestionData($request);
        $id   = $this->questionModel->create($data);
        $question = $this->questionModel->findById($id);
        Response::created($question, 'Pregunta creada');
    }

    /**
     * PUT /api/admin/questions/{id}
     */
    public function update(Request $request, array $payload, string $id): void {
        $question = $this->questionModel->findById((int)$id);

        if (!$question) {
            Response::notFound('Pregunta no encontrada');
        }

        $data = $this->validateQuestionData($request);
        $this->questionModel->update((int)$id, $data);

        Response::success($this->questionModel->findById((int)$id), 'Pregunta actualizada');
    }

    /**
     * DELETE /api/admin/questions/{id}  (soft delete)
     */
    public function destroy(Request $request, array $payload, string $id): void {
        $question = $this->questionModel->findById((int)$id);

        if (!$question) {
            Response::notFound('Pregunta no encontrada');
        }

        $this->questionModel->delete((int)$id);
        Response::success(null, 'Pregunta eliminada');
    }

    /**
     * GET /api/admin/categories
     */
    public function categories(Request $request, array $payload): void {
        $stmt = Database::connect()->query('SELECT * FROM categories ORDER BY id');
        Response::success($stmt->fetchAll());
    }

    // --- Helper ---

    private function validateQuestionData(Request $request): array {
        $required = ['category_id', 'difficulty', 'question_text',
                     'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];

        foreach ($required as $field) {
            if (!$request->input($field)) {
                Response::error("El campo '$field' es requerido");
            }
        }

        $validDifficulties = ['easy', 'medium', 'hard'];
        if (!in_array($request->input('difficulty'), $validDifficulties, true)) {
            Response::error('Dificultad inválida. Valores: easy, medium, hard');
        }

        $validAnswers = ['a', 'b', 'c', 'd'];
        if (!in_array(strtolower($request->input('correct_answer')), $validAnswers, true)) {
            Response::error('Respuesta correcta inválida. Valores: a, b, c, d');
        }

        return [
            'category_id'    => (int) $request->input('category_id'),
            'difficulty'     => $request->input('difficulty'),
            'question_text'  => trim($request->input('question_text')),
            'option_a'       => trim($request->input('option_a')),
            'option_b'       => trim($request->input('option_b')),
            'option_c'       => trim($request->input('option_c')),
            'option_d'       => trim($request->input('option_d')),
            'correct_answer' => strtolower($request->input('correct_answer')),
            'feedback_text'  => trim($request->input('feedback_text', '')),
        ];
    }

    /**
 * POST /api/admin/questions/generate
 * Body: { "category_id": 1, "category_name": "Ergonomía postural", "difficulty": "medium", "count": 5 }
 */
public function generate(Request $request, array $payload): void {
    $categoryId   = (int) $request->input('category_id');
    $categoryName = trim($request->input('category_name', ''));
    $difficulty   = $request->input('difficulty', 'easy');
    $count        = min((int) $request->input('count', 5), 10);
    $language     = $request->input('language', 'español');

    if (!$categoryId || !$categoryName) {
        Response::error('category_id y category_name son requeridos');
    }

    $validDifficulties = ['easy', 'medium', 'hard'];
    if (!in_array($difficulty, $validDifficulties, true)) {
        Response::error('Dificultad inválida');
    }

    try {
        $ai        = new GeminiService();
        $generated = $ai->generateQuestions($categoryName, $difficulty, $count, $language);

        $saved = [];
        foreach ($generated as $q) {
            if (empty($q['question_text']) || empty($q['correct_answer'])) continue;

            $id = $this->questionModel->create([
                'category_id'    => $categoryId,
                'difficulty'     => $difficulty,
                'question_text'  => $q['question_text'],
                'option_a'       => $q['option_a']      ?? '',
                'option_b'       => $q['option_b']      ?? '',
                'option_c'       => $q['option_c']      ?? '',
                'option_d'       => $q['option_d']      ?? '',
                'correct_answer' => strtolower($q['correct_answer']),
                'feedback_text'  => $q['feedback_text'] ?? '',
            ]);

            Database::connect()
                ->prepare('UPDATE questions SET is_active = 0 WHERE id = ?')
                ->execute([$id]);

            $saved[] = $this->questionModel->findById($id);
        }

        Response::success([
            'generated' => count($saved),
            'questions' => $saved,
            'notice'    => 'Las preguntas fueron guardadas como PENDIENTES. Deben ser validadas por un profesional de salud antes de activarse.'
        ], 'Preguntas generadas exitosamente');

    } catch (\Exception $e) {
        Response::error('Error al generar preguntas: ' . $e->getMessage(), 500);
    }
}

public function geminiModels(Request $request, array $payload): void {
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    $url    = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    // Extraer solo los nombres de los modelos
    $models = array_map(
        fn($m) => $m['name'],
        $data['models'] ?? []
    );

    Response::success([
        'http_code' => $httpCode,
        'models'    => $models
    ]);
}

/**
 * POST /api/admin/questions/import
 * Recibe archivo CSV o TXT y carga preguntas masivamente
 */
public function import(Request $request, array $payload): void {
    if (empty($_FILES['file'])) {
        Response::error('No se recibió ningún archivo');
    }

    $file     = $_FILES['file'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['csv', 'txt'];

    if (!in_array($ext, $allowed, true)) {
        Response::error('Solo se permiten archivos CSV o TXT');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        Response::error('El archivo no debe superar 2MB');
    }

    $content = file_get_contents($file['tmp_name']);

    // Normalizar saltos de línea Windows/Linux
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines   = array_filter(explode("\n", $content));
    $lines   = array_values($lines);

    if (count($lines) < 2) {
        Response::error('El archivo debe tener al menos una pregunta además del encabezado');
    }

    // Saltar la primera línea (encabezado)
    array_shift($lines);

    $saved  = [];
    $errors = [];

    foreach ($lines as $index => $line) {
        $row = str_getcsv($line);

        if (count($row) < 9) {
            $errors[] = "Fila " . ($index + 2) . ": faltan columnas (se esperan 9)";
            continue;
        }

        [
            $categoryId,
            $difficulty,
            $questionText,
            $optionA,
            $optionB,
            $optionC,
            $optionD,
            $correctAnswer,
            $feedbackText
        ] = array_map('trim', $row);

        $validDifficulties = ['easy', 'medium', 'hard'];
        $validAnswers      = ['a', 'b', 'c', 'd'];

        if (!in_array($difficulty, $validDifficulties, true)) {
            $errors[] = "Fila " . ($index + 2) . ": dificultad inválida '$difficulty'";
            continue;
        }

        if (!in_array(strtolower($correctAnswer), $validAnswers, true)) {
            $errors[] = "Fila " . ($index + 2) . ": respuesta inválida '$correctAnswer'";
            continue;
        }

        if (empty($questionText)) {
            $errors[] = "Fila " . ($index + 2) . ": la pregunta está vacía";
            continue;
        }

        $id = $this->questionModel->create([
            'category_id'    => (int) $categoryId,
            'difficulty'     => $difficulty,
            'question_text'  => $questionText,
            'option_a'       => $optionA,
            'option_b'       => $optionB,
            'option_c'       => $optionC,
            'option_d'       => $optionD,
            'correct_answer' => strtolower($correctAnswer),
            'feedback_text'  => $feedbackText,
        ]);

        // Todas las preguntas importadas entran inactivas
        Database::connect()
            ->prepare('UPDATE questions SET is_active = 0 WHERE id = ?')
            ->execute([$id]);

        $saved[] = $this->questionModel->findById($id);
    }

    Response::success([
        'imported' => count($saved),
        'errors'   => $errors,
        'questions' => $saved,
        'notice'   => 'Preguntas importadas como PENDIENTES. Verifica cada una antes de activarla.'
    ], 'Importación completada');
}

/**
 * PATCH /api/admin/questions/{id}/toggle
 * Activa o desactiva una pregunta (verificar / desverificar)
 */
public function toggle(Request $request, array $payload, string $id): void {
    $question = $this->questionModel->findById((int) $id);

    if (!$question) {
        Response::notFound('Pregunta no encontrada');
    }

    $newState = $question['is_active'] ? 0 : 1;

    Database::connect()
        ->prepare('UPDATE questions SET is_active = ? WHERE id = ?')
        ->execute([$newState, (int) $id]);

    Response::success([
        'id'        => (int) $id,
        'is_active' => (bool) $newState,
        'status'    => $newState ? 'verificada' : 'desverificada'
    ], $newState ? 'Pregunta verificada' : 'Pregunta desverificada');
}

/**
 * GET /api/admin/stats
 */
public function stats(Request $request, array $payload): void {
    $db = Database::connect();

    $totalStudents = $db->query(
        'SELECT COUNT(*) FROM users WHERE role = "student"'
    )->fetchColumn();

    $totalRooms = $db->query(
        'SELECT COUNT(*) FROM rooms WHERE is_active = 1'
    )->fetchColumn();

    $totalQuestions = $db->query(
        'SELECT COUNT(*) FROM questions WHERE is_active = 1'
    )->fetchColumn();

    $totalSessions = $db->query(
        'SELECT COUNT(*) FROM game_sessions WHERE ended_at IS NOT NULL'
    )->fetchColumn();

    $avgScore = $db->query(
        'SELECT ROUND(AVG(score), 1) FROM game_sessions WHERE ended_at IS NOT NULL'
    )->fetchColumn();

    $recentStudents = $db->query(
        'SELECT name, created_at, group_type
         FROM users
         WHERE role = "student"
         ORDER BY created_at DESC
         LIMIT 5'
    )->fetchAll();

    $roomStats = $db->query(
        'SELECT r.code, r.name, r.group_type,
                COUNT(u.id) AS total_students
         FROM rooms r
         LEFT JOIN users u ON u.room_id = r.id
         WHERE r.is_active = 1
         GROUP BY r.id
         ORDER BY total_students DESC
         LIMIT 5'
    )->fetchAll();

    Response::success([
        'total_students'  => (int)   $totalStudents,
        'total_rooms'     => (int)   $totalRooms,
        'total_questions' => (int)   $totalQuestions,
        'total_sessions'  => (int)   $totalSessions,
        'avg_score'       => (float) ($avgScore ?? 0),
        'recent_students' => $recentStudents,
        'room_stats'      => $roomStats,
    ]);
}

/**
 * POST /api/admin/categories
 */
public function storeCategory(Request $request, array $payload): void {
    $name        = trim($request->input('name', ''));
    $description = trim($request->input('description', ''));

    if (!$name) {
        Response::error('El nombre es requerido');
    }

    $db   = Database::connect();
    $stmt = $db->prepare(
        'INSERT INTO categories (name, description) VALUES (:name, :description)'
    );
    $stmt->execute([':name' => $name, ':description' => $description]);
    $id = (int) $db->lastInsertId();

    $cat = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $cat->execute([$id]);

    Response::created($cat->fetch(), 'Categoría creada');
}

/**
 * PUT /api/admin/categories/{id}
 */
public function updateCategory(Request $request, array $payload, string $id): void {
    $name        = trim($request->input('name', ''));
    $description = trim($request->input('description', ''));

    if (!$name) {
        Response::error('El nombre es requerido');
    }

    $db = Database::connect();
    $db->prepare(
        'UPDATE categories SET name = :name, description = :description WHERE id = :id'
    )->execute([':name' => $name, ':description' => $description, ':id' => (int) $id]);

    $cat = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $cat->execute([(int) $id]);

    Response::success($cat->fetch(), 'Categoría actualizada');
}

/**
 * DELETE /api/admin/categories/{id}
 */
public function destroyCategory(Request $request, array $payload, string $id): void {
    $db = Database::connect();

    // Verificar si tiene preguntas asociadas
    $count = $db->prepare(
        'SELECT COUNT(*) FROM questions WHERE category_id = ? AND is_active = 1'
    );
    $count->execute([(int) $id]);

    if ((int) $count->fetchColumn() > 0) {
        Response::error('No se puede eliminar: la categoría tiene preguntas activas asociadas', 409);
    }

    $db->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $id]);
    Response::success(null, 'Categoría eliminada');
}
}