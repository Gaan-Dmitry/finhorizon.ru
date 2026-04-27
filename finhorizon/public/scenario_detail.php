<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детали сценария - ФинГоризонт</title>
    
    <!-- Шрифты -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Condensed:wght@400;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Стили -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-logo">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#2C3E50" stroke-width="3"/>
                    <path d="M 20 70 L 40 50 L 60 55 L 80 30" fill="none" stroke="#27AE60" stroke-width="4" stroke-linecap="round"/>
                    <circle cx="80" cy="30" r="5" fill="#27AE60"/>
                </svg>
            </div>
            <span>ФинГоризонт</span>
        </a>
        <ul class="navbar-nav">
            <li><a href="index.php">Главная</a></li>
            <li><a href="scenarios.php">Сценарии</a></li>
            <li><a href="#" id="userMenu">Профиль</a></li>
        </ul>
    </nav>

    <!-- Основной контент -->
    <main class="container">
        <!-- Заголовок -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" id="scenarioTitle">Загрузка...</h2>
                <div>
                    <button class="btn btn-accent" onclick="generateForecast()">
                        📊 Сгенерировать прогноз
                    </button>
                    <a href="index.php" class="btn btn-outline">Назад</a>
                </div>
            </div>
            <p id="scenarioDescription"></p>
            <p><strong>Период:</strong> <span id="scenarioPeriod"></span></p>
        </div>

        <!-- Статистика -->
        <div class="dashboard-grid">
            <div class="stat-card accent">
                <div class="stat-label">Доходы (факт)</div>
                <div class="stat-value" id="actualIncome">0 ₽</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">Расходы (факт)</div>
                <div class="stat-value" id="actualExpense">0 ₽</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Прогноз доходов</div>
                <div class="stat-value" id="forecastIncome">0 ₽</div>
            </div>
        </div>

        <!-- Таблица бюджета -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Бюджет по периодам</h2>
                <button class="btn btn-primary" onclick="openAddItemModal()">+ Добавить статью</button>
            </div>
            <div style="overflow-x: auto;">
                <table class="table" id="budgetTable">
                    <thead>
                        <tr>
                            <th>Категория</th>
                            <th>Тип</th>
                            <th id="periodsHeader">Периоды</th>
                        </tr>
                    </thead>
                    <tbody id="budgetBody">
                        <tr>
                            <td colspan="3" class="text-center">Загрузка...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- График прогноза -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Прогноз на основе исторических данных</h2>
            </div>
            <div class="chart-container">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>
    </main>

    <!-- Модальное окно добавления статьи -->
    <div class="modal-overlay" id="addItemModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Добавить статью бюджета</h3>
            </div>
            <form id="addItemForm">
                <input type="hidden" id="itemScenarioId">
                <div class="form-group">
                    <label class="form-label" for="itemCategory">Категория *</label>
                    <select class="form-control" id="itemCategory" required>
                        <option value="">Выберите категорию</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="itemPeriod">Период *</label>
                    <input type="month" class="form-control" id="itemPeriod" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="itemAmount">Сумма *</label>
                    <input type="number" class="form-control" id="itemAmount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="itemComment">Комментарий</label>
                    <textarea class="form-control" id="itemComment" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline modal-close">Отмена</button>
                    <button type="submit" class="btn btn-accent">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Уведомления -->
    <div id="notifications"></div>

    <!-- Скрипты -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/scenario_detail.js"></script>
</body>
</html>
