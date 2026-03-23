<?php
function getDemoData(): array
{
    return [
        'navItems' => [
            [
                'id' => 'dashboard',
                'title' => 'Дашборд',
                'icon' => 'dashboard',
                'heading' => 'Проектор финансовых планов малого бизнеса',
                'slogan' => 'Бюджеты, прогнозы выручки и сценарии развития на квартал или год вперед.',
            ],
            [
                'id' => 'articles',
                'title' => 'Статьи бюджета',
                'icon' => 'articles',
                'heading' => 'Планирование статей бюджета',
                'slogan' => 'Контролируйте лимиты, обязательства и точки перерасхода',
            ],
            [
                'id' => 'scenarios',
                'title' => 'Сценарии прогноза',
                'icon' => 'scenarios',
                'heading' => 'Сценарный анализ',
                'slogan' => 'CRUD для сценариев бюджета и прогнозов выручки в одном интерфейсе',
            ],
            [
                'id' => 'reports',
                'title' => 'Отчеты',
                'icon' => 'reports',
                'heading' => 'Управленческая отчетность',
                'slogan' => 'Ключевые показатели и структура прибыли в одном месте',
            ],
            [
                'id' => 'settings',
                'title' => 'Настройки',
                'icon' => 'settings',
                'heading' => 'Параметры панели',
                'slogan' => 'Персонализируйте интерфейс и режим оповещений',
            ],
        ],
        'productOverview' => [
            'title' => 'Проектор финансовых планов малого бизнеса',
            'description' => 'Удобный финансовый проектор для малых предприятий, позволяющий составлять бюджеты и прогнозы выручки на квартал или год вперед. Все сценарии бюджетов сохраняются и поддерживают операции CRUD.',
            'highlights' => ['Квартальные и годовые прогнозы', 'Сценарии с операциями CRUD', 'Контроль бюджета и выручки'],
        ],
        'dashboardStats' => [
            ['label' => 'Выручка (Март)', 'value' => 540000, 'state' => 'success', 'format' => 'currency'],
            ['label' => 'Расходы (Март)', 'value' => 210500, 'state' => 'danger', 'format' => 'currency'],
            ['label' => 'Чистая прибыль', 'value' => 329500, 'state' => 'neutral', 'format' => 'currency'],
            ['label' => 'Точность прогноза', 'value' => 98.2, 'state' => 'neutral', 'format' => 'percent'],
        ],
        'transactions' => [
            ['category' => 'Аренда офиса', 'amount' => -80000, 'type' => 'negative'],
            ['category' => 'Продажа ПО', 'amount' => 125000, 'type' => 'positive'],
            ['category' => 'ФОТ (Зарплаты)', 'amount' => -110000, 'type' => 'negative'],
            ['category' => 'Консалтинг', 'amount' => 45000, 'type' => 'positive'],
        ],
        'budgetArticles' => [
            ['name' => 'Маркетинг', 'limit' => 150000, 'spent' => 117500],
            ['name' => 'Операционные расходы', 'limit' => 240000, 'spent' => 210500],
            ['name' => 'Разработка', 'limit' => 320000, 'spent' => 275000],
            ['name' => 'Административные', 'limit' => 95000, 'spent' => 88000],
        ],
        'scenarios' => [
            [
                'id' => 'optimistic',
                'name' => 'Оптимистичный',
                'delta' => 14,
                'description' => 'Рост продаж и расширение среднего чека.',
                'points' => [540000, 580000, 620000, 680000],
            ],
            [
                'id' => 'base',
                'name' => 'Базовый',
                'delta' => 7,
                'description' => 'Стабильная динамика с текущими контрактами.',
                'points' => [540000, 560000, 590000, 615000],
            ],
            [
                'id' => 'stress',
                'name' => 'Стресс',
                'delta' => -6,
                'description' => 'Снижение спроса и рост операционных затрат.',
                'points' => [540000, 510000, 500000, 485000],
            ],
        ],
        'reports' => [
            ['title' => 'P&L за квартал', 'period' => 'Январь — Март', 'status' => 'Готов'],
            ['title' => 'Cash Flow', 'period' => 'Март', 'status' => 'Обновлен сегодня'],
            ['title' => 'Факторный анализ отклонений', 'period' => 'Q1', 'status' => 'Требует согласования'],
        ],
        'settings' => [
            'accent' => '#27AE60',
            'compactMode' => false,
            'notifications' => true,
        ],
        'chartData' => [
            'labels' => ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
            'actual' => [420000, 480000, 540000, null, null, null],
        ],
    ];
}

