<?php
/**
 * ФинГоризонт - Конфигурация базы данных
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'finhorizon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// DSN для PDO
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Опции PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // В продакшене логировать ошибку, а не выводить
    error_log("Ошибка подключения к БД: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверка авторизации пользователя
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Требуется авторизация']);
        exit;
    }
}

/**
 * Получение ID текущего пользователя
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}
