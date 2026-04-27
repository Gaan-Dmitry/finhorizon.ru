<?php
/**
 * ФинГоризонт - Модель для работы со сценариями
 */

require_once __DIR__ . '/../config/database.php';

class Scenario {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Создание нового сценария
     */
    public function create($userId, $name, $description, $startDate, $endDate) {
        $sql = "INSERT INTO scenarios (user_id, name, description, start_date, end_date) 
                VALUES (:user_id, :name, :description, :start_date, :end_date)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Получение всех сценариев пользователя
     */
    public function getAllByUser($userId) {
        $sql = "SELECT * FROM scenarios WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение сценария по ID
     */
    public function getById($id, $userId) {
        $sql = "SELECT * FROM scenarios WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch();
    }
    
    /**
     * Обновление сценария
     */
    public function update($id, $userId, $name, $description, $startDate, $endDate) {
        $sql = "UPDATE scenarios SET 
                name = :name, 
                description = :description, 
                start_date = :start_date, 
                end_date = :end_date 
                WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }
    
    /**
     * Удаление сценария
     */
    public function delete($id, $userId) {
        $sql = "DELETE FROM scenarios WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
