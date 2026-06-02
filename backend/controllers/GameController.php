<?php

require_once __DIR__ . '/../models/QuestionModel.php';
require_once __DIR__ . '/../models/SessionModel.php';
require_once __DIR__ . '/../models/AnswerModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/GameConfigModel.php';
require_once __DIR__ . '/../services/AdaptiveEngine.php';

class GameController {

    private QuestionModel   $questionModel;
    private SessionModel    $sessionModel;
    private AnswerModel     $answerModel;
    private GameConfigModel $configModel;
    

    public function __construct() {
        $this->questionModel = new QuestionModel();
        $this->sessionModel  = new SessionModel();
        $this->answerModel   = new AnswerModel();
        $this->configModel   = new GameConfigModel();
    }

    /**
     * POST /api/game/start
     */
    public function start(Request $request, array $payload): void {
    $userId      = $payload['sub'];
    //$sessionType = $request->input('session_type', 'game');
    $language    = $request->input('language', 'es'); // ← nuevo

    //$validTypes = ['pretest', 'game', 'posttest'];
    //if (!in_array($sessionType, $validTypes, true)) {
    //    Response::error('Tipo de sesión inválido');
    //}
    $sessionType = 'game';
    $config    = $this->configModel->get();
    $sessionId = $this->sessionModel->create($userId, $sessionType);

    $userModel  = new \UserModel();
    $user       = $userModel->findById($userId);
    $roomConfig = null;

    if (!empty($user['room_id'])) {
        $db   = \Database::connect();
        $stmt = $db->prepare('SELECT * FROM rooms WHERE id = ?');
        $stmt->execute([$user['room_id']]);
        $roomConfig = $stmt->fetch() ?: null;
    }

    $initialDifficulty = 'easy';
    if ($roomConfig && $roomConfig['difficulty'] !== 'adaptive') {
        $initialDifficulty = $roomConfig['difficulty'];
    }

    $allowedCategories = null;
    if ($roomConfig && !empty($roomConfig['category_ids'])) {
        $allowedCategories = array_map('intval', explode(',', $roomConfig['category_ids']));
    }

    // Pasar language a getNextQuestion
    $question = $this->getNextQuestion($sessionId, $initialDifficulty, [], $allowedCategories, $language);

    if (!$question) {
        Response::error('No hay preguntas disponibles en este idioma', 500);
    }

    $maxQuestions = $roomConfig
        ? (int) $roomConfig['questions_count']
        : (int) $config['questions'];

    Response::success([
        'session_id' => $sessionId,
        'question'   => $this->formatQuestion($question),
        'config'     => [
            'lives'        => (int) $config['lives'],
            'questions'    => $maxQuestions,
            'time_seconds' => (int) $config['time_seconds'],
            'difficulty'   => $roomConfig['difficulty'] ?? 'adaptive',
        ],
    ], 'Sesión iniciada');
}

