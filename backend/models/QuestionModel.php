<?php

class QuestionModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function getByDifficulty(string $difficulty, int $limit = 5, array $exclude = []): array {
        $placeholders = $exclude ? implode(',', array_fill(0, count($exclude), '?')) : '0';
        $sql = "SELECT * FROM questions
                WHERE difficulty = ? AND is_active = 1 AND id NOT IN ($placeholders)
                ORDER BY RAND() LIMIT $limit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$difficulty], $exclude));
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM questions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;

    // Castear tipos correctamente
    $row['id']          = (int)  $row['id'];
    $row['category_id'] = (int)  $row['category_id'];
    $row['is_active']   = (bool) $row['is_active'];
    return $row;
}

    public function all(array $filters = []): array {
        $where = 'WHERE 1=1';
        $params = [];
        if (!empty($filters['difficulty'])) {
            $where .= ' AND q.difficulty = ?';
            $params[] = $filters['difficulty'];
        }
        if (!empty($filters['category_id'])) {
            $where .= ' AND q.category_id = ?';
            $params[] = $filters['category_id'];
        }
        $sql = "SELECT q.*, c.name as category_name
                FROM questions q
                LEFT JOIN categories c ON c.id = q.category_id
                $where ORDER BY q.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO questions
             (category_id, difficulty, question_text, option_a, option_b, option_c, option_d, correct_answer, feedback_text)
             VALUES (:cat, :diff, :text, :a, :b, :c, :d, :ans, :feedback)'
        );
        $stmt->execute([
            ':cat'      => $data['category_id'],
            ':diff'     => $data['difficulty'],
            ':text'     => $data['question_text'],
            ':a'        => $data['option_a'],
            ':b'        => $data['option_b'],
            ':c'        => $data['option_c'],
            ':d'        => $data['option_d'],
            ':ans'      => $data['correct_answer'],
            ':feedback' => $data['feedback_text'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE questions SET
             category_id=:cat, difficulty=:diff, question_text=:text,
             option_a=:a, option_b=:b, option_c=:c, option_d=:d,
             correct_answer=:ans, feedback_text=:feedback
             WHERE id=:id'
        );
        return $stmt->execute([
            ':cat'      => $data['category_id'],
            ':diff'     => $data['difficulty'],
            ':text'     => $data['question_text'],
            ':a'        => $data['option_a'],
            ':b'        => $data['option_b'],
            ':c'        => $data['option_c'],
            ':d'        => $data['option_d'],
            ':ans'      => $data['correct_answer'],
            ':feedback' => $data['feedback_text'] ?? null,
            ':id'       => $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('UPDATE questions SET is_active = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }
}