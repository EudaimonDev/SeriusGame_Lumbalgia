<?php

class RoomModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM rooms WHERE code = ? AND is_active = 1'
        );
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function all(): array {
        return $this->db->query(
            'SELECT r.*, COUNT(u.id) AS total_students
             FROM rooms r
             LEFT JOIN users u ON u.room_id = r.id
             GROUP BY r.id
             ORDER BY r.created_at DESC'
        )->fetchAll();
    }

    public function create(
        string $code,
        string $name,
        string $groupType,
        string $phase          = 'game',
        int    $questionsCount = 10,
        string $difficulty     = 'adaptive',
        ?string $categoryIds   = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO rooms (code, name, group_type, phase, questions_count, difficulty, category_ids)
             VALUES (:code, :name, :group_type, :phase, :questions_count, :difficulty, :category_ids)'
        );
        $stmt->execute([
            ':code'            => strtoupper(trim($code)),
            ':name'            => $name,
            ':group_type'      => $groupType,
            ':phase'           => $phase,
            ':questions_count' => $questionsCount,
            ':difficulty'      => $difficulty,
            ':category_ids'    => $categoryIds,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $this->db->prepare(
            'UPDATE rooms SET
                name             = :name,
                group_type       = :group_type,
                phase            = :phase,
                questions_count  = :questions_count,
                difficulty       = :difficulty,
                category_ids     = :category_ids
             WHERE id = :id'
        )->execute([
            ':name'            => $data['name'],
            ':group_type'      => $data['group_type'],
            ':phase'           => $data['phase']           ?? 'game',
            ':questions_count' => $data['questions_count'] ?? 10,
            ':difficulty'      => $data['difficulty']      ?? 'adaptive',
            ':category_ids'    => $data['category_ids']    ?? null,
            ':id'              => $id,
        ]);
    }

    public function toggle(int $id): void {
        $this->db->prepare(
            'UPDATE rooms SET is_active = NOT is_active WHERE id = ?'
        )->execute([$id]);
    }

    public function delete(int $id): void {
        $this->db->prepare('DELETE FROM rooms WHERE id = ?')->execute([$id]);
    }
}