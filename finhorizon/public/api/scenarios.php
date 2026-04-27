<?php
/**
 * ФинГоризонт - API для работы со сценариями
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Scenario.php';

try {
    $scenarioModel = new Scenario($pdo);
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Получение списка сценариев пользователя
                requireAuth();
                $userId = getCurrentUserId();
                $scenarios = $scenarioModel->getAllByUser($userId);
                echo json_encode(['success' => true, 'data' => $scenarios]);
            } elseif ($action === 'get' && isset($_GET['id'])) {
                // Получение конкретного сценария
                requireAuth();
                $userId = getCurrentUserId();
                $id = (int)$_GET['id'];
                $scenario = $scenarioModel->getById($id, $userId);
                
                if ($scenario) {
                    echo json_encode(['success' => true, 'data' => $scenario]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Сценарий не найден']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Неверный запрос']);
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                // Создание нового сценария
                requireAuth();
                $userId = getCurrentUserId();
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['name'], $input['start_date'], $input['end_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Необходимо указать название, дату начала и дату окончания']);
                    break;
                }
                
                $id = $scenarioModel->create(
                    $userId,
                    $input['name'],
                    $input['description'] ?? '',
                    $input['start_date'],
                    $input['end_date']
                );
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Сценарий создан',
                    'id' => $id
                ]);
            } elseif ($action === 'update' && isset($_GET['id'])) {
                // Обновление сценария
                requireAuth();
                $userId = getCurrentUserId();
                $id = (int)$_GET['id'];
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['name'], $input['start_date'], $input['end_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Необходимо указать название, дату начала и дату окончания']);
                    break;
                }
                
                $success = $scenarioModel->update(
                    $id,
                    $userId,
                    $input['name'],
                    $input['description'] ?? '',
                    $input['start_date'],
                    $input['end_date']
                );
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Сценарий обновлен']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Сценарий не найден или не принадлежит пользователю']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Неверный запрос']);
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete' && isset($_GET['id'])) {
                // Удаление сценария
                requireAuth();
                $userId = getCurrentUserId();
                $id = (int)$_GET['id'];
                
                $success = $scenarioModel->delete($id, $userId);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Сценарий удален']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Сценарий не найден или не принадлежит пользователю']);
                }
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
