<?php
/**
 * ФинГоризонт - API для прогнозирования
 * Использует исторические данные для построения прогнозов
 */

require_once '../includes/config.php';

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
            
            // Расчет прогноза методом линейной регрессии с сезонной корректировкой
            $predictions = [];
            $incomeValues = array_column($historicalData, 'income');
            $expenseValues = array_column($historicalData, 'expense');

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
            
            for ($i = 0; $i < $months; $i++) {
                if ($i === 0) {
                    // Первая точка прогноза = последний фактический месяц
                    $forecastDate = clone $lastDate;
                    $forecastMonth = $forecastDate->format('Y-m');
                    $predictedIncome = max(0, (float)$incomeValues[$n - 1]);
                    $predictedExpense = max(0, (float)$expenseValues[$n - 1]);
                } else {
                    $forecastDate = clone $lastDate;
                    $forecastDate->modify("+{$i} month");
                    $forecastMonth = $forecastDate->format('Y-m');

                    // Прогнозируемые значения для будущих месяцев
                    $predictedIncome = max(0, $incomeForecast['values'][$i - 1] ?? 0);
                    $predictedExpense = max(0, $expenseForecast['values'][$i - 1] ?? 0);
                }
                
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
                        'income_slope' => round($incomeForecast['slope'], 2),
                        'expense_slope' => round($expenseForecast['slope'], 2),
                        'income_trend' => $incomeForecast['slope'] > 0 ? 'growth' : ($incomeForecast['slope'] < 0 ? 'decline' : 'stable'),
                        'expense_trend' => $expenseForecast['slope'] > 0 ? 'growth' : ($expenseForecast['slope'] < 0 ? 'decline' : 'stable')
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
} finally {
    restore_error_handler();
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
 * Прогноз ряда: экспоненциальное сглаживание + ограниченный медианный тренд
 */
function forecastSeries($values, $forecastMonths) {
    $n = count($values);
    $alpha = 0.45; // вес последних наблюдений

    // Базовый уровень через экспоненциальное сглаживание
    $level = (float)$values[0];
    for ($i = 1; $i < $n; $i++) {
        $level = $alpha * (float)$values[$i] + (1 - $alpha) * $level;
    }

    // Тренд: медиана относительных изменений за последние 6 периодов
    $growthRates = [];
    $startIndex = max(1, $n - 6);
    for ($i = $startIndex; $i < $n; $i++) {
        $prev = (float)$values[$i - 1];
        $curr = (float)$values[$i];

        if ($prev > 0) {
            $growthRates[] = ($curr - $prev) / $prev;
        }
    }

    $trendRate = median($growthRates);

    // Ограничение тренда для устойчивости
    $trendRate = max(-0.2, min(0.2, $trendRate));

    // Старт прогноза привязываем к последнему фактическому значению,
    // чтобы избежать резкого скачка между историей и прогнозом.
    $lastActual = (float)$values[$n - 1];
    $prevForecast = $lastActual;
    $forecast = [];

    for ($m = 1; $m <= $forecastMonths; $m++) {
        // Плавно затухающий тренд: в начале шаг сильнее, дальше более консервативный
        $trendDamping = max(0.35, 1 - (($m - 1) * 0.12));
        $stepTrendRate = $trendRate * $trendDamping;

        $rawForecast = $prevForecast * (1 + $stepTrendRate);

        // Мягкое притяжение к сглаженному уровню для устойчивости
        $reversionWeight = 0.25;
        $forecastValue = $rawForecast + (($level - $rawForecast) * $reversionWeight);

        $forecastValue = max(0, $forecastValue);
        $forecast[] = round($forecastValue, 2);
        $prevForecast = $forecastValue;
    }

    $slope = count($forecast) > 0 ? $forecast[0] - $lastActual : 0;

    return [
        'values' => $forecast,
        'slope' => round($slope, 2)
    ];
}

/**
 * Медиана массива чисел
 */
function median($values) {
    if (empty($values)) {
        return 0;
    }

    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = (int) floor($count / 2);

    if ($count % 2 === 0) {
        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    return $values[$middle];
}
