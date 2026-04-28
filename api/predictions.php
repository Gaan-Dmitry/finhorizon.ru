<?php
/**
 * ФинГоризонт - API для прогнозирования
 * Использует исторические данные для построения прогнозов
 */

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Требуется авторизация']);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'generate':
            // Генерация прогноза на основе исторических данных
            $scenarioId = $_GET['scenario_id'] ?? null;
            $months = (int)($_GET['months'] ?? 6); // Количество месяцев для прогноза
            
            if (!$scenarioId) {
                jsonResponse(['success' => false, 'error' => 'Не указан сценарий']);
            }
            
            // Проверка прав на сценарий
            $stmt = $pdo->prepare("SELECT id, start_date, end_date FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$scenarioId, $_SESSION['user_id']]);
            $scenario = $stmt->fetch();
            
            if (!$scenario) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }
            
            // Получение исторических данных по месяцам
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                FROM budget_items
                WHERE scenario_id = ?
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$scenarioId]);
            $historicalData = $stmt->fetchAll();
            
            if (count($historicalData) < 2) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Недостаточно данных для прогноза. Минимум 2 месяца истории.'
                ]);
            }
            
            // Расчет прогноза методом линейной регрессии с сезонной корректировкой
            $predictions = [];
            $incomeValues = array_column($historicalData, 'income');
            $expenseValues = array_column($historicalData, 'expense');
            
            // Расчет линейного тренда (метод наименьших квадратов)
            $n = count($incomeValues);
            $months = max(1, min(24, $months));
            $sumX = array_sum(range(0, $n - 1));
            $sumYIncome = array_sum($incomeValues);
            $sumYExpense = array_sum($expenseValues);
            $sumXYIncome = 0;
            $sumXYExpense = 0;
            $sumX2 = 0;
            
            foreach ($incomeValues as $i => $value) {
                $sumXYIncome += $i * $value;
                $sumX2 += $i * $i;
            }
            
            foreach ($expenseValues as $i => $value) {
                $sumXYExpense += $i * $value;
            }
            
            // Коэффициенты тренда
            $denominator = ($n * $sumX2 - $sumX * $sumX);
            $slopeIncome = $denominator != 0 ? ($n * $sumXYIncome - $sumX * $sumYIncome) / $denominator : 0;
            $interceptIncome = ($sumYIncome - $slopeIncome * $sumX) / $n;
            
            $slopeExpense = $denominator != 0 ? ($n * $sumXYExpense - $sumX * $sumYExpense) / $denominator : 0;
            $interceptExpense = ($sumYExpense - $slopeExpense * $sumX) / $n;
            
            // Расчет волатильности для определения confidence level
            $incomeStdDev = calculateStandardDeviation($incomeValues);
            $expenseStdDev = calculateStandardDeviation($expenseValues);
            $avgIncome = array_sum($incomeValues) / $n;
            $avgExpense = array_sum($expenseValues) / $n;
            
            $incomeVariation = $avgIncome > 0 ? $incomeStdDev / $avgIncome : 1;
            $expenseVariation = $avgExpense > 0 ? $expenseStdDev / $avgExpense : 1;
            
            // Генерация прогнозов на будущие месяцы
            $lastMonth = end($historicalData)['month'];
            $lastDate = new DateTime($lastMonth . '-01');
            
            for ($i = 1; $i <= $months; $i++) {
                $forecastDate = clone $lastDate;
                $forecastDate->modify("+{$i} month");
                $forecastMonth = $forecastDate->format('Y-m');
                
                // Прогнозируемые значения с учетом тренда
                $predictedIncome = max(0, $interceptIncome + $slopeIncome * ($n + $i - 1));
                $predictedExpense = max(0, $interceptExpense + $slopeExpense * ($n + $i - 1));
                
                // Корректировка на сезонность (если есть данные за тот же месяц в прошлом)
                $sameMonthInHistory = array_filter($historicalData, function($item) use ($forecastDate) {
                    return date('m', strtotime($item['month'] . '-01')) === $forecastDate->format('m');
                });
                
                if (!empty($sameMonthInHistory)) {
                    if ($avgIncome > 0) {
                        $seasonalFactorIncome = array_sum(array_column($sameMonthInHistory, 'income')) /
                                               (count($sameMonthInHistory) * $avgIncome);
                        $predictedIncome *= $seasonalFactorIncome;
                    }

                    if ($avgExpense > 0) {
                        $seasonalFactorExpense = array_sum(array_column($sameMonthInHistory, 'expense')) /
                                                (count($sameMonthInHistory) * $avgExpense);
                        $predictedExpense *= $seasonalFactorExpense;
                    }
                }
                
                $predictedBalance = $predictedIncome - $predictedExpense;
                
                // Расчет уровня доверия (confidence level)
                // Чем больше история и меньше волатильность, тем выше доверие
                $baseConfidence = min(95, 50 + ($n * 5));
                $volatilityPenalty = (($incomeVariation + $expenseVariation) / 2) * 30;
                $confidenceLevel = max(30, min(95, $baseConfidence - $volatilityPenalty));
                
                $predictions[] = [
                    'month' => $forecastMonth,
                    'predicted_income' => round($predictedIncome, 2),
                    'predicted_expense' => round($predictedExpense, 2),
                    'predicted_balance' => round($predictedBalance, 2),
                    'confidence_level' => round($confidenceLevel, 2)
                ];
            }
            
            // Сохранение прогнозов в БД
            $stmt = $pdo->prepare("DELETE FROM predictions WHERE scenario_id = ?");
            $stmt->execute([$scenarioId]);
            
            $stmt = $pdo->prepare("
                INSERT INTO predictions 
                (scenario_id, prediction_date, predicted_income, predicted_expense, 
                 predicted_balance, confidence_level, algorithm_used)
                VALUES (?, ?, ?, ?, ?, ?, 'linear_regression_with_seasonality')
            ");
            
            foreach ($predictions as $prediction) {
                $stmt->execute([
                    $scenarioId,
                    $prediction['month'] . '-01',
                    $prediction['predicted_income'],
                    $prediction['predicted_expense'],
                    $prediction['predicted_balance'],
                    $prediction['confidence_level']
                ]);
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Прогноз успешно сгенерирован',
                'data' => [
                    'historical' => $historicalData,
                    'predictions' => $predictions,
                    'trend' => [
                        'income_slope' => round($slopeIncome, 2),
                        'expense_slope' => round($slopeExpense, 2),
                        'income_trend' => $slopeIncome > 0 ? 'growth' : ($slopeIncome < 0 ? 'decline' : 'stable'),
                        'expense_trend' => $slopeExpense > 0 ? 'growth' : ($slopeExpense < 0 ? 'decline' : 'stable')
                    ]
                ]
            ]);
            
            break;
            
        case 'get':
            // Получение сохраненных прогнозов
            $scenarioId = $_GET['scenario_id'] ?? null;
            
            if (!$scenarioId) {
                jsonResponse(['success' => false, 'error' => 'Не указан сценарий']);
            }
            
            // Проверка прав на сценарий
            $stmt = $pdo->prepare("SELECT id FROM scenarios WHERE id = ? AND user_id = ?");
            $stmt->execute([$scenarioId, $_SESSION['user_id']]);
            $scenario = $stmt->fetch();

            if (!$scenario) {
                jsonResponse(['success' => false, 'error' => 'Сценарий не найден']);
            }

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    scenario_id,
                    DATE_FORMAT(prediction_date, '%Y-%m') as month,
                    prediction_date,
                    predicted_income,
                    predicted_expense,
                    predicted_balance,
                    confidence_level,
                    algorithm_used,
                    created_at
                FROM predictions
                WHERE scenario_id = ?
                ORDER BY prediction_date ASC
            ");
            $stmt->execute([$scenarioId]);
            $predictions = $stmt->fetchAll();
            
            // Получение исторических данных для графика
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance
                FROM budget_items
                WHERE scenario_id = ?
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$scenarioId]);
            $historical = $stmt->fetchAll();
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'historical' => $historical,
                    'predictions' => $predictions
                ]
            ]);
            
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка БД: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка базы данных']);
} catch (Throwable $e) {
    error_log("Ошибка прогнозирования: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Ошибка расчета прогноза']);
}

/**
 * Расчет стандартного отклонения
 */
function calculateStandardDeviation($values) {
    $n = count($values);
    if ($n < 2) {
        return 0;
    }
    
    $mean = array_sum($values) / $n;
    $variance = 0;
    
    foreach ($values as $value) {
        $variance += pow($value - $mean, 2);
    }
    
    return sqrt($variance / ($n - 1));
}
