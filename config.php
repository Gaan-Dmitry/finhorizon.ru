<?php

declare(strict_types=1);

function loadEnvironment(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if ($name === '') {
            continue;
        }

        $_ENV[$name] ??= $value;
        $_SERVER[$name] ??= $value;
        putenv(sprintf('%s=%s', $name, $value));
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function appConfig(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    loadEnvironment(__DIR__ . '/.env');

    $config = [
        'app_name' => env('APP_NAME', 'FinHorizon'),
        'app_url' => env('APP_URL', 'http://localhost:8080'),
        'db_host' => env('DB_HOST', '127.0.0.1'),
        'db_port' => env('DB_PORT', '3306'),
        'db_name' => env('DB_DATABASE', 'finhorizon'),
        'db_user' => env('DB_USERNAME', 'finhorizon'),
        'db_pass' => env('DB_PASSWORD', 'finhorizon'),
    ];

    return $config;
}

function databaseConnection(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = appConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