function formatCurrency(int|float $value, bool $signed = false): string
{
    $formatted = number_format(abs($value), 0, ',', ' ');
    $prefix = $signed ? ($value >= 0 ? '+' : '-') : '';

    return sprintf('%s%s ₽', $prefix, $formatted);
}

function formatPercent(int|float $value): string
{
    return sprintf('%s%%', rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'));
}

function deriveBudgetStatus(int|float $limit, int|float $spent): string
{
    if ($limit <= 0) {
        return 'Без лимита';
    }

    return ($spent / $limit) >= 0.85 ? 'Риск перерасхода' : 'В лимите';
}

function openDashboardDatabase(string $dbPath): ?SQLite3
{
    if (!class_exists('SQLite3')) {
        return null;
    }

    $dbDirectory = dirname($dbPath);
    if (!is_dir($dbDirectory)) {
        mkdir($dbDirectory, 0777, true);
    }

    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);
    initializeDashboardDatabase($db);

    return $db;
}

function initializeDashboardDatabase(SQLite3 $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            value REAL NOT NULL,
            state TEXT NOT NULL,
            format TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS budget_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            limit_amount REAL NOT NULL,
            spent_amount REAL NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS scenarios (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            delta REAL NOT NULL,
            description TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS scenario_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            scenario_id TEXT NOT NULL,
            period_index INTEGER NOT NULL,
            amount REAL NOT NULL,
            FOREIGN KEY (scenario_id) REFERENCES scenarios(id)
        );
        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            period TEXT NOT NULL,
            status TEXT NOT NULL,
            sort_order INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS chart_periods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            actual_amount REAL NULL,
            sort_order INTEGER NOT NULL
        );'
    );

    $demoData = getDemoData();
    $tables = [
        'dashboard_stats' => 'dashboardStats',
        'transactions' => 'transactions',
        'budget_articles' => 'budgetArticles',
        'scenarios' => 'scenarios',
        'reports' => 'reports',
        'chart_periods' => 'chartData',
        'settings' => 'settings',
    ];

    $needsSeed = false;
    foreach (array_keys($tables) as $table) {
        $count = (int) $db->querySingle("SELECT COUNT(*) FROM {$table}");
        if ($count === 0) {
            $needsSeed = true;
            break;
        }
    }

    $scenarioPointsCount = (int) $db->querySingle('SELECT COUNT(*) FROM scenario_points');
    if ($scenarioPointsCount === 0) {
        $needsSeed = true;
    }

    if (!$needsSeed) {
        return;
    }

    $db->exec('BEGIN');

    $db->exec('DELETE FROM dashboard_stats');
    $statement = $db->prepare('INSERT INTO dashboard_stats (label, value, state, format, sort_order) VALUES (:label, :value, :state, :format, :sort_order)');
    foreach ($demoData['dashboardStats'] as $index => $stat) {
        $statement->bindValue(':label', $stat['label'], SQLITE3_TEXT);
        $statement->bindValue(':value', $stat['value'], SQLITE3_FLOAT);
        $statement->bindValue(':state', $stat['state'], SQLITE3_TEXT);
        $statement->bindValue(':format', $stat['format'], SQLITE3_TEXT);
        $statement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('DELETE FROM transactions');
    $statement = $db->prepare('INSERT INTO transactions (category, amount, type, sort_order) VALUES (:category, :amount, :type, :sort_order)');
    foreach ($demoData['transactions'] as $index => $transaction) {
        $statement->bindValue(':category', $transaction['category'], SQLITE3_TEXT);
        $statement->bindValue(':amount', $transaction['amount'], SQLITE3_FLOAT);
        $statement->bindValue(':type', $transaction['type'], SQLITE3_TEXT);
        $statement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('DELETE FROM budget_articles');
    $statement = $db->prepare('INSERT INTO budget_articles (name, limit_amount, spent_amount, sort_order) VALUES (:name, :limit_amount, :spent_amount, :sort_order)');
    foreach ($demoData['budgetArticles'] as $index => $article) {
        $statement->bindValue(':name', $article['name'], SQLITE3_TEXT);
        $statement->bindValue(':limit_amount', $article['limit'], SQLITE3_FLOAT);
        $statement->bindValue(':spent_amount', $article['spent'], SQLITE3_FLOAT);
        $statement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('DELETE FROM scenario_points');
    $db->exec('DELETE FROM scenarios');
    $scenarioStatement = $db->prepare('INSERT INTO scenarios (id, name, delta, description, sort_order) VALUES (:id, :name, :delta, :description, :sort_order)');
    $pointStatement = $db->prepare('INSERT INTO scenario_points (scenario_id, period_index, amount) VALUES (:scenario_id, :period_index, :amount)');
    foreach ($demoData['scenarios'] as $index => $scenario) {
        $scenarioStatement->bindValue(':id', $scenario['id'], SQLITE3_TEXT);
        $scenarioStatement->bindValue(':name', $scenario['name'], SQLITE3_TEXT);
        $scenarioStatement->bindValue(':delta', $scenario['delta'], SQLITE3_FLOAT);
        $scenarioStatement->bindValue(':description', $scenario['description'], SQLITE3_TEXT);
        $scenarioStatement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $scenarioStatement->execute();
        $scenarioStatement->reset();

        foreach ($scenario['points'] as $periodIndex => $amount) {
            $pointStatement->bindValue(':scenario_id', $scenario['id'], SQLITE3_TEXT);
            $pointStatement->bindValue(':period_index', $periodIndex, SQLITE3_INTEGER);
            $pointStatement->bindValue(':amount', $amount, SQLITE3_FLOAT);
            $pointStatement->execute();
            $pointStatement->reset();
        }
    }

    $db->exec('DELETE FROM reports');
    $statement = $db->prepare('INSERT INTO reports (title, period, status, sort_order) VALUES (:title, :period, :status, :sort_order)');
    foreach ($demoData['reports'] as $index => $report) {
        $statement->bindValue(':title', $report['title'], SQLITE3_TEXT);
        $statement->bindValue(':period', $report['period'], SQLITE3_TEXT);
        $statement->bindValue(':status', $report['status'], SQLITE3_TEXT);
        $statement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('DELETE FROM settings');
    $statement = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)');
    foreach ($demoData['settings'] as $key => $value) {
        $statement->bindValue(':setting_key', $key, SQLITE3_TEXT);
        $statement->bindValue(':setting_value', is_bool($value) ? ($value ? '1' : '0') : (string) $value, SQLITE3_TEXT);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('DELETE FROM chart_periods');
    $statement = $db->prepare('INSERT INTO chart_periods (label, actual_amount, sort_order) VALUES (:label, :actual_amount, :sort_order)');
    foreach ($demoData['chartData']['labels'] as $index => $label) {
        $statement->bindValue(':label', $label, SQLITE3_TEXT);
        if ($demoData['chartData']['actual'][$index] === null) {
            $statement->bindValue(':actual_amount', null, SQLITE3_NULL);
        } else {
            $statement->bindValue(':actual_amount', $demoData['chartData']['actual'][$index], SQLITE3_FLOAT);
        }
        $statement->bindValue(':sort_order', $index, SQLITE3_INTEGER);
        $statement->execute();
        $statement->reset();
    }

    $db->exec('COMMIT');
}

function loadDashboardData(?SQLite3 $db): array
{
    $data = getDemoData();
    if (!$db) {
        return $data;
    }

    $data['dashboardStats'] = [];
    $result = $db->query('SELECT label, value, state, format FROM dashboard_stats ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data['dashboardStats'][] = [
            'label' => $row['label'],
            'value' => (float) $row['value'],
            'state' => $row['state'],
            'format' => $row['format'],
        ];
    }

    $data['transactions'] = [];
    $result = $db->query('SELECT category, amount, type FROM transactions ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data['transactions'][] = [
            'category' => $row['category'],
            'amount' => (float) $row['amount'],
            'type' => $row['type'],
        ];
    }

    $data['budgetArticles'] = [];
    $result = $db->query('SELECT name, limit_amount, spent_amount FROM budget_articles ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $limitAmount = (float) $row['limit_amount'];
        $spentAmount = (float) $row['spent_amount'];
        $data['budgetArticles'][] = [
            'name' => $row['name'],
            'limit' => $limitAmount,
            'spent' => $spentAmount,
            'status' => deriveBudgetStatus($limitAmount, $spentAmount),
        ];
    }

    $data['scenarios'] = [];
    $data['chartData']['scenarios'] = [];
    $pointStatement = $db->prepare('SELECT amount FROM scenario_points WHERE scenario_id = :scenario_id ORDER BY period_index');
    $result = $db->query('SELECT id, name, delta, description FROM scenarios ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pointStatement->bindValue(':scenario_id', $row['id'], SQLITE3_TEXT);
        $pointsResult = $pointStatement->execute();
        $points = [];
        while ($point = $pointsResult->fetchArray(SQLITE3_ASSOC)) {
            $points[] = (float) $point['amount'];
        }
        $pointStatement->reset();

        $data['scenarios'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'delta' => (float) $row['delta'],
            'description' => $row['description'],
            'points' => $points,
        ];
        $data['chartData']['scenarios'][$row['id']] = $points;
    }

    $data['reports'] = [];
    $result = $db->query('SELECT title, period, status FROM reports ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data['reports'][] = $row;
    }

    $data['settings'] = [
        'accent' => '#27AE60',
        'compactMode' => false,
        'notifications' => true,
    ];
    $result = $db->query('SELECT setting_key, setting_value FROM settings');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data['settings'][$row['setting_key']] = match ($row['setting_key']) {
            'compactMode', 'notifications' => $row['setting_value'] === '1',
            default => $row['setting_value'],
        };
    }

    $data['chartData']['labels'] = [];
    $data['chartData']['actual'] = [];
    $result = $db->query('SELECT label, actual_amount FROM chart_periods ORDER BY sort_order');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data['chartData']['labels'][] = $row['label'];
        $data['chartData']['actual'][] = $row['actual_amount'] === null ? null : (float) $row['actual_amount'];
    }

    return $data;
}

