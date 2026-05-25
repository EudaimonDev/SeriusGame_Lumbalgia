<?php

class SessionModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $userId, string $type): int {
        $stmt = $this->db->prepare(
            'INSERT INTO game_sessions (user_id, session_type) VALUES (?, ?)'
        );
        $stmt->execute([$userId, $type]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM game_sessions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateDifficulty(int $id, string $difficulty): void {
        $stmt = $this->db->prepare(
            'UPDATE game_sessions SET current_difficulty = ? WHERE id = ?'
        );
        $stmt->execute([$difficulty, $id]);
    }

    public function close(int $id, int $score, int $total): void {
        $stmt = $this->db->prepare(
            'UPDATE game_sessions SET ended_at = NOW(), score = ?, total_questions = ? WHERE id = ?'
        );
        $stmt->execute([$score, $total, $id]);
    }

    public function getAnswers(int $sessionId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM session_answers WHERE session_id = ? ORDER BY answered_at'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    }
}