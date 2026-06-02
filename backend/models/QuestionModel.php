<?php

class QuestionModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function getByDifficulty(
        string $difficulty,
        int $limit = 5,
        array $exclude = [],
        ?array $allowedCategories = null,
        string $language = 'es'
    ): array {
        $excludePlaceholders = $exclude
            ? implode(',', array_fill(0, count($exclude), '?'))
            : '0';

        $categoryFilter = '';
        $categoryParams = [];

        if (!empty($allowedCategories)) {
            $categoryPlaceholders = implode(',', array_fill(0, count($allowedCategories), '?'));
            $categoryFilter = "AND category_id IN ($categoryPlaceholders)";
            $categoryParams = $allowedCategories;
        }

        $sql = "SELECT * FROM questions
                WHERE difficulty = ?
                AND is_active = 1
                AND language = ?
                AND id NOT IN ($excludePlaceholders)
                $categoryFilter
                ORDER BY RAND()
                LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(
            [$difficulty, $language],
            $exclude,
            $categoryParams
        ));

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM questions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $row['id']          = (int)  $row['id'];
        $row['category_id'] = (int)  $row['category_id'];
        $row['is_active']   = (bool) $row['is_active'];
        return $row;
    }

    public function all(array $filters = []): array {
        $where  = 'WHERE 1=1';
        $params = [];
        $lang   = $filters['language'] ?? 'es';

        if (!empty($filters['difficulty'])) {
            $where   .= ' AND q.difficulty = ?';
            $params[] = $filters['difficulty'];
        }
        if (!empty($filters['category_id'])) {
            $where   .= ' AND q.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['language'])) {
            $where   .= ' AND q.language = ?';
            $params[] = $filters['language'];
        }

        // Si idioma es inglés usar name_en, si no tiene usar name
        $categoryName = $lang === 'en'
            ? 'COALESCE(NULLIF(c.name_en, ""), c.name)'
            : 'c.name';

        $sql  = "SELECT q.*, $categoryName as category_name
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
             (category_id, difficulty, question_text, option_a, option_b,
              option_c, option_d, correct_answer, feedback_text, language)
             VALUES (:cat, :diff, :text, :a, :b, :c, :d, :ans, :feedback, :lang)'
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
            ':lang'     => $data['language'] ?? 'es',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE questions SET
             category_id=:cat, difficulty=:diff, question_text=:text,
             option_a=:a, option_b=:b, option_c=:c, option_d=:d,
             correct_answer=:ans, feedback_text=:feedback, language=:lang
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
            ':lang'     => $data['language'] ?? 'es',
            ':id'       => $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('UPDATE questions SET is_active = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }
}