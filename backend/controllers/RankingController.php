<?php

require_once __DIR__ . '/../models/GameConfigModel.php';

class RankingController {

    /**
     * GET /api/ranking
     */
    public function index(Request $request, array $payload = []): void {
    $db = Database::connect();

    $stmt = $db->prepare(
        'SELECT
            u.id,
            u.name,
            u.age,
            COUNT(DISTINCT gs.id)                        AS partidas,
            MAX(gs.score)                                AS max_puntaje,
            COALESCE(SUM(gs.score), 0)                   AS puntos_totales,
            ROUND(
                COALESCE(SUM(sa_correct.correct_count), 0) * 100.0 /
                NULLIF(COUNT(sa_all.id), 0)
            , 2)                                         AS `precision`
         FROM users u
         LEFT JOIN game_sessions gs
            ON gs.user_id = u.id
            AND gs.session_type = "game"
            AND gs.ended_at IS NOT NULL
         LEFT JOIN session_answers sa_all
            ON sa_all.session_id = gs.id
         LEFT JOIN (
            SELECT session_id, COUNT(*) AS correct_count
            FROM session_answers
            WHERE is_correct = 1
            GROUP BY session_id
         ) sa_correct ON sa_correct.session_id = gs.id
         WHERE u.role = "student"
         GROUP BY u.id, u.name, u.age
         ORDER BY puntos_totales DESC, `precision` DESC
         LIMIT 50'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

        $ranking = array_map(function ($row, $index) {
            return [
                'position'       => $index + 1,
                'id'             => (int)   $row['id'],
                'name'           => $row['name'],
                'age'            => (int)   $row['age'],
                'partidas'       => (int)   $row['partidas'],
                'max_puntaje'    => (int)   $row['max_puntaje'],
                'puntos_totales' => (int)   $row['puntos_totales'],
                'precision'      => (float) $row['precision'],
            ];
        }, $rows, array_keys($rows));

        // Detectar usuario autenticado sin usar 'use' dentro de función
        $myPosition = null;
        $token = $request->bearerToken();

        if ($token) {
            try {
                $secret  = $_ENV['JWT_SECRET'] ?? 'secret';
                $decoded = \Firebase\JWT\JWT::decode(
                    $token,
                    new \Firebase\JWT\Key($secret, 'HS256')
                );
                $myId = $decoded->sub ?? null;

                if ($myId) {
                    foreach ($ranking as $item) {
                        if ($item['id'] === (int) $myId) {
                            $myPosition = $item;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Token inválido — no pasa nada
            }
        }

        Response::success([
            'ranking'     => $ranking,
            'my_position' => $myPosition,
            'total'       => count($ranking),
        ]);
    }
}