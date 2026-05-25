<?php

class GameConfigModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function get(): array {
        $stmt = $this->db->query('SELECT * FROM game_config LIMIT 1');
        return $stmt->fetch() ?: [
            'lives'          => 3,
            'questions'      => 16,
            'time_seconds'   => 15,
            'points_correct' => 10,
            'points_bonus'   => 5,
        ];
    }

    public function update(array $data): void {
        $this->db->prepare(
            'UPDATE game_config SET
                lives          = :lives,
                questions      = :questions,
                time_seconds   = :time_seconds,
                points_correct = :points_correct,
                points_bonus   = :points_bonus
             WHERE id = 1'
        )->execute([
            ':lives'          => (int) $data['lives'],
            ':questions'      => (int) $data['questions'],
            ':time_seconds'   => (int) $data['time_seconds'],
            ':points_correct' => (int) $data['points_correct'],
            ':points_bonus'   => (int) $data['points_bonus'],
        ]);
    }
}