-- ФинГоризонт: База данных для системы финансового прогнозирования

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS finhorizon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finhorizon;

-- Таблица пользователей
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица сценариев (проектов/планов)
CREATE TABLE scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица категорий статей бюджета
CREATE TABLE budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES budget_categories(id) ON DELETE SET NULL
);

-- Таблица фактических данных бюджета (история)
CREATE TABLE budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT NOT NULL,
    category_id INT NOT NULL,
    period DATE NOT NULL COMMENT 'Период (месяц)',
    amount DECIMAL(15, 2) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_scenario_category_period (scenario_id, category_id, period)
);

-- Таблица прогнозов
CREATE TABLE forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT NOT NULL,
    category_id INT NOT NULL,
    period DATE NOT NULL,
    predicted_amount DECIMAL(15, 2) NOT NULL,
    method VARCHAR(50) DEFAULT 'moving_average' COMMENT 'Метод прогнозирования',
    confidence_level DECIMAL(5, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_forecast_scenario_category_period (scenario_id, category_id, period)
);

-- Индексы для оптимизации запросов
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_scenarios_user ON scenarios(user_id);
CREATE INDEX idx_budget_items_scenario ON budget_items(scenario_id);
CREATE INDEX idx_budget_items_period ON budget_items(period);
CREATE INDEX idx_forecasts_scenario ON forecasts(scenario_id);
CREATE INDEX idx_forecasts_period ON forecasts(period);

-- Пример данных для тестирования (опционально)
-- INSERT INTO users (email, password_hash, full_name) VALUES 
-- ('demo@finhorizon.ru', '$2y$10$example_hash_here', 'Демо Пользователь');
