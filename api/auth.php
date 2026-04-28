<?php
/**
 * ФинГоризонт - API для аутентификации
 */

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'register':
            // Регистрация нового пользователя
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Валидация
            if (empty($data['email'])) {
                jsonResponse(['success' => false, 'error' => 'Email обязателен']);
            }
            
            if (empty($data['password']) || strlen($data['password']) < 6) {
                jsonResponse(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов']);
            }
            
            // Проверка существования пользователя
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Пользователь с таким email уже существует']);
            }
            
            // Хэширование пароля
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Создание пользователя
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, company_name)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $data['email'],
                $passwordHash,
                $data['company_name'] ?? null
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Автоматическая авторизация
            $_SESSION['user_id'] = $userId;
            
            jsonResponse([
                'success' => true, 
                'message' => 'Регистрация успешна',
                'user_id' => $userId
            ]);
            
            break;
            
        case 'login':
            // Вход пользователя
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Метод не разрешен']);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['email']) || empty($data['password'])) {
                jsonResponse(['success' => false, 'error' => 'Введите email и пароль']);
            }
            
            // Поиск пользователя
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                jsonResponse(['success' => false, 'error' => 'Неверный email или пароль']);
            }
            
            // Установка сессии
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            
            jsonResponse([
                'success' => true, 
                'message' => 'Вход выполнен',
                'redirect' => '/index.php'
            ]);
            
            break;
            
        case 'logout':
            // Выход
            session_destroy();
            jsonResponse(['success' => true, 'message' => 'Выход выполнен']);
            
            break;
            
        case 'check':
            // Проверка авторизации
            jsonResponse([
                'success' => true,
                'logged_in' => isLoggedIn(),
                'user' => isLoggedIn() ? getCurrentUser() : null
            ]);
            
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка БД: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка базы данных']);
}
