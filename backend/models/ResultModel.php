<?php

class ResultModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function save(int $userId, string $type, float $score): int {
        $stmt = $this->db->prepare(
            'INSERT INTO test_results (user_id, test_type, score) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $score]);
        return (int) $this->db->lastInsertId();
    }

    public function getByUser(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM test_results WHERE user_id = ? ORDER BY applied_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getGroupStats(): array {
        $stmt = $this->db->prepare(
            'SELECT u.group_type, tr.test_type,
                    AVG(tr.score) as avg_score,
                    COUNT(tr.id) as total
             FROM test_results tr
             JOIN users u ON u.id = tr.user_id
             GROUP BY u.group_type, tr.test_type'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}