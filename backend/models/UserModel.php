<?php

class UserModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
public function findById(int $id): ?array {
    $stmt = $this->db->prepare(
        'SELECT id, name, age, email, role, semester, group_type,
                total_score, total_games, best_streak, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

public function updatePassword(int $id, string $newPassword): void {
    $this->db->prepare(
        'UPDATE users SET password = ? WHERE id = ?'
    )->execute([
        password_hash($newPassword, PASSWORD_BCRYPT),
        $id
    ]);
}

public function createStudent(string $name, int $age): int {
    $stmt = $this->db->prepare(
        'INSERT INTO users (name, age, role, group_type)
         VALUES (:name, :age, :role, :group_type)'
    );
    $stmt->execute([
        ':name'       => $name,
        ':age'        => $age,
        ':role'       => 'student',
        ':group_type' => 'free',  // ← antes era 'experimental'
    ]);
    return (int) $this->db->lastInsertId();
}
    // Crear admin — con email y password
    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role, semester, group_type)
             VALUES (:name, :email, :password, :role, :semester, :group_type)'
        );
        $stmt->execute([
            ':name'       => $data['name'],
            ':email'      => $data['email'],
            ':password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'       => $data['role']       ?? 'student',
            ':semester'   => $data['semester']   ?? null,
            ':group_type' => $data['group_type'] ?? 'experimental',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createStudentWithRoom(string $name, int $roomId, string $groupType, int $age = 0): int {
        $db   = Database::connect();
        $stmt = $db->prepare("INSERT INTO users (name, age, role, room_id, group_type) VALUES (?, ?, 'student', ?, ?)");
        $stmt->execute([$name, $age, $roomId, $groupType]);
        return (int)$db->lastInsertId();
    }

    // Actualizar stats después de cada sesión
    public function updateStats(int $userId, int $score): void {
        $this->db->prepare(
            'UPDATE users SET
                total_score = total_score + :score,
                total_games = total_games + 1
             WHERE id = :id'
        )->execute([':score' => $score, ':id' => $userId]);
    }

    public function findByNameAndAge(string $name, int $age): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM users
            WHERE role = "student"
            AND LOWER(name) = LOWER(:name)
            AND age = :age
            LIMIT 1'
        );
        $stmt->execute([
            ':name' => $name,
            ':age'  => $age,
        ]);
        return $stmt->fetch() ?: null;
    }

    // Buscar estudiante por nombre exacto
    public function findByName(string $name): ?array {
        $db   = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE name = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // Actualizar sala, grupo y edad
    public function updateRoomAndAge(int $id, int $roomId, string $groupType, int $age): void {
        $db   = Database::connect();
        $stmt = $db->prepare("UPDATE users SET room_id = ?, group_type = ?, age = ? WHERE id = ?");
        $stmt->execute([$roomId, $groupType, $age, $id]);
    }
}