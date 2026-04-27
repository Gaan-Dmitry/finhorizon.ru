<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ФинГоризонт - Планируйте уверенно</title>
    
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
        <!-- Статистика -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-label">Активных сценариев</div>
                <div class="stat-value" id="activeScenarios">0</div>
            </div>
            <div class="stat-card accent">
                <div class="stat-label">Общий доход</div>
                <div class="stat-value" id="totalIncome">0 ₽</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">Общие расходы</div>
                <div class="stat-value" id="totalExpense">0 ₽</div>
            </div>
        </div>

        <!-- Сценарии -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Мои сценарии</h2>
                <button class="btn btn-accent" onclick="openCreateScenarioModal()">
                    + Новый сценарий
                </button>
            </div>
            <table class="table" id="scenariosTable">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Период</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="scenariosBody">
                    <tr>
                        <td colspan="4" class="text-center">Загрузка...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Графики -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Аналитика</h2>
            </div>
            <div class="chart-container">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </main>

    <!-- Модальное окно создания сценария -->
    <div class="modal-overlay" id="createScenarioModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Новый сценарий</h3>
            </div>
            <form id="createScenarioForm">
                <div class="form-group">
                    <label class="form-label" for="scenarioName">Название *</label>
                    <input type="text" class="form-control" id="scenarioName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scenarioDescription">Описание</label>
                    <textarea class="form-control" id="scenarioDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scenarioStartDate">Дата начала *</label>
                    <input type="date" class="form-control" id="scenarioStartDate" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scenarioEndDate">Дата окончания *</label>
                    <input type="date" class="form-control" id="scenarioEndDate" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline modal-close">Отмена</button>
                    <button type="submit" class="btn btn-accent">Создать</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Уведомления -->
    <div id="notifications"></div>

    <!-- Скрипты -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
