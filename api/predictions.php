<?php
/**
 * ФинГоризонт - API для прогнозирования
 * Использует исторические данные для построения прогнозов
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

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
            
            // Новый расчет прогноза: линейная регрессия + сезонность + волатильность
            $predictions = [];
            $months = max(1, min(24, $months));
            $n = count($historicalData);

            $incomeValues = array_map('floatval', array_column($historicalData, 'income'));
            $expenseValues = array_map('floatval', array_column($historicalData, 'expense'));

            $incomeTrend = calculateLinearTrend($incomeValues);
            $expenseTrend = calculateLinearTrend($expenseValues);

            $avgIncome = array_sum($incomeValues) / $n;
            $avgExpense = array_sum($expenseValues) / $n;
            $incomeStdDev = calculateStandardDeviation($incomeValues);
            $expenseStdDev = calculateStandardDeviation($expenseValues);

            $incomeVariation = $avgIncome > 0 ? $incomeStdDev / $avgIncome : 1;
            $expenseVariation = $avgExpense > 0 ? $expenseStdDev / $avgExpense : 1;

            $incomeSeasonality = buildSeasonalityMap($historicalData, 'income', $avgIncome);
            $expenseSeasonality = buildSeasonalityMap($historicalData, 'expense', $avgExpense);

            $incomeR2 = calculateTrendR2($incomeValues, $incomeTrend['slope'], $incomeTrend['intercept']);
            $expenseR2 = calculateTrendR2($expenseValues, $expenseTrend['slope'], $expenseTrend['intercept']);
            
            // Генерация прогнозов на будущие месяцы
            $lastMonth = end($historicalData)['month'];
            $lastDate = new DateTime($lastMonth . '-01');
            
            for ($i = 1; $i <= $months; $i++) {
                $forecastDate = clone $lastDate;
                $forecastDate->modify("+{$i} month");
                $forecastMonth = $forecastDate->format('Y-m');
                
                $monthNum = (int)$forecastDate->format('n');

                // Тренд по регрессии
                $incomeByTrend = $incomeTrend['intercept'] + $incomeTrend['slope'] * ($n + $i - 1);
                $expenseByTrend = $expenseTrend['intercept'] + $expenseTrend['slope'] * ($n + $i - 1);

                // Сезонная корректировка
                $incomeSeasonalFactor = $incomeSeasonality[$monthNum] ?? 1.0;
                $expenseSeasonalFactor = $expenseSeasonality[$monthNum] ?? 1.0;

                $predictedIncome = max(0, $incomeByTrend * $incomeSeasonalFactor);
                $predictedExpense = max(0, $expenseByTrend * $expenseSeasonalFactor);
                
                $predictedBalance = $predictedIncome - $predictedExpense;
                
                // Уровень доверия: больше истории + меньше волатильность + устойчивее тренд (R²)
                $baseConfidence = min(95, 50 + ($n * 5));
                $volatilityPenalty = (($incomeVariation + $expenseVariation) / 2) * 30;
                $modelQualityBonus = (($incomeR2 + $expenseR2) / 2) * 10;
                $horizonPenalty = $i * 1.5;
                $confidenceLevel = max(30, min(95, $baseConfidence - $volatilityPenalty + $modelQualityBonus - $horizonPenalty));
                
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
                VALUES (?, ?, ?, ?, ?, ?, 'exponential_smoothing_with_capped_trend')
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
                        'income_slope' => round($incomeTrend['slope'], 2),
                        'expense_slope' => round($expenseTrend['slope'], 2),
                        'income_trend' => $incomeTrend['slope'] > 0 ? 'growth' : ($incomeTrend['slope'] < 0 ? 'decline' : 'stable'),
                        'expense_trend' => $expenseTrend['slope'] > 0 ? 'growth' : ($expenseTrend['slope'] < 0 ? 'decline' : 'stable')
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

/**
 * Линейная регрессия (метод наименьших квадратов)
 */
function calculateLinearTrend($values) {
    $n = count($values);
    if ($n === 0) {
        return ['slope' => 0, 'intercept' => 0];
    }

    $sumX = array_sum(range(0, $n - 1));
    $sumY = array_sum($values);
    $sumXY = 0;
    $sumX2 = 0;

    foreach ($values as $i => $value) {
        $sumXY += $i * $value;
        $sumX2 += $i * $i;
    }

    $denominator = ($n * $sumX2 - $sumX * $sumX);
    $slope = $denominator != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;
    $intercept = ($sumY - $slope * $sumX) / $n;

    return ['slope' => $slope, 'intercept' => $intercept];
}

/**
 * Сезонные коэффициенты по номеру месяца (1..12)
 */
function buildSeasonalityMap($historicalData, $field, $average) {
    $result = array_fill(1, 12, 1.0);
    if ($average <= 0) {
        return $result;
    }

    $monthBuckets = [];
    foreach ($historicalData as $item) {
        $monthNum = (int)date('n', strtotime($item['month'] . '-01'));
        $monthBuckets[$monthNum][] = (float)$item[$field];
    }

    foreach ($monthBuckets as $monthNum => $values) {
        $monthAvg = array_sum($values) / count($values);
        $factor = $monthAvg / $average;

        // Ограничиваем сезонный фактор, чтобы не было экстремальных всплесков
        $result[$monthNum] = max(0.5, min(1.5, $factor));
    }

    return $result;
}

/**
 * Коэффициент детерминации R² для оценки качества тренда
 */
function calculateTrendR2($values, $slope, $intercept) {
    $n = count($values);
    if ($n < 2) {
        return 0;
    }

    $mean = array_sum($values) / $n;
    $ssTot = 0;
    $ssRes = 0;

    foreach ($values as $i => $actual) {
        $predicted = $intercept + $slope * $i;
        $ssTot += pow($actual - $mean, 2);
        $ssRes += pow($actual - $predicted, 2);
    }

    if ($ssTot <= 0) {
        return 0;
    }

    return max(0, min(1, 1 - ($ssRes / $ssTot)));
}
