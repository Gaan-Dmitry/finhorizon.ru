<?php
/**
 * ФинГоризонт - API для управления сценариями
 */

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Требуется авторизация']);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            // Получение списка сценариев пользователя
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    COUNT(bi.id) as items_count,
                    SUM(CASE WHEN bi.type = 'income' THEN bi.amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN bi.type = 'expense' THEN bi.amount ELSE 0 END) as total_expense
                FROM scenarios s
                LEFT JOIN budget_items bi ON s.id = bi.scenario_id
                WHERE s.user_id = ?
                GROUP BY s.id
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $scenarios = $stmt->fetchAll();
            
            jsonResponse(['success' => true, 'data' => $scenarios]);
            
            break;
            
        case 'get':
            // Получение одного сценария
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM scenarios 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $scenario = $stmt->fetch();
            
            if (!$scenario) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }
            
            jsonResponse(['success' => true, 'data' => $scenario]);
            
            break;
            
        case 'create':
            // Создание нового сценария
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Валидация
            $required = ['name', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    jsonResponse(['success' => false, 'error' => "Поле '$field' обязательно"]);
                }
            }
            
            // Проверка дат
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                jsonResponse(['success' => false, 'error' => 'Дата начала должна быть раньше даты окончания']);
            }
            
            // Создание
            $stmt = $pdo->prepare("
                INSERT INTO scenarios (user_id, name, description, start_date, end_date, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['is_active'] ?? 1
            ]);
            
            $newId = $pdo->lastInsertId();
            jsonResponse(['success' => true, 'message' => 'Сценарий создан', 'id' => $newId]);
            
            break;
            
        case 'update':
            // Обновление сценария
            if ($method !== 'PUT' && $method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            // Проверка прав
            $stmt = $pdo->prepare("SELECT id FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE scenarios 
                SET name = ?, description = ?, start_date = ?, end_date = ?, is_active = ?
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['is_active'] ?? 1,
                $id,
                $_SESSION['user_id']
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Сценарий обновлен']);
            
            break;
            
        case 'delete':
            // Удаление сценария
            if ($method !== 'DELETE' && $method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            // Проверка прав
            $stmt = $pdo->prepare("SELECT id FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }
            
            // Удаление (каскадно удалит и статьи бюджета)
            $stmt = $pdo->prepare("DELETE FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            
            jsonResponse(['success' => true, 'message' => 'Сценарий удален']);
            
            break;
            
        case 'deactivate':
            // Деактивация сценария
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            $stmt = $pdo->prepare("UPDATE scenarios SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            
            jsonResponse(['success' => true, 'message' => 'Сценарий деактивирован']);
            
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка БД: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка базы данных']);
}
