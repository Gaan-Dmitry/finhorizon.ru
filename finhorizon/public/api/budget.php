<?php
/**
 * ФинГоризонт - API для работы с бюджетом и прогнозированием
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Budget.php';

try {
    $budgetModel = new Budget($pdo);
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'categories' && isset($_GET['scenario_id'])) {
                // Получение категорий бюджета
                requireAuth();
                $userId = getCurrentUserId();
                $scenarioId = (int)$_GET['scenario_id'];
                
                // Проверяем принадлежность сценария пользователю
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $scenarioId]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $type = $_GET['type'] ?? null;
                $categories = $budgetModel->getCategories($userId, $type);
                echo json_encode(['success' => true, 'data' => $categories]);
                
            } elseif ($action === 'items' && isset($_GET['scenario_id'])) {
                // Получение статей бюджета
                requireAuth();
                $userId = getCurrentUserId();
                $scenarioId = (int)$_GET['scenario_id'];
                
                // Проверка доступа
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $scenarioId]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $startDate = $_GET['start_date'] ?? null;
                $endDate = $_GET['end_date'] ?? null;
                
                $items = $budgetModel->getItems($scenarioId, $startDate, $endDate);
                echo json_encode(['success' => true, 'data' => $items]);
                
            } elseif ($action === 'summary' && isset($_GET['scenario_id'])) {
                // Получение сводных данных
                requireAuth();
                $userId = getCurrentUserId();
                $scenarioId = (int)$_GET['scenario_id'];
                
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $scenarioId]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $summary = $budgetModel->getSummary($scenarioId);
                echo json_encode(['success' => true, 'data' => $summary]);
                
            } elseif ($action === 'forecasts' && isset($_GET['scenario_id'])) {
                // Получение прогнозов
                requireAuth();
                $userId = getCurrentUserId();
                $scenarioId = (int)$_GET['scenario_id'];
                
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $scenarioId]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $forecasts = $budgetModel->getForecasts($scenarioId);
                echo json_encode(['success' => true, 'data' => $forecasts]);
                
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Неверный запрос']);
            }
            break;
            
        case 'POST':
            if ($action === 'category') {
                // Создание категории
                requireAuth();
                $userId = getCurrentUserId();
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['name'], $input['type'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Необходимо указать название и тип']);
                    break;
                }
                
                if (!in_array($input['type'], ['income', 'expense'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Неверный тип категории']);
                    break;
                }
                
                $id = $budgetModel->createCategory(
                    $userId,
                    $input['name'],
                    $input['type'],
                    $input['parent_id'] ?? null
                );
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Категория создана',
                    'id' => $id
                ]);
                
            } elseif ($action === 'item') {
                // Добавление/обновление статьи бюджета
                requireAuth();
                $userId = getCurrentUserId();
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['scenario_id'], $input['category_id'], $input['period'], $input['amount'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Необходимо указать scenario_id, category_id, period и amount']);
                    break;
                }
                
                // Проверка доступа к сценарию
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $input['scenario_id']]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $success = $budgetModel->upsertItem(
                    $input['scenario_id'],
                    $input['category_id'],
                    $input['period'],
                    $input['amount'],
                    $input['comment'] ?? null
                );
                
                echo json_encode(['success' => true, 'message' => 'Данные сохранены']);
                
            } elseif ($action === 'generate_forecast' && isset($_GET['scenario_id'])) {
                // Генерация прогнозов
                requireAuth();
                $userId = getCurrentUserId();
                $scenarioId = (int)$_GET['scenario_id'];
                
                $scenarioStmt = $pdo->prepare("SELECT user_id FROM scenarios WHERE id = :id");
                $scenarioStmt->execute(['id' => $scenarioId]);
                $scenario = $scenarioStmt->fetch();
                
                if (!$scenario || $scenario['user_id'] != $userId) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Доступ запрещен']);
                    break;
                }
                
                $periodsAhead = (int)($_GET['periods'] ?? 3);
                $forecasts = $budgetModel->generateAllForecasts($scenarioId, $periodsAhead);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Прогноз сгенерирован',
                    'data' => $forecasts,
                    'count' => count($forecasts)
                ]);
                
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Неверный запрос']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутренняя ошибка сервера']);
}