    /**
     * POST /api/game/answer
     */
    public function answer(Request $request, array $payload): void {
        $sessionId      = (int) $request->input('session_id');
        $questionId     = (int) $request->input('question_id');
        $selectedAnswer = strtolower(trim($request->input('selected_answer', '')));
        $responseTime   = (int) $request->input('response_time_ms', 0);
        
        if (!$sessionId || !$questionId) {
            Response::error('Faltan parámetros requeridos');
        }

        // Detectar timeout ANTES de validar
        $isTimeout = ($selectedAnswer === 'timeout' || $selectedAnswer === '');
        if ($isTimeout) {
            $selectedAnswer = 'a';
        }

        $session  = $this->sessionModel->findById($sessionId);
        $question = $this->questionModel->findById($questionId);

        if (!$session || !$question) {
            Response::notFound('Sesión o pregunta no encontrada');
        }

        if ($session['user_id'] !== $payload['sub']) {
            Response::forbidden();
        }

        $config = $this->configModel->get();

        // Si es timeout SIEMPRE es incorrecto
        $isCorrect = $isTimeout ? false : ($selectedAnswer === $question['correct_answer']);

        // Calcular puntos
        $points = 0;
        if ($isCorrect) {
            $points = (int) $config['points_correct'];
            $halfTime = ($config['time_seconds'] * 1000) / 2;
            if ($responseTime < $halfTime) {
                $points += (int) $config['points_bonus'];
            }
        }
        
        // Registrar respuesta
        $this->answerModel->record([
            'session_id'        => $sessionId,
            'question_id'       => $questionId,
            'selected_answer'   => $selectedAnswer,
            'is_correct'        => $isCorrect,
            'response_time_ms'  => $responseTime,
            'difficulty_at_time'=> $session['current_difficulty'],
        ]);
        $allAnswers     = $this->sessionModel->getAnswers($sessionId);
        // Calcular racha actual
        $streak = 0;
        foreach (array_reverse($allAnswers) as $ans) {
            if ($ans['is_correct']) {
                $streak++;
            } else {
                break;
            }
        }

        // Bonus por racha (se suma a los puntos ya calculados)
        if ($isCorrect) {
            if ($streak >= 5) {
                $points = (int) round($points * 2);    // x2 desde racha 5
            } elseif ($streak >= 3) {
                $points = (int) round($points * 1.5);  // x1.5 desde racha 3
            }
        }

        // Motor adaptativo
        $recentAnswers = $this->answerModel->getRecent($sessionId, 5);
        $newDifficulty = AdaptiveEngine::getNextDifficulty(
            $recentAnswers,
            $session['current_difficulty']
        );

        if ($newDifficulty !== $session['current_difficulty']) {
            $this->sessionModel->updateDifficulty($sessionId, $newDifficulty);
        }

        $allAnswers    = $this->sessionModel->getAnswers($sessionId);
        $answeredIds   = array_column($allAnswers, 'question_id');
        $totalAnswered = count($allAnswers);

        // Calcular vidas restantes desde la BD — fuente de verdad
        $incorrectCount = count(array_filter($allAnswers, fn($a) => !$a['is_correct']));
        $livesRemaining = (int) $config['lives'] - $incorrectCount;

        $maxQuestions = (int) $config['questions'];
        $gameOver     = ($livesRemaining <= 0) || ($totalAnswered >= $maxQuestions);
        $nextQuestion = null;

        if (!$gameOver) {
            // Obtener idioma de la sesión actual
            $language = $request->input('language', 'es');
            $nextQuestion = $this->getNextQuestion($sessionId, $newDifficulty, $answeredIds, null, $language);
            if (!$nextQuestion) $gameOver = true;
        }

        if ($gameOver) {
            $correctCount = count(array_filter($allAnswers, fn($a) => $a['is_correct']));
            $total        = count($allAnswers);

            $this->sessionModel->close($sessionId, $correctCount, $total);
            if ($gameOver) {
                $correctCount = count(array_filter($allAnswers, fn($a) => $a['is_correct']));
                $total        = count($allAnswers);

                $this->sessionModel->close($sessionId, $correctCount, $total);

                $userModel = new \UserModel();
                $userModel->updateStats($payload['sub'], $correctCount);

                // Guardar en test_results si es pretest o posttest
                /*if (in_array($session['session_type'], ['pretest', 'posttest'], true)) {
                    $db = \Database::connect();

                    $knowledgeGain = null;
                    if ($session['session_type'] === 'posttest') {
                        $stmt = $db->prepare(
                            "SELECT score, total_questions FROM game_sessions
                            WHERE user_id = ? AND session_type = 'pretest'
                            AND ended_at IS NOT NULL
                            ORDER BY ended_at DESC LIMIT 1"
                        );
                        $stmt->execute([$payload['sub']]);
                        $pretest = $stmt->fetch();

                        if ($pretest && $pretest['total_questions'] > 0) {
                            $pretestPct    = ($pretest['score'] / $pretest['total_questions']) * 100;
                            $posttestPct   = $total > 0 ? ($correctCount / $total) * 100 : 0;
                            $knowledgeGain = round($posttestPct - $pretestPct, 2);
                        }
                    }

                    $stmt = $db->prepare(
                        "INSERT INTO test_results (user_id, test_type, score, knowledge_gain, applied_at)
                        VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([
                        $payload['sub'],
                        $session['session_type'],
                        $correctCount,
                        $knowledgeGain
                    ]);
                }*/

                Response::success([
                    'feedback'        => $question['feedback_text'],
                    'correct'         => $isCorrect,
                    'correct_answer'  => $question['correct_answer'],
                    'game_over'       => true,
                    'points_earned'   => $points,
                    'score'           => $correctCount,
                    'total'           => $total,
                    'percentage'      => $total > 0 ? round(($correctCount / $total) * 100, 1) : 0,
                    'reason'          => $livesRemaining <= 0 ? 'no_lives' : 'completed',
                    'lives_remaining' => $livesRemaining,
                    'streak'          => $streak, 
                ], 'Sesión finalizada');
            }
            $userModel = new \UserModel();
            $userModel->updateStats($payload['sub'], $correctCount);

            Response::success([
                'feedback'        => $question['feedback_text'],
                'correct'         => $isCorrect,
                'correct_answer'  => $question['correct_answer'],
                'game_over'       => true,
                'points_earned'   => $points,
                'score'           => $correctCount,
                'total'           => $total,
                'percentage'      => $total > 0 ? round(($correctCount / $total) * 100, 1) : 0,
                'reason'          => $livesRemaining <= 0 ? 'no_lives' : 'completed',
                'lives_remaining' => $livesRemaining,
            ], 'Sesión finalizada');
        }

        Response::success([
            'feedback'        => $question['feedback_text'],
            'correct'         => $isCorrect,
            'correct_answer'  => $question['correct_answer'],
            'game_over'       => false,
            'points_earned'   => $points,
            'new_difficulty'  => $newDifficulty,
            'next_question'   => $this->formatQuestion($nextQuestion),
            'lives_remaining' => $livesRemaining,
            'streak'          => $streak,
        ]);
    }

