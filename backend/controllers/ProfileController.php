<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/ResultModel.php';

class ProfileController {

    private UserModel   $userModel;
    private ResultModel $resultModel;

    public function __construct() {
        $this->userModel   = new UserModel();
        $this->resultModel = new ResultModel();
    }

    /**
     * GET /api/profile
     * Perfil completo del estudiante autenticado
     */
    public function me(Request $request, array $payload): void {
        $userId = $payload['sub'];
        $user   = $this->userModel->findById($userId);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        $db = \Database::connect();

        // Stats generales
        $stmt = $db->prepare(
            'SELECT
                COUNT(DISTINCT gs.id)                        AS total_games,
                SUM(sa.is_correct)                           AS total_correct,
                COUNT(sa.id)                                 AS total_answers,
                ROUND(AVG(sa.response_time_ms) / 1000, 1)   AS avg_time_sec,
                MAX(gs.score)                                AS best_score
             FROM game_sessions gs
             LEFT JOIN session_answers sa ON sa.session_id = gs.id
             WHERE gs.user_id = ? AND gs.session_type = "game"'
        );
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();

        $totalAnswers  = (int) ($stats['total_answers']  ?? 0);
        $totalCorrect  = (int) ($stats['total_correct']  ?? 0);
        $precision     = $totalAnswers > 0
            ? round(($totalCorrect / $totalAnswers) * 100, 1)
            : 0;

        // Puntuación total acumulada
        $stmtScore = $db->prepare(
            'SELECT COALESCE(SUM(score), 0) AS total_score
             FROM game_sessions
             WHERE user_id = ? AND session_type = "game" AND ended_at IS NOT NULL'
        );
        $stmtScore->execute([$userId]);
        $totalScore = (int) $stmtScore->fetchColumn();
        // Errores — preguntas respondidas incorrectamente con detalle
        $stmtErrors = $db->prepare(
        'SELECT
            q.question_text,
            sa.selected_answer,
            q.correct_answer,
            q.feedback_text,
            c.name AS category_name,
            MAX(sa.answered_at) AS answered_at
        FROM session_answers sa
        JOIN questions q ON q.id = sa.question_id
        JOIN game_sessions gs ON gs.id = sa.session_id
        LEFT JOIN categories c ON c.id = q.category_id
        WHERE gs.user_id = ? AND sa.is_correct = 0
        GROUP BY q.id, q.question_text, sa.selected_answer, q.correct_answer, q.feedback_text, c.name
        ORDER BY answered_at DESC
        LIMIT 10'
    );
    $stmtErrors->execute([$userId]);
    $errors = $stmtErrors->fetchAll();

        // Desempeño por categoría
        $stmtCats = $db->prepare(
            'SELECT
                c.name                                        AS category,
                COUNT(sa.id)                                 AS total,
                SUM(sa.is_correct)                           AS correct,
                ROUND(AVG(sa.response_time_ms) / 1000, 1)   AS avg_time
             FROM session_answers sa
             JOIN questions q ON q.id = sa.question_id
             JOIN categories c ON c.id = q.category_id
             JOIN game_sessions gs ON gs.id = sa.session_id
             WHERE gs.user_id = ?
             GROUP BY c.id, c.name'
        );
        $stmtCats->execute([$userId]);
        $categories = $stmtCats->fetchAll();

        // Formatear categorías
        $categoryStats = array_map(function ($cat) {
            $total   = (int) $cat['total'];
            $correct = (int) $cat['correct'];
            return [
                'category'  => $cat['category'],
                'total'     => $total,
                'correct'   => $correct,
                'precision' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
                'avg_time'  => (float) $cat['avg_time'],
            ];
        }, $categories);

        Response::success([
            'user' => [
                'id'         => $user['id'],
                'name'       => $user['name'],
                'age'        => $user['age'],
                'role'       => $user['role'],
                'created_at' => $user['created_at'],
            ],
            'stats' => [
                'total_games'   => (int) ($stats['total_games'] ?? 0),
                'total_score'   => $totalScore,
                'precision'     => $precision,
                'avg_time_sec'  => (float) ($stats['avg_time_sec'] ?? 0),
                'best_score'    => (int) ($stats['best_score'] ?? 0),
            ],
            'errors'     => $errors,
            'categories' => $categoryStats,
        ]);
    }
}