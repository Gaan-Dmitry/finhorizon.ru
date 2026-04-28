<?php
/**
 * ФинГоризонт - Главная страница (Dashboard)
 */

require_once 'includes/config.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$pdo = getDBConnection();

// Получение активных сценариев
$stmt = $pdo->prepare("SELECT * FROM scenarios WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$scenarios = $stmt->fetchAll();

// Общая статистика по всем сценариям
$totalIncome = 0;
$totalExpense = 0;
$totalItems = 0;

if (!empty($scenarios)) {
    $scenarioIds = array_column($scenarios, 'id');
    $placeholders = implode(',', array_fill(0, count($scenarioIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
            COUNT(*) as total_items
        FROM budget_items
        WHERE scenario_id IN ($placeholders)
    ");
    $stmt->execute($scenarioIds);
    $stats = $stmt->fetch();
    
    $totalIncome = $stats['total_income'] ?? 0;
    $totalExpense = $stats['total_expense'] ?? 0;
    $totalItems = $stats['total_items'] ?? 0;
}

$totalBalance = $totalIncome - $totalExpense;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
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
            <li><a href="/index.php" class="active">Dashboard</a></li>
            <li><a href="/scenarios.php">Сценарии</a></li>
            <li><a href="/budget.php">Бюджет</a></li>
            <li><a href="/predictions.php">Прогнозы</a></li>
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
            <!-- Статистические карточки -->
            <div class="stats-grid">
                <div class="stat-card income">
                    <div class="stat-label">Общий доход</div>
                    <div class="stat-value positive monospace"><?= number_format($totalIncome, 2, '.', ' ') ?> ₽</div>
                </div>
                
                <div class="stat-card expense">
                    <div class="stat-label">Общие расходы</div>
                    <div class="stat-value negative monospace"><?= number_format($totalExpense, 2, '.', ' ') ?> ₽</div>
                </div>
                
                <div class="stat-card <?= $totalBalance >= 0 ? '' : 'expense' ?>">
                    <div class="stat-label">Баланс</div>
                    <div class="stat-value <?= $totalBalance >= 0 ? 'positive' : 'negative' ?> monospace">
                        <?= number_format($totalBalance, 2, '.', ' ') ?> ₽
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Статей бюджета</div>
                    <div class="stat-value monospace"><?= $totalItems ?></div>
                </div>
            </div>
            
            <!-- Сценарии -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Ваши сценарии</h2>
                    <a href="/scenarios.php" class="btn btn-primary">Управление сценариями</a>
                </div>
                
                <?php if (empty($scenarios)): ?>
                    <div class="alert alert-warning">
                        У вас пока нет активных сценариев. Создайте первый сценарий для начала работы.
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Период</th>
                                    <th>Доход</th>
                                    <th>Расход</th>
                                    <th>Баланс</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scenarios as $scenario): ?>
                                    <?php
                                    // Получение статистики по сценарию
                                    $stmt = $pdo->prepare("
                                        SELECT 
                                            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                                            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                                        FROM budget_items
                                        WHERE scenario_id = ?
                                    ");
                                    $stmt->execute([$scenario['id']]);
                                    $scenarioStats = $stmt->fetch();
                                    $scenarioIncome = $scenarioStats['income'] ?? 0;
                                    $scenarioExpense = $scenarioStats['expense'] ?? 0;
                                    $scenarioBalance = $scenarioIncome - $scenarioExpense;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($scenario['name']) ?></strong></td>
                                        <td><?= date('d.m.Y', strtotime($scenario['start_date'])) ?> - <?= date('d.m.Y', strtotime($scenario['end_date'])) ?></td>
                                        <td class="amount positive monospace"><?= number_format($scenarioIncome, 2, '.', ' ') ?> ₽</td>
                                        <td class="amount negative monospace"><?= number_format($scenarioExpense, 2, '.', ' ') ?> ₽</td>
                                        <td class="amount monospace <?= $scenarioBalance >= 0 ? 'positive' : 'negative' ?>">
                                            <?= number_format($scenarioBalance, 2, '.', ' ') ?> ₽
                                        </td>
                                        <td>
                                            <a href="/budget.php?scenario_id=<?= $scenario['id'] ?>" class="btn btn-outline" style="padding: 5px 10px;">Бюджет</a>
                                            <a href="/predictions.php?scenario_id=<?= $scenario['id'] ?>" class="btn btn-accent" style="padding: 5px 10px;">Прогноз</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- График (если есть данные) -->
            <?php if ($totalItems > 0 && !empty($scenarios)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Динамика доходов и расходов</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function logout() {
            fetch('/api/auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.href = '/login.php');
        }
        
        <?php if ($totalItems > 0 && !empty($scenarios)): ?>
        // Инициализация графика
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('mainChart').getContext('2d');
            
            // Загрузка данных для графика
            fetch('/api/budget.php?action=summary&scenario_id=<?= $scenarios[0]['id'] ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const labels = data.data.by_month.map(item => item.month);
                        const incomeData = data.data.by_month.map(item => parseFloat(item.income));
                        const expenseData = data.data.by_month.map(item => parseFloat(item.expense));
                        
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Доходы',
                                        data: incomeData,
                                        borderColor: '#27AE60',
                                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Расходы',
                                        data: expenseData,
                                        borderColor: '#E74C3C',
                                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                        fill: true,
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
                });
        });
        <?php endif; ?>
    </script>
</body>
</html>
