<?php
/**
 * ФинГоризонт - Конфигурация базы данных
 * Файл конфигурации для подключения к БД
 */

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'finhorizon_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('APP_NAME', 'ФинГоризонт');
define('APP_SLOGAN', 'Планируйте уверенно');
define('APP_VERSION', '1.0.0');

// Пути
define('BASE_PATH', dirname(__DIR__));
define('CSS_PATH', '/css');
define('JS_PATH', '/js');
define('API_PATH', '/api');

// Настройки сессии
ini_set('session.cookie_httponly', 1);
session_start();

// Функция подключения к БД
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Ошибка подключения к БД: " . $e->getMessage());
            die(json_encode(['success' => false, 'error' => 'Ошибка подключения к базе данных']));
        }
    }
    
    return $pdo;
}

// Функция проверки авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Функция получения текущего пользователя
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, email, company_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Функция безопасного вывода JSON
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Функция проверки токена CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Генерация токена CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
