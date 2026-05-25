<?php

class AnswerModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function record(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO session_answers
             (session_id, question_id, selected_answer, is_correct, response_time_ms, difficulty_at_time)
             VALUES (:session, :question, :selected, :correct, :time, :diff)'
        );
        $stmt->execute([
            ':session'  => $data['session_id'],
            ':question' => $data['question_id'],
            ':selected' => $data['selected_answer'],
            ':correct'  => $data['is_correct'] ? 1 : 0,
            ':time'     => $data['response_time_ms'],
            ':diff'     => $data['difficulty_at_time'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getRecent(int $sessionId, int $n = 5): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM session_answers WHERE session_id = ?
             ORDER BY answered_at DESC LIMIT ?'
        );
        $stmt->execute([$sessionId, $n]);
        return array_reverse($stmt->fetchAll());
    }
}