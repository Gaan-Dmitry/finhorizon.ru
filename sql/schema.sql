CREATE DATABASE IF NOT EXISTS finhorizon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finhorizon;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS scenario_forecasts;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS budget_categories;
DROP TABLE IF EXISTS company_user;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    industry VARCHAR(120) NOT NULL,
    plan_name VARCHAR(80) NOT NULL DEFAULT 'Starter',
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    base_currency CHAR(3) NOT NULL DEFAULT 'USD',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_name VARCHAR(40) NOT NULL DEFAULT 'manager',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_company_user (company_id, user_id),
    CONSTRAINT fk_company_user_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budget_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    category_type ENUM('revenue', 'expense') NOT NULL,
    monthly_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    KEY idx_budget_company_type (company_id, category_type),
    CONSTRAINT fk_budget_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    txn_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    direction ENUM('inflow', 'outflow') NOT NULL,
    source_type ENUM('manual', 'import', 'api') NOT NULL DEFAULT 'manual',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    KEY idx_transactions_company_date (company_id, txn_date),
    KEY idx_transactions_category (category_id),
    CONSTRAINT fk_transactions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenario_forecasts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    scenario_code VARCHAR(40) NOT NULL,
    scenario_name VARCHAR(120) NOT NULL,
    forecast_month DATE NOT NULL,
    revenue_forecast DECIMAL(14,2) NOT NULL,
    expense_forecast DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uniq_company_scenario_month (company_id, scenario_code, forecast_month),
    KEY idx_scenario_company_month (company_id, forecast_month),
    CONSTRAINT fk_scenario_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Демо-пользователь для тестирования
INSERT INTO users (id, full_name, email, password_hash, created_at) VALUES
    (1, 'Demo Owner', 'owner@demo.fin', '$2y$12$rE0lVQzyW9WNqjo4xR8rseF/rn5AFF.uAkOXWkXDMM.BnNh5cbwcG', NOW());

-- Демо-компания будет создана через функцию seedCompanyData() при регистрации
-- Статические значения удалены - все данные генерируются динамически
