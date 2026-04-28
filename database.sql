-- ФинГоризонт - База данных для финансового планирования

-- Удаление существующих таблиц (для чистой установки)
DROP TABLE IF EXISTS `predictions`;
DROP TABLE IF EXISTS `budget_items`;
DROP TABLE IF EXISTS `scenarios`;
DROP TABLE IF EXISTS `users`;

-- Таблица пользователей
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `company_name` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сценариев (бюджетных планов)
CREATE TABLE `scenarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `scenarios_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица статей бюджета
CREATE TABLE `budget_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `scenario_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `date` DATE NOT NULL,
  `is_recurring` TINYINT(1) DEFAULT 0,
  `recurrence_pattern` ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scenario_id` (`scenario_id`),
  KEY `date_idx` (`date`),
  KEY `type_idx` (`type`),
  CONSTRAINT `budget_items_ibfk_1` FOREIGN KEY (`scenario_id`) REFERENCES `scenarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица прогнозов
CREATE TABLE `predictions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `scenario_id` INT(11) NOT NULL,
  `prediction_date` DATE NOT NULL,
  `predicted_income` DECIMAL(15,2) DEFAULT 0,
  `predicted_expense` DECIMAL(15,2) DEFAULT 0,
  `predicted_balance` DECIMAL(15,2) DEFAULT 0,
  `confidence_level` DECIMAL(5,2) DEFAULT 0,
  `algorithm_used` VARCHAR(50) DEFAULT 'moving_average',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scenario_id` (`scenario_id`),
  KEY `prediction_date_idx` (`prediction_date`),
  CONSTRAINT `predictions_ibfk_1` FOREIGN KEY (`scenario_id`) REFERENCES `scenarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индексы для оптимизации запросов
CREATE INDEX idx_scenario_date ON budget_items(scenario_id, date);
CREATE INDEX idx_user_scenarios ON scenarios(user_id, is_active);
