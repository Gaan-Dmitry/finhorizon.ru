# ФинГоризонт - SaaS сервис финансового планирования

## Описание
**ФинГоризонт** — это SaaS-сервис для финансового планирования и прогнозирования бюджета на основе исторических данных.

**Слоган:** «Планируйте уверенно»

## Структура проекта

```
/workspace
├── api/                    # API endpoints
│   ├── auth.php           # Аутентификация (вход, регистрация, выход)
│   ├── budget.php         # CRUD для статей бюджета
│   ├── predictions.php    # Генерация и получение прогнозов
│   └── scenarios.php      # CRUD для сценариев
├── css/
│   └── style.css          # Основные стили приложения
├── includes/
│   └── config.php         # Конфигурация и общие функции
├── js/                     # JavaScript файлы (при необходимости)
├── database.sql           # SQL схема базы данных
├── index.php              # Dashboard (главная страница)
├── login.php              # Страница входа/регистрации
├── scenarios.php          # Управление сценариями
├── budget.php             # Управление бюджетом
└── predictions.php        # Прогнозирование
```

## Технический стек

- **Frontend:** HTML5, CSS3, Vanilla JavaScript, Chart.js
- **Backend:** PHP 8.x
- **База данных:** MySQL/MariaDB
- **Сервер:** Apache

## Цветовая схема

- **Основной:** `#2C3E50` (Темно-синий)
- **Акцентный:** `#27AE60` (Изумрудный)
- **Фон:** `#ECF0F1` (Светло-серый)
- **Ошибка:** `#E74C3C` (Приглушенный красный)

## Установка

### 1. Требования
- PHP 8.0 или выше
- MySQL 5.7+ или MariaDB 10.3+
- Apache с mod_rewrite

### 2. Настройка базы данных

```bash
# Создайте базу данных
mysql -u root -p -e "CREATE DATABASE finhorizon_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Импортируйте схему
mysql -u root -p finhorizon_db < database.sql
```

### 3. Настройка конфигурации

Отредактируйте файл `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'finhorizon_db');
define('DB_USER', 'ваш_пользователь');
define('DB_PASS', 'ваш_пароль');
```

### 4. Настройка Apache

Создайте виртуальный хост:

```apache
<VirtualHost *:80>
    ServerName finhorizon.local
    DocumentRoot /workspace
    
    <Directory /workspace>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 5. Права доступа

```bash
chmod -R 755 /workspace
chown -R www-data:www-data /workspace
```

## Функциональность

### 1. Аутентификация
- Регистрация новых пользователей
- Вход по email/паролю
- Сессионная авторизация

### 2. Управление сценариями
- Создание бюджетных сценариев
- Установка периода действия
- Активные/архивные сценарии

### 3. Управление бюджетом
- Добавление статей доходов и расходов
- Категоризация статей
- Повторяющиеся статьи
- Фильтрация по типу и категории

### 4. Прогнозирование
- **Алгоритм:** Линейная регрессия с сезонной корректировкой
- **Метод:** Наименьшие квадраты для выявления тренда
- **Учет сезонности:** Корректировка на основе исторических данных за аналогичные периоды
- **Уровень доверия:** Расчет на основе волатильности данных

Прогноз строится на основе:
1. Выявления линейного тренда доходов и расходов
2. Расчета сезонных коэффициентов
3. Оценки волатильности для определения confidence level

### 5. Визуализация
- Графики Chart.js
- Динамика доходов/расходов
- Сравнение истории и прогноза

## API Endpoints

### Аутентификация (`/api/auth.php`)
- `POST ?action=register` — Регистрация
- `POST ?action=login` — Вход
- `POST ?action=logout` — Выход

### Сценарии (`/api/scenarios.php`)
- `GET ?action=list` — Список сценариев
- `GET ?action=get&id=X` — Получить сценарий
- `POST ?action=create` — Создать сценарий
- `POST ?action=update` — Обновить сценарий
- `POST ?action=delete` — Удалить сценарий

### Бюджет (`/api/budget.php`)
- `GET ?action=list&scenario_id=X` — Список статей
- `GET ?action=get&id=X` — Получить статью
- `POST ?action=create` — Создать статью
- `POST ?action=update` — Обновить статью
- `POST ?action=delete` — Удалить статью
- `GET ?action=summary&scenario_id=X` — Сводка по бюджету

### Прогнозы (`/api/predictions.php`)
- `GET ?action=get&scenario_id=X` — Получить прогноз
- `GET ?action=generate&scenario_id=X&months=6` — Сгенерировать прогноз

## Пример работы прогнозирования

```php
// Алгоритм использует:
// 1. Линейную регрессию для тренда
// 2. Сезонные коэффициенты
// 3. Расчет волатильности

// Формула прогноза:
predicted_value = intercept + slope * period_index * seasonal_factor

// Уровень доверия:
confidence = base_confidence - volatility_penalty
// где base_confidence зависит от количества месяцев истории
// а volatility_penalty от стандартного отклонения
```

## Безопасность

- Хэширование паролей (password_hash)
- Prepared statements для защиты от SQL injection
- CSRF токены
- Проверка прав доступа к данным пользователя
- Валидация входных данных

## Лицензия

Проект создан для демонстрации возможностей SaaS разработки.
