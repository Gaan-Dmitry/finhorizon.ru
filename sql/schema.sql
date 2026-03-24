
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
    plan_name VARCHAR(80) NOT NULL DEFAULT 'Growth',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow',
    base_currency CHAR(3) NOT NULL DEFAULT 'RUB',
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

INSERT INTO companies (id, name, industry, plan_name, timezone, base_currency, created_at) VALUES
    (1, 'Demo FinOps LLC', 'B2B SaaS', 'Scale', 'Europe/Moscow', 'RUB', NOW());

INSERT INTO users (id, full_name, email, password_hash, created_at) VALUES
    (1, 'Demo Owner', 'owner@demo.fin', '$2y$12$rE0lVQzyW9WNqjo4xR8rseF/rn5AFF.uAkOXWkXDMM.BnNh5cbwcG', NOW());

INSERT INTO company_user (company_id, user_id, role_name, created_at) VALUES
    (1, 1, 'owner', NOW());

INSERT INTO budget_categories (id, company_id, name, category_type, monthly_limit, created_at) VALUES
    (1, 1, 'Подписки SaaS', 'revenue', 0, NOW()),
    (2, 1, 'Консалтинг', 'revenue', 0, NOW()),
    (3, 1, 'Зарплаты', 'expense', 320000, NOW()),
    (4, 1, 'Маркетинг', 'expense', 150000, NOW()),
    (5, 1, 'Инфраструктура', 'expense', 95000, NOW()),
    (6, 1, 'Операционные расходы', 'expense', 80000, NOW());

INSERT INTO transactions (company_id, category_id, txn_date, description, amount, direction, source_type, created_at) VALUES
    (1, 1, DATE_SUB(CURDATE(), INTERVAL 95 DAY), 'Годовой контракт / клиент Northstar', 230000, 'inflow', 'manual', NOW()),
    (1, 2, DATE_SUB(CURDATE(), INTERVAL 82 DAY), 'Финансовый аудит для ритейл-группы', 145000, 'inflow', 'manual', NOW()),
    (1, 3, DATE_SUB(CURDATE(), INTERVAL 78 DAY), 'ФОТ за прошлый квартал', 318000, 'outflow', 'manual', NOW()),
    (1, 4, DATE_SUB(CURDATE(), INTERVAL 67 DAY), 'Перформанс-маркетинг', 102000, 'outflow', 'manual', NOW()),
    (1, 1, DATE_SUB(CURDATE(), INTERVAL 54 DAY), 'MRR / клиент Apex', 255000, 'inflow', 'manual', NOW()),
    (1, 5, DATE_SUB(CURDATE(), INTERVAL 42 DAY), 'Облако, CDN и email-провайдер', 64000, 'outflow', 'manual', NOW()),
    (1, 1, DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'MRR / клиент Helix', 295000, 'inflow', 'manual', NOW()),
    (1, 2, DATE_SUB(CURDATE(), INTERVAL 16 DAY), 'Проект по BI-интеграции', 188000, 'inflow', 'manual', NOW()),
    (1, 3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'ФОТ текущего месяца', 320000, 'outflow', 'manual', NOW()),
    (1, 4, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Лидогенерация и вебинары', 79000, 'outflow', 'manual', NOW()),
    (1, 5, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Сервисы мониторинга и хранения', 58000, 'outflow', 'manual', NOW()),
    (1, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Юридические и банковские сервисы', 41000, 'outflow', 'manual', NOW());

INSERT INTO scenario_forecasts (company_id, scenario_code, scenario_name, forecast_month, revenue_forecast, expense_forecast, created_at) VALUES
    (1, 'optimistic', 'Рост', DATE_FORMAT(CURDATE(), '%Y-%m-01'), 920000, 515000, NOW()),
    (1, 'optimistic', 'Рост', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), 1015000, 528000, NOW()),
    (1, 'optimistic', 'Рост', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01'), 1090000, 542000, NOW()),
    (1, 'optimistic', 'Рост', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01'), 1180000, 559000, NOW()),
    (1, 'base', 'Базовый', DATE_FORMAT(CURDATE(), '%Y-%m-01'), 870000, 510000, NOW()),
    (1, 'base', 'Базовый', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), 930000, 522000, NOW()),
    (1, 'base', 'Базовый', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01'), 995000, 537000, NOW()),
    (1, 'base', 'Базовый', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01'), 1060000, 553000, NOW()),
    (1, 'stress', 'Стресс', DATE_FORMAT(CURDATE(), '%Y-%m-01'), 760000, 532000, NOW()),
    (1, 'stress', 'Стресс', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), 720000, 540000, NOW()),
    (1, 'stress', 'Стресс', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01'), 695000, 547000, NOW()),
    (1, 'stress', 'Стресс', DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01'), 680000, 556000, NOW());