$db = null;
$dbError = null;

try {
    $db = openDashboardDatabase(__DIR__ . '/data/finhorizon.sqlite3');
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

$data = loadDashboardData($db);

$navItems = $data['navItems'];
$productOverview = $data['productOverview'];
$dashboardStats = array_map(
    static function (array $stat): array {
        $formattedValue = $stat['format'] === 'percent'
            ? formatPercent($stat['value'])
            : formatCurrency($stat['value']);

        return $stat + ['displayValue' => $formattedValue];
    },
    $data['dashboardStats']
);
$transactions = array_map(
    static fn (array $transaction): array => $transaction + ['displayAmount' => formatCurrency($transaction['amount'], true)],
    $data['transactions']
);
$budgetArticles = array_map(
    static fn (array $article): array => $article + [
        'displayLimit' => formatCurrency($article['limit']),
        'displaySpent' => formatCurrency($article['spent']),
    ],
    $data['budgetArticles']
);
$scenarios = array_map(
    static fn (array $scenario): array => $scenario + ['displayDelta' => formatPercent($scenario['delta'])],
    $data['scenarios']
);
$reports = $data['reports'];
$settings = $data['settings'];
$chartData = $data['chartData'];
$defaultScenario = $scenarios[0]['id'] ?? 'base';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ФинГоризонт — Проектор финансовых планов</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Roboto+Mono&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body style="--accent-color: <?= htmlspecialchars($settings['accent'], ENT_QUOTES, 'UTF-8'); ?>;">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="logo-container">
                <img class="brand-logo" src="img/logo.png" alt="Логотип ФинГоризонт">
                <div>
                    <div class="brand-name">ФинГоризонт</div>
                    <div class="brand-subtitle">Проектор финансовых планов</div>
                </div>
            </div>

            <div class="sidebar-product-card">
                <p class="sidebar-product-card__eyebrow">Малый бизнес</p>
                <strong><?= htmlspecialchars($productOverview['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <span><?= htmlspecialchars($productOverview['description'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <nav class="sidebar-nav" aria-label="Основная навигация">
                <ul>
                    <?php foreach ($navItems as $index => $item): ?>
                        <li>
                            <button
                                class="nav-button <?= $index === 0 ? 'is-active' : ''; ?>"
                                type="button"
                                data-section-target="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-heading="<?= htmlspecialchars($item['heading'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-slogan="<?= htmlspecialchars($item['slogan'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <span class="nav-button__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <use href="img/icons.svg#<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></use>
                                    </svg>
                                </span>
                                <span><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="sidebar-summary">
                <div class="sidebar-summary__label">Источник данных</div>
                <strong><?= $db ? 'SQLite3 подключена' : 'Демо-режим'; ?></strong>
                <span><?= $db ? 'Показатели, лимиты и сценарии читаются из data/finhorizon.sqlite3' : 'Показываем резервные данные из PHP-массива'; ?></span>
                <?php if ($dbError): ?>
                    <small><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1 id="pageHeading"><?= htmlspecialchars($navItems[0]['heading'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="slogan" id="pageSlogan"><?= htmlspecialchars($navItems[0]['slogan'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="profile-card">
                    <span class="profile-card__label">Администратор</span>
                    <strong>Иван Иванов</strong>
                </div>
            </header>

            <section class="hero-card">
                <div class="hero-card__content">
                    <p class="hero-card__eyebrow">Описание продукта</p>
                    <h2><?= htmlspecialchars($productOverview['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?= htmlspecialchars($productOverview['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul class="hero-card__list">
                        <?php foreach ($productOverview['highlights'] as $highlight): ?>
                            <li><?= htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="hero-card__media">
                    <img src="img/logo.png" alt="Логотип продукта ФинГоризонт">
                </div>
            </section>

            <section class="content-section is-active" id="section-dashboard" data-section="dashboard">
                <div class="dashboard-grid">
                    <?php foreach ($dashboardStats as $stat): ?>
                        <article class="card <?= $stat['state'] === 'neutral' ? '' : 'card--' . $stat['state']; ?>">
                            <div class="card-label"><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="card-value"><?= htmlspecialchars($stat['displayValue'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="content-row">
                    <section class="content-block content-block--chart">
                        <div class="block-header">
                            <div>
                                <h2>Прогноз выручки и лимиты</h2>
                                <p>Сценарии и значения графика подгружаются из SQLite3 без правки шаблона.</p>
                            </div>
                            <div class="scenario-switcher" id="scenarioSwitcher">
                                <?php foreach ($scenarios as $index => $scenario): ?>
                                    <button class="chip <?= $index === 0 ? 'is-active' : ''; ?>" type="button" data-scenario="<?= htmlspecialchars($scenario['id'], ENT_QUOTES, 'UTF-8'); ?>" data-scenario-name="<?= htmlspecialchars($scenario['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($scenario['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="chart-surface">
                            <canvas id="forecastChart"></canvas>
                        </div>
                    </section>

                    <section class="content-block">
                        <h2>Последние операции</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Категория</th>
                                    <th class="align-right">Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="amount <?= htmlspecialchars($transaction['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($transaction['displayAmount'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            </section>

            <section class="content-section" id="section-articles" data-section="articles">
                <div class="content-block">
                    <div class="block-header">
                        <div>
                            <h2>Статьи бюджета</h2>
                            <p>Лимиты и факт расходов тоже читаются из SQLite3 и рассчитывают статус автоматически.</p>
                        </div>
                        <button class="button button--secondary" type="button" id="highlightBudgetButton">Подсветить риски</button>
                    </div>
                    <table id="budgetTable">
                        <thead>
                            <tr>
                                <th>Статья</th>
                                <th>Лимит</th>
                                <th>Использовано</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgetArticles as $article): ?>
                                <tr data-status="<?= htmlspecialchars($article['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <td><?= htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($article['displayLimit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($article['displaySpent'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="status-pill <?= $article['status'] === 'Риск перерасхода' ? 'status-pill--warning' : 'status-pill--success'; ?>"><?= htmlspecialchars($article['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="content-section" id="section-scenarios" data-section="scenarios">
                <div class="cards-row">
                    <?php foreach ($scenarios as $index => $scenario): ?>
                        <article class="scenario-card <?= $index === 0 ? 'is-selected' : ''; ?>" data-scenario-card="<?= htmlspecialchars($scenario['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="scenario-card__header">
                                <h2><?= htmlspecialchars($scenario['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                <span class="scenario-card__delta"><?= htmlspecialchars($scenario['displayDelta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <p><?= htmlspecialchars($scenario['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <button class="button" type="button" data-scenario-apply="<?= htmlspecialchars($scenario['id'], ENT_QUOTES, 'UTF-8'); ?>">Применить к графику</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="content-section" id="section-reports" data-section="reports">
                <div class="content-block">
                    <h2>Доступные отчеты</h2>
                    <div class="report-list">
                        <?php foreach ($reports as $report): ?>
                            <article class="report-card">
                                <div>
                                    <h3><?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p><?= htmlspecialchars($report['period'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="status-pill"><?= htmlspecialchars($report['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="content-section" id="section-settings" data-section="settings">
                <div class="content-block">
                    <h2>Настройки интерфейса</h2>
                    <form class="settings-form" id="settingsForm">
                        <label class="field">
                            <span>Акцентный цвет</span>
                            <input type="color" name="accent" value="<?= htmlspecialchars($settings['accent'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="compactMode" <?= $settings['compactMode'] ? 'checked' : ''; ?>>
                            <span>Компактный режим карточек</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="notifications" <?= $settings['notifications'] ? 'checked' : ''; ?>>
                            <span>Показывать уведомления о рисках</span>
                        </label>
                        <div class="settings-actions">
                            <button class="button" type="submit">Сохранить настройки</button>
                            <button class="button button--secondary" type="button" id="resetSettingsButton">Сбросить</button>
                        </div>
                        <p class="settings-hint" id="settingsHint">Настройки можно хранить локально, а данные дашборда — в SQLite3.</p>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        window.dashboardConfig = <?= json_encode([
            'defaultScenario' => $defaultScenario,
            'settings' => $settings,
            'chart' => $chartData,
            'scenarioNames' => array_column($scenarios, 'name', 'id'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
    </script>
    <script src="js/app.js"></script>
</body>
</html>
