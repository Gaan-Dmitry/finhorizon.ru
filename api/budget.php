<?php
/**
 * ФинГоризонт - API для работы со статьями бюджета
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
            // Получение списка статей бюджета
            $scenarioId = $_GET['scenario_id'] ?? null;
            
            if (!$scenarioId) {
                // Получаем все сценарии пользователя
                $stmt = $pdo->prepare("
                    SELECT bi.*, s.name as scenario_name 
                    FROM budget_items bi
                    JOIN scenarios s ON bi.scenario_id = s.id
                    WHERE s.user_id = ?
                    ORDER BY bi.date DESC
                ");
                $stmt->execute([$_SESSION['user_id']]);
            } else {
                // Получаем статьи конкретного сценария
                $stmt = $pdo->prepare("
                    SELECT * FROM budget_items 
                    WHERE scenario_id = ?
                    ORDER BY date DESC
                ");
                $stmt->execute([$scenarioId]);
            }
            
            $items = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $items]);
            
            break;
            
        case 'get':
            // Получение одной статьи
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            $stmt = $pdo->prepare("
                SELECT bi.*, s.name as scenario_name 
                FROM budget_items bi
                JOIN scenarios s ON bi.scenario_id = s.id
                WHERE bi.id = ? AND s.user_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $item = $stmt->fetch();
            
            if (!$item) {
                jsonResponse(['success' => false, 'error' => 'Статья не найдена']);
            }
            
            jsonResponse(['success' => true, 'data' => $item]);
            
            break;
            
        case 'create':
            // Создание новой статьи
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Валидация
            $required = ['scenario_id', 'name', 'type', 'amount', 'date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    jsonResponse(['success' => false, 'error' => "Поле '$field' обязательно"]);
                }
            }
            
            // Проверка прав на сценарий
            $stmt = $pdo->prepare("SELECT id FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['scenario_id'], $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }
            
            // Создание
            $stmt = $pdo->prepare("
                INSERT INTO budget_items 
                (scenario_id, name, type, category, amount, date, is_recurring, recurrence_pattern, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['scenario_id'],
                $data['name'],
                $data['type'],
                $data['category'] ?? null,
                $data['amount'],
                $data['date'],
                $data['is_recurring'] ?? 0,
                $data['recurrence_pattern'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $newId = $pdo->lastInsertId();
            jsonResponse(['success' => true, 'message' => 'Статья добавлена', 'id' => $newId]);
            
            break;
            
        case 'update':
            // Обновление статьи
            if ($method !== 'PUT' && $method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            // Проверка прав
            $stmt = $pdo->prepare("
                SELECT bi.id FROM budget_items bi
                JOIN scenarios s ON bi.scenario_id = s.id
                WHERE bi.id = ? AND s.user_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Статья не найдена']);
            }
            
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE budget_items 
                SET name = ?, type = ?, category = ?, amount = ?, 
                    date = ?, is_recurring = ?, recurrence_pattern = ?, notes = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['type'],
                $data['category'] ?? null,
                $data['amount'],
                $data['date'],
                $data['is_recurring'] ?? 0,
                $data['recurrence_pattern'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Статья обновлена']);
            
            break;
            
        case 'delete':
            // Удаление статьи
            if ($method !== 'DELETE' && $method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? $_GET['id'] ?? null;
            
            if (!$id) {
                jsonResponse(['success' => false, 'error' => 'Не указан ID']);
            }
            
            // Проверка прав
            $stmt = $pdo->prepare("
                SELECT bi.id FROM budget_items bi
                JOIN scenarios s ON bi.scenario_id = s.id
                WHERE bi.id = ? AND s.user_id = ?
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Статья не найдена']);
            }
            
            // Удаление
            $stmt = $pdo->prepare("DELETE FROM budget_items WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['success' => true, 'message' => 'Статья удалена']);
            
            break;
            
        case 'summary':
            // Получение сводки по бюджету
            $scenarioId = $_GET['scenario_id'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            if (!$scenarioId) {
                jsonResponse(['success' => false, 'error' => 'Не указан сценарий']);
            }
            
            $where = "scenario_id = ?";
            $params = [$scenarioId];
            
            if ($startDate) {
                $where .= " AND date >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $where .= " AND date <= ?";
                $params[] = $endDate;
            }
            
            // Общие суммы
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                    COUNT(*) as total_items
                FROM budget_items
                WHERE $where
            ");
            $stmt->execute($params);
            $summary = $stmt->fetch();
            
            // Группировка по категориям
            $stmt = $pdo->prepare("
                SELECT 
                    category,
                    type,
                    SUM(amount) as amount,
                    COUNT(*) as count
                FROM budget_items
                WHERE $where
                GROUP BY category, type
                ORDER BY amount DESC
            ");
            $stmt->execute($params);
            $byCategory = $stmt->fetchAll();
            
            // Группировка по месяцам
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                FROM budget_items
                WHERE $where
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute($params);
            $byMonth = $stmt->fetchAll();
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'by_category' => $byCategory,
                    'by_month' => $byMonth
                ]
            ]);
            
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка БД: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка базы данных']);
}
