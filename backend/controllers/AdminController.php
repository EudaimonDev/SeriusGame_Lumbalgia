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
            'language'    => $request->query['language']    ?? null,  // ← agrega esto
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
     * GET /api/admin/categories?lang=en
     */
    public function categories(Request $request, array $payload): void {
        $lang = $request->query['lang'] ?? 'es';
        $db   = Database::connect();

        $stmt = $db->query('SELECT * FROM categories ORDER BY id');
        $cats = $stmt->fetchAll();

        // Si el idioma es inglés y tiene traducción, usar name_en
        if ($lang === 'en') {
            $cats = array_map(function($cat) {
                return [
                    'id'          => $cat['id'],
                    'name'        => !empty($cat['name_en']) ? $cat['name_en'] : $cat['name'],
                    'description' => !empty($cat['description_en']) ? $cat['description_en'] : $cat['description'],
                    'name_es'     => $cat['name'],
                    'name_en'     => $cat['name_en'] ?? null,
                ];
            }, $cats);
        }

        Response::success($cats);
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
                'language'       => $language === 'english' ? 'en' : 'es',  // ← agrega esto
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

/**
 * GET /api/admin/reports/rooms
 */
public function reportRooms(Request $request, array $payload): void {
    $db = Database::connect();

    $rooms = $db->query(
        'SELECT r.id, r.name, r.code, r.group_type,
                COUNT(DISTINCT u.id) AS total_students,
                COUNT(DISTINCT gs.id) AS total_sessions,
                ROUND(AVG(gs.score), 1) AS avg_score,
                ROUND(AVG(
                    CASE WHEN gs.total_questions > 0
                    THEN (gs.score / gs.total_questions) * 100
                    ELSE 0 END
                ), 1) AS avg_precision,
                MAX(gs.score) AS max_score
         FROM rooms r
         LEFT JOIN users u ON u.room_id = r.id
         LEFT JOIN game_sessions gs ON gs.user_id = u.id AND gs.ended_at IS NOT NULL
         WHERE r.is_active = 1
         GROUP BY r.id
         ORDER BY avg_precision DESC'
    )->fetchAll();

    Response::success($rooms);
}

/**
 * GET /api/admin/reports/students
 */
public function reportStudents(Request $request, array $payload): void {
    $db = Database::connect();

    $roomId = $request->query['room_id'] ?? null;

    $where = $roomId ? 'AND u.room_id = ' . (int)$roomId : '';

    $students = $db->query(
        "SELECT u.id, u.name, u.age, u.group_type,
                r.name AS room_name, r.code AS room_code,
                COUNT(DISTINCT gs.id) AS total_sessions,
                ROUND(AVG(gs.score), 1) AS avg_score,
                MAX(gs.score) AS max_score,
                ROUND(AVG(
                    CASE WHEN gs.total_questions > 0
                    THEN (gs.score / gs.total_questions) * 100
                    ELSE 0 END
                ), 1) AS avg_precision,
                SUM(gs.score) AS total_score
         FROM users u
         LEFT JOIN rooms r ON r.id = u.room_id
         LEFT JOIN game_sessions gs ON gs.user_id = u.id AND gs.ended_at IS NOT NULL
         WHERE u.role = 'student' $where
         GROUP BY u.id
         ORDER BY avg_precision DESC"
    )->fetchAll();

    Response::success($students);
}

/**
 * GET /api/admin/reports/evolution
 */
public function reportEvolution(Request $request, array $payload): void {
    $db     = Database::connect();
    $roomId = $request->query['room_id'] ?? null;
    $where  = $roomId ? 'AND u.room_id = ' . (int)$roomId : '';

    $rows = $db->query(
        "SELECT
            u.id        AS user_id,
            u.name      AS student_name,
            u.group_type,
            r.name      AS room_name,
            gs.score,
            gs.total_questions,
            gs.started_at,
            ROUND(
                CASE WHEN gs.total_questions > 0
                THEN (gs.score / gs.total_questions) * 100
                ELSE 0 END
            , 1) AS `precision`
        FROM game_sessions gs
        JOIN users u ON u.id = gs.user_id
        LEFT JOIN rooms r ON r.id = u.room_id
        WHERE u.role = 'student'
        AND gs.score > 0  -- ← agrega esto
        $where
        ORDER BY u.id, gs.started_at ASC"
    )->fetchAll();
    // Agrega número de sesión por estudiante
    $sessionCount = [];
    foreach ($rows as &$row) {
        $uid = $row['user_id'];
        $sessionCount[$uid] = ($sessionCount[$uid] ?? 0) + 1;
        $row['session_number'] = $sessionCount[$uid];
    }

    Response::success($rows);
}

/**
 * GET /api/admin/reports/stats
 */
public function reportStats(Request $request, array $payload): void {
    $db     = Database::connect();
    $roomId = $request->query['room_id'] ?? null;
    $where  = $roomId ? 'AND u.room_id = ' . (int)$roomId : '';

    // Datos agregados por estudiante
    $students = $db->query(
        "SELECT
            u.id        AS user_id,
            u.name      AS student_name,
            u.group_type,
            r.name      AS room_name,
            COUNT(gs.id) AS total_sessions,
            ROUND(AVG(gs.score), 2) AS avg_score,
            ROUND(AVG(
                CASE WHEN gs.total_questions > 0
                THEN (gs.score / gs.total_questions) * 100
                ELSE 0 END
            ), 2) AS avg_precision,
            MAX(gs.score) AS max_score
         FROM users u
         LEFT JOIN rooms r ON r.id = u.room_id
         LEFT JOIN game_sessions gs ON gs.user_id = u.id
         WHERE u.role = 'student' $where
         GROUP BY u.id
         ORDER BY u.group_type, avg_precision DESC"
    )->fetchAll();

    // Scores individuales por estudiante para mediana/desviación en frontend
    $scoresRaw = $db->query(
        "SELECT gs.user_id, gs.score
        FROM game_sessions gs
        JOIN users u ON u.id = gs.user_id
        WHERE u.role = 'student'
        AND gs.score > 0  -- ← agrega esto también
        $where
        ORDER BY gs.user_id"
    )->fetchAll();

    $scoresByUser = [];
    foreach ($scoresRaw as $s) {
        $scoresByUser[$s['user_id']][] = (float)$s['score'];
    }

    foreach ($students as &$student) {
        $student['scores'] = $scoresByUser[$student['user_id']] ?? [];
    }

    Response::success($students);
}

/**
 * GET /api/admin/reports/testcomparison
 */
public function reportTestComparison(Request $request, array $payload): void {
    $db = Database::connect();

    $rows = $db->prepare(
        "SELECT
            u.id          AS user_id,
            u.name        AS student_name,
            u.group_type,
            r.name        AS room_name,
            r.id          AS room_id,
            MAX(CASE WHEN tr.test_type = 'pretest'  THEN tr.score END) AS pretest_score,
            MAX(CASE WHEN tr.test_type = 'posttest' THEN tr.score END) AS posttest_score,
            MAX(tr.knowledge_gain) AS knowledge_gain
         FROM users u
         LEFT JOIN rooms r ON r.id = u.room_id
         LEFT JOIN test_results tr ON tr.user_id = u.id
         WHERE u.role = 'student'
         GROUP BY u.id
         ORDER BY u.group_type, r.id"
    );
    $rows->execute();
    $data = $rows->fetchAll();

    Response::success($data);
}

/**
 * GET /api/admin/reports/questions
 */
public function reportQuestions(Request $request, array $payload): void {
    $db     = Database::connect();
    $roomId = $request->query['room_id'] ?? null;
    $where  = $roomId
        ? 'AND u.room_id = ' . (int)$roomId
        : '';

    $rows = $db->query(
        "SELECT
            q.id                                        AS question_id,
            q.question_text,
            q.difficulty,
            c.name                                      AS category_name,
            COUNT(sa.id)                                AS total_answers,
            SUM(sa.is_correct)                          AS total_correct,
            COUNT(sa.id) - SUM(sa.is_correct)          AS total_incorrect,
            ROUND(SUM(sa.is_correct) / COUNT(sa.id) * 100, 1) AS success_rate,
            ROUND(AVG(sa.response_time_ms) / 1000, 1)  AS avg_time_sec,
            SUM(sa.selected_answer = 'a')               AS count_a,
            SUM(sa.selected_answer = 'b')               AS count_b,
            SUM(sa.selected_answer = 'c')               AS count_c,
            SUM(sa.selected_answer = 'd')               AS count_d,
            q.correct_answer
         FROM session_answers sa
         JOIN game_sessions gs ON gs.id = sa.session_id
         JOIN users u ON u.id = gs.user_id
         JOIN questions q ON q.id = sa.question_id
         LEFT JOIN categories c ON c.id = q.category_id
         WHERE u.role = 'student' $where
         GROUP BY q.id
         ORDER BY total_answers DESC"
    )->fetchAll();

    Response::success($rows);
}

/**
 * GET /api/admin/admins
 */
public function getAdmins(Request $request, array $payload): void {
    $db   = Database::connect();
    $stmt = $db->query("SELECT id, name, email, role, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC");
    Response::success($stmt->fetchAll());
}

/**
 * POST /api/admin/admins
 */
public function storeAdmin(Request $request, array $payload): void {
    $name     = trim($request->input('name', ''));
    $email    = trim($request->input('email', ''));
    $password = trim($request->input('password', ''));

    if (!$name || !$email || !$password) {
        Response::error('Nombre, email y contraseña son requeridos');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email inválido');
    }

    $db   = Database::connect();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        Response::error('El email ya está registrado');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password, role, group_type) VALUES (?, ?, ?, 'admin', 'experimental')"
    );
    $stmt->execute([$name, $email, $hash]);

    Response::success(['id' => (int)$db->lastInsertId()], 'Administrador creado', 201);
}

/**
 * PUT /api/admin/admins/{id}
 */
public function updateAdmin(Request $request, array $payload, string $id): void {
    $name     = trim($request->input('name', ''));
    $email    = trim($request->input('email', ''));
    $password = trim($request->input('password', ''));

    if (!$name || !$email) {
        Response::error('Nombre y email son requeridos');
    }

    $db   = Database::connect();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, (int)$id]);
    if ($stmt->fetch()) {
        Response::error('El email ya está en uso');
    }

    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'admin'")
           ->execute([$name, $email, $hash, (int)$id]);
    } else {
        $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'admin'")
           ->execute([$name, $email, (int)$id]);
    }

    Response::success(null, 'Administrador actualizado');
}

/**
 * DELETE /api/admin/admins/{id}
 */
public function destroyAdmin(Request $request, array $payload, string $id): void {
    if ((int)$payload['sub'] === (int)$id) {
        Response::error('No puedes eliminarte a ti mismo');
    }

    $db = Database::connect();
    $db->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([(int)$id]);
    Response::success(null, 'Administrador eliminado');
}
}