    /**
     * GET /api/game/result?session_id=1
     */
    public function result(Request $request, array $payload): void {
        $sessionId = (int)($request->query['session_id'] ?? 0);

        if (!$sessionId) {
            Response::error('session_id requerido');
        }

        $session = $this->sessionModel->findById($sessionId);

        if (!$session) {
            Response::notFound('Sesión no encontrada');
        }

        if ($session['user_id'] !== $payload['sub']) {
            Response::forbidden();
        }

        $answers = $this->sessionModel->getAnswers($sessionId);
        $correct = count(array_filter($answers, fn($a) => $a['is_correct']));
        $total   = count($answers);

        Response::success([
            'session'    => $session,
            'score'      => $correct,
            'total'      => $total,
            'percentage' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            'answers'    => $answers,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────

    private function getNextQuestion(
        int $sessionId,
        string $difficulty,
        array $exclude,
        ?array $allowedCategories = null,
        string $language = 'es'
    ): ?array {
        $questions = $this->questionModel->getByDifficulty(
            $difficulty, 1, $exclude, $allowedCategories, $language
        );

        if (empty($questions)) {
            $fallbacks = array_diff(['easy', 'medium', 'hard'], [$difficulty]);
            foreach ($fallbacks as $fallback) {
                $questions = $this->questionModel->getByDifficulty(
                    $fallback, 1, $exclude, $allowedCategories, $language
                );
                if (!empty($questions)) break;
            }
        }

        return $questions[0] ?? null;
    }

    private function formatQuestion(array $question): array {
        return [
            'id'            => $question['id'],
            'question_text' => $question['question_text'],
            'option_a'      => $question['option_a'],
            'option_b'      => $question['option_b'],
            'option_c'      => $question['option_c'],
            'option_d'      => $question['option_d'],
            'difficulty'    => $question['difficulty'],
            'category_id'   => $question['category_id'],
        ];
    }
}