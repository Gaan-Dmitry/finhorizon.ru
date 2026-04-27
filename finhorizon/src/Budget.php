<?php
/**
 * ФинГоризонт - Модель для работы с бюджетом и прогнозированием
 */

require_once __DIR__ . '/../config/database.php';

class Budget {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Создание категории бюджета
     */
    public function createCategory($userId, $name, $type, $parentId = null) {
        $sql = "INSERT INTO budget_categories (user_id, name, type, parent_id) 
                VALUES (:user_id, :name, :type, :parent_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'parent_id' => $parentId
        ]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Получение всех категорий пользователя
     */
    public function getCategories($userId, $type = null) {
        $sql = "SELECT * FROM budget_categories WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($type) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }
        
        $sql .= " ORDER BY type, name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Добавление/обновление статьи бюджета
     */
    public function upsertItem($scenarioId, $categoryId, $period, $amount, $comment = null) {
        $sql = "INSERT INTO budget_items (scenario_id, category_id, period, amount, comment) 
                VALUES (:scenario_id, :category_id, :period, :amount, :comment)
                ON DUPLICATE KEY UPDATE 
                amount = VALUES(amount), 
                comment = VALUES(comment)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'scenario_id' => $scenarioId,
            'category_id' => $categoryId,
            'period' => $period,
            'amount' => $amount,
            'comment' => $comment
        ]);
    }
    
    /**
     * Получение статей бюджета по сценарию
     */
    public function getItems($scenarioId, $startDate = null, $endDate = null) {
        $sql = "SELECT bi.*, bc.name as category_name, bc.type 
                FROM budget_items bi
                JOIN budget_categories bc ON bi.category_id = bc.id
                WHERE bi.scenario_id = :scenario_id";
        $params = ['scenario_id' => $scenarioId];
        
        if ($startDate) {
            $sql .= " AND bi.period >= :start_date";
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND bi.period <= :end_date";
            $params['end_date'] = $endDate;
        }
        
        $sql .= " ORDER BY bi.period, bc.type, bc.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Получение данных для прогнозирования
     */
    public function getHistoricalData($scenarioId, $categoryId, $limit = 12) {
        $sql = "SELECT period, amount 
                FROM budget_items 
                WHERE scenario_id = :scenario_id 
                AND category_id = :category_id
                ORDER BY period DESC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':scenario_id', $scenarioId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll());
    }
    
    /**
     * Расчет прогноза методом скользящего среднего
     */
    public function calculateForecast($scenarioId, $categoryId, $periodsAhead = 3) {
        // Получаем исторические данные
        $history = $this->getHistoricalData($scenarioId, $categoryId, 12);
        
        if (count($history) < 3) {
            return null; // Недостаточно данных для прогноза
        }
        
        // Извлекаем значения
        $values = array_column($history, 'amount');
        $dates = array_column($history, 'period');
        
        // Простой прогноз: среднее арифметическое последних 3 периодов
        $recentValues = array_slice($values, -3);
        $average = array_sum($recentValues) / count($recentValues);
        
        // Расчет волатильности для уровня доверия
        $variance = 0;
        foreach ($recentValues as $val) {
            $variance += pow($val - $average, 2);
        }
        $stdDev = sqrt($variance / count($recentValues));
        $confidenceLevel = max(0, min(100, 100 - ($stdDev / $average * 100)));
        
        // Генерируем прогнозы на несколько периодов вперед
        $forecasts = [];
        $lastDate = new DateTime(end($dates));
        
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $nextDate = clone $lastDate;
            $nextDate->modify("+{$i} month");
            
            $forecasts[] = [
                'period' => $nextDate->format('Y-m-d'),
                'predicted_amount' => round($average, 2),
                'confidence_level' => round($confidenceLevel, 2),
                'method' => 'moving_average'
            ];
        }
        
        return $forecasts;
    }
    
    /**
     * Сохранение прогноза в БД
     */
    public function saveForecast($scenarioId, $categoryId, $period, $predictedAmount, $method, $confidenceLevel) {
        $sql = "INSERT INTO forecasts (scenario_id, category_id, period, predicted_amount, method, confidence_level)
                VALUES (:scenario_id, :category_id, :period, :predicted_amount, :method, :confidence_level)
                ON DUPLICATE KEY UPDATE
                predicted_amount = VALUES(predicted_amount),
                method = VALUES(method),
                confidence_level = VALUES(confidence_level)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'scenario_id' => $scenarioId,
            'category_id' => $categoryId,
            'period' => $period,
            'predicted_amount' => $predictedAmount,
            'method' => $method,
            'confidence_level' => $confidenceLevel
        ]);
    }
    
    /**
     * Получение прогнозов по сценарию
     */
    public function getForecasts($scenarioId) {
        $sql = "SELECT f.*, bc.name as category_name, bc.type 
                FROM forecasts f
                JOIN budget_categories bc ON f.category_id = bc.id
                WHERE f.scenario_id = :scenario_id
                ORDER BY f.period, bc.type, bc.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['scenario_id' => $scenarioId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Генерация прогнозов для всех категорий сценария
     */
    public function generateAllForecasts($scenarioId, $periodsAhead = 3) {
        // Получаем все категории сценария
        $sql = "SELECT DISTINCT bc.id, bc.name, bc.type
                FROM budget_categories bc
                JOIN budget_items bi ON bc.id = bi.category_id
                WHERE bi.scenario_id = :scenario_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['scenario_id' => $scenarioId]);
        $categories = $stmt->fetchAll();
        
        $generatedForecasts = [];
        
        foreach ($categories as $category) {
            $forecasts = $this->calculateForecast($scenarioId, $category['id'], $periodsAhead);
            
            if ($forecasts) {
                foreach ($forecasts as $forecast) {
                    $this->saveForecast(
                        $scenarioId,
                        $category['id'],
                        $forecast['period'],
                        $forecast['predicted_amount'],
                        $forecast['method'],
                        $forecast['confidence_level']
                    );
                    
                    $generatedForecasts[] = array_merge($forecast, [
                        'category_name' => $category['name'],
                        'category_type' => $category['type']
                    ]);
                }
            }
        }
        
        return $generatedForecasts;
    }
    
    /**
     * Получение сводных данных по сценарию
     */
    public function getSummary($scenarioId) {
        $sql = "SELECT 
                SUM(CASE WHEN bc.type = 'income' THEN bi.amount ELSE 0 END) as total_income,
                SUM(CASE WHEN bc.type = 'expense' THEN bi.amount ELSE 0 END) as total_expense,
                COUNT(DISTINCT bi.period) as periods_count
                FROM budget_items bi
                JOIN budget_categories bc ON bi.category_id = bc.id
                WHERE bi.scenario_id = :scenario_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['scenario_id' => $scenarioId]);
        return $stmt->fetch();
    }
}
