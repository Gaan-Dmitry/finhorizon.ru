<?php
/**
 * ФинГоризонт - Страница прогнозов
 */

require_once 'includes/config.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$pdo = getDBConnection();

// Получение сценария
$scenarioId = $_GET['scenario_id'] ?? null;
$scenario = null;

if ($scenarioId) {
    $stmt = $pdo->prepare("SELECT * FROM scenarios WHERE id = ? AND user_id = ?");
    $stmt->execute([$scenarioId, $_SESSION['user_id']]);
    $scenario = $stmt->fetch();
}

// Если сценарий не выбран или не найден, берем первый активный
if (!$scenario) {
    $stmt = $pdo->prepare("SELECT * FROM scenarios WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $scenario = $stmt->fetch();
    if ($scenario) {
        $scenarioId = $scenario['id'];
    }
}

// Получение всех сценариев для селекта
$stmt = $pdo->prepare("SELECT * FROM scenarios WHERE user_id = ? AND is_active = 1 ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$allScenarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прогнозы - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Хедер -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                </svg>
            </div>
            <div class="logo-text">
                <h1>ФинГоризонт</h1>
                <p><?= APP_SLOGAN ?></p>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li><a href="/index.php">Dashboard</a></li>
            <li><a href="/scenarios.php">Сценарии</a></li>
            <li><a href="/budget.php">Бюджет</a></li>
            <li><a href="/predictions.php" class="active">Прогнозы</a></li>
        </ul>
        
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['company_name'] ?? $user['email']) ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Выход</button>
        </div>
    </header>
    
    <!-- Основной контент -->
    <main class="main-content">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Финансовое прогнозирование</h2>
                    <select id="scenarioSelect" class="form-control" style="width: 300px;">
                        <?php foreach ($allScenarios as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $scenarioId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!$scenarioId): ?>
                    <div class="alert alert-warning">
                        У вас нет активных сценариев. Создайте сценарий и добавьте статьи бюджета для построения прогноза.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" id="infoMessage" style="display: none;"></div>
                    <div class="alert alert-error" id="errorMessage" style="display: none;"></div>
                    
                    <div style="margin-bottom: 20px;">
                        <button class="btn btn-accent" onclick="generatePrediction()">
                            📊 Сгенерировать прогноз
                        </button>
                        <select id="monthsSelect" class="form-control" style="width: 150px; display: inline-block; margin-left: 10px;">
                            <option value="3">3 месяца</option>
                            <option value="6" selected>6 месяцев</option>
                            <option value="12">12 месяцев</option>
                        </select>
                    </div>
                    
                    <!-- График прогноза -->
                    <div class="chart-container">
                        <canvas id="predictionChart"></canvas>
                    </div>
                    
                    <!-- Таблица прогнозов -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Месяц</th>
                                    <th>Прогноз дохода</th>
                                    <th>Прогноз расходов</th>
                                    <th>Прогноз баланса</th>
                                    <th>Уверенность</th>
                                </tr>
                            </thead>
                            <tbody id="predictionsTableBody">
                                <tr>
                                    <td colspan="5" class="text-center">Загрузка данных...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Информация о тренде -->
                    <div class="stats-grid mt-20" id="trendInfo" style="display: none;">
                        <div class="stat-card income">
                            <div class="stat-label">Тренд доходов</div>
                            <div class="stat-value" id="incomeTrend">-</div>
                        </div>
                        <div class="stat-card expense">
                            <div class="stat-label">Тренд расходов</div>
                            <div class="stat-value" id="expenseTrend">-</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">На основе месяцев истории</div>
                            <div class="stat-value monospace" id="historyMonths">-</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Информация о методе прогнозирования -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">О методе прогнозирования</h2>
                </div>
                <div style="line-height: 1.8;">
                    <p>
                        Прогнозирование в <strong>ФинГоризонт</strong> использует комбинацию методов:
                    </p>
                    <ul style="margin: 15px 0 15px 30px;">
                        <li><strong>Линейная регрессия</strong> — выявление общего тренда (рост/падение) на основе исторических данных</li>
                        <li><strong>Сезонная корректировка</strong> — учет повторяющихся паттернов в одни и те же месяцы</li>
                        <li><strong>Анализ волатильности</strong> — расчет уровня доверия к прогнозу на основе стабильности данных</li>
                    </ul>
                    <p>
                        <strong>Важно:</strong> Для точного прогноза рекомендуется иметь минимум 2-3 месяца исторических данных.
                        Чем больше история, тем выше точность прогноза.
                    </p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let predictionChart = null;
        let currentScenarioId = <?= $scenarioId ? $scenarioId : 'null' ?>;
        
        function logout() {
            fetch('/api/auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.href = '/login.php');
        }
        
        // Переключение сценария
        document.getElementById('scenarioSelect')?.addEventListener('change', function() {
            const scenarioId = this.value;
            window.location.href = '/predictions.php?scenario_id=' + scenarioId;
        });
        
        // Загрузка данных при старте
        if (currentScenarioId) {
            loadPredictions();
        }
        
        function loadPredictions() {
            if (!currentScenarioId) return;
            
            fetch('/api/predictions.php?action=get&scenario_id=' + currentScenarioId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.predictions.length > 0) {
                        displayPredictions(data.data);
                    } else {
                        document.getElementById('predictionsTableBody').innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center">
                                    Прогноз еще не сгенерирован. Нажмите кнопку "Сгенерировать прогноз".
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(err => {
                    console.error('Ошибка загрузки:', err);
                    document.getElementById('predictionsTableBody').innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center">Ошибка загрузки данных</td>
                        </tr>
                    `;
                });
        }
        
        function generatePrediction() {
            const months = document.getElementById('monthsSelect').value;
            
            fetch(`/api/predictions.php?action=generate&scenario_id=${currentScenarioId}&months=${months}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Прогноз успешно сгенерирован!', 'success');
                        displayPredictions(data.data);
                        
                        // Показываем информацию о тренде
                        const trendInfo = document.getElementById('trendInfo');
                        const incomeTrend = document.getElementById('incomeTrend');
                        const expenseTrend = document.getElementById('expenseTrend');
                        const historyMonths = document.getElementById('historyMonths');
                        
                        trendInfo.style.display = 'grid';
                        
                        incomeTrend.textContent = data.data.trend.income_trend === 'growth' ? '📈 Рост' : 
                                                  (data.data.trend.income_trend === 'decline' ? '📉 Спад' : '➡️ Стабильно');
                        incomeTrend.className = 'stat-value ' + (data.data.trend.income_trend === 'growth' ? 'positive' : 
                                                                 (data.data.trend.income_trend === 'decline' ? 'negative' : ''));
                        
                        expenseTrend.textContent = data.data.trend.expense_trend === 'growth' ? '📈 Рост' : 
                                                   (data.data.trend.expense_trend === 'decline' ? '📉 Спад' : '➡️ Стабильно');
                        expenseTrend.className = 'stat-value ' + (data.data.trend.expense_trend === 'growth' ? 'negative' : 
                                                                  (data.data.trend.expense_trend === 'decline' ? 'positive' : ''));
                        
                        historyMonths.textContent = data.data.historical.length;
                    } else {
                        showMessage(data.error || 'Ошибка генерации прогноза', 'error');
                    }
                })
                .catch(err => {
                    console.error('Ошибка:', err);
                    showMessage('Ошибка соединения с сервером', 'error');
                });
        }
        
        function displayPredictions(data) {
            const { historical, predictions } = data;
            
            // Обновление таблицы
            const tbody = document.getElementById('predictionsTableBody');
            let html = '';
            
            // Исторические данные (последние 6 месяцев)
            const recentHistory = historical.slice(-6);
            if (recentHistory.length > 0) {
                html += '<tr><td colspan="5" style="background-color: #f5f5f5;"><strong>Исторические данные</strong></td></tr>';
                recentHistory.forEach(item => {
                    const balance = parseFloat(item.income) - parseFloat(item.expense);
                    html += `
                        <tr>
                            <td>${item.month}</td>
                            <td class="amount positive monospace">${Number(item.income).toLocaleString('ru-RU')} ₽</td>
                            <td class="amount negative monospace">${Number(item.expense).toLocaleString('ru-RU')} ₽</td>
                            <td class="amount monospace ${balance >= 0 ? 'positive' : 'negative'}">
                                ${balance.toLocaleString('ru-RU')} ₽
                            </td>
                            <td>-</td>
                        </tr>
                    `;
                });
            }
            
            // Прогнозы
            if (predictions.length > 0) {
                html += '<tr><td colspan="5" style="background-color: #e8f5e9;"><strong>Прогноз</strong></td></tr>';
                predictions.forEach(item => {
                    html += `
                        <tr>
                            <td>${item.month}</td>
                            <td class="amount positive monospace">${Number(item.predicted_income).toLocaleString('ru-RU')} ₽</td>
                            <td class="amount negative monospace">${Number(item.predicted_expense).toLocaleString('ru-RU')} ₽</td>
                            <td class="amount monospace ${item.predicted_balance >= 0 ? 'positive' : 'negative'}">
                                ${Number(item.predicted_balance).toLocaleString('ru-RU')} ₽
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <div style="flex: 1; height: 10px; background-color: #ecf0f1; border-radius: 5px; overflow: hidden;">
                                        <div style="width: ${item.confidence_level}%; height: 100%; background-color: ${getConfidenceColor(item.confidence_level)};"></div>
                                    </div>
                                    <span style="font-size: 0.85rem;">${item.confidence_level}%</span>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html || '<tr><td colspan="5" class="text-center">Нет данных</td></tr>';
            
            // Построение графика
            renderChart(historical, predictions);
        }
        
        function renderChart(historical, predictions) {
            const ctx = document.getElementById('predictionChart').getContext('2d');
            
            // Подготовка данных
            const labels = [
                ...historical.map(item => item.month),
                ...predictions.map(item => item.month)
            ];
            
            const incomeData = [
                ...historical.map(item => parseFloat(item.income)),
                ...predictions.map(item => item.predicted_income)
            ];
            
            const expenseData = [
                ...historical.map(item => parseFloat(item.expense)),
                ...predictions.map(item => item.predicted_expense)
            ];
            
            // Разделительная линия между историей и прогнозом
            const splitIndex = historical.length - 1;
            
            if (predictionChart) {
                predictionChart.destroy();
            }
            
            predictionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Доходы (история)',
                            data: incomeData.map((v, i) => i <= splitIndex ? v : null),
                            borderColor: '#27AE60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Доходы (прогноз)',
                            data: incomeData.map((v, i) => i > splitIndex ? v : null),
                            borderColor: '#27AE60',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: 'Расходы (история)',
                            data: expenseData.map((v, i) => i <= splitIndex ? v : null),
                            borderColor: '#E74C3C',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Расходы (прогноз)',
                            data: expenseData.map((v, i) => i > splitIndex ? v : null),
                            borderColor: '#E74C3C',
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    xMin: splitIndex + 0.5,
                                    xMax: splitIndex + 0.5,
                                    borderColor: 'rgba(0, 0, 0, 0.3)',
                                    borderWidth: 2,
                                    borderDash: [5, 5]
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('ru-RU') + ' ₽';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function getConfidenceColor(level) {
            if (level >= 80) return '#27AE60';
            if (level >= 60) return '#F39C12';
            return '#E74C3C';
        }
        
        function showMessage(text, type) {
            const successMsg = document.getElementById('infoMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';
            
            if (type === 'success') {
                successMsg.textContent = text;
                successMsg.style.display = 'flex';
            } else {
                errorMsg.textContent = text;
                errorMsg.style.display = 'flex';
            }
            
            setTimeout(() => {
                successMsg.style.display = 'none';
                errorMsg.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
