<?php
$navItems = [
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
];

$productOverview = [
    'title' => 'Проектор финансовых планов малого бизнеса',
    'description' => 'Удобный финансовый проектор для малых предприятий, позволяющий составлять бюджеты и прогнозы выручки на квартал или год вперед. Все сценарии бюджетов сохраняются и поддерживают операции CRUD.',
    'highlights' => ['Квартальные и годовые прогнозы', 'Сценарии с операциями CRUD', 'Контроль бюджета и выручки'],
];

$dashboardStats = [
    ['label' => 'Выручка (Март)', 'value' => '540 000 ₽', 'state' => 'success'],
    ['label' => 'Расходы (Март)', 'value' => '210 500 ₽', 'state' => 'danger'],
    ['label' => 'Чистая прибыль', 'value' => '329 500 ₽', 'state' => 'neutral'],
    ['label' => 'Точность прогноза', 'value' => '98.2%', 'state' => 'neutral'],
];

$transactions = [
    ['category' => 'Аренда офиса', 'amount' => '-80 000 ₽', 'type' => 'negative'],
    ['category' => 'Продажа ПО', 'amount' => '+125 000 ₽', 'type' => 'positive'],
    ['category' => 'ФОТ (Зарплаты)', 'amount' => '-110 000 ₽', 'type' => 'negative'],
    ['category' => 'Консалтинг', 'amount' => '+45 000 ₽', 'type' => 'positive'],
];

$budgetArticles = [
    ['name' => 'Маркетинг', 'limit' => '150 000 ₽', 'spent' => '117 500 ₽', 'status' => 'В лимите'],
    ['name' => 'Операционные расходы', 'limit' => '240 000 ₽', 'spent' => '210 500 ₽', 'status' => 'Риск перерасхода'],
    ['name' => 'Разработка', 'limit' => '320 000 ₽', 'spent' => '275 000 ₽', 'status' => 'В лимите'],
    ['name' => 'Административные', 'limit' => '95 000 ₽', 'spent' => '88 000 ₽', 'status' => 'В лимите'],
];

$scenarios = [
    [
        'id' => 'optimistic',
        'name' => 'Оптимистичный',
        'delta' => '+14%',
        'description' => 'Рост продаж и расширение среднего чека.',
    ],
    [
        'id' => 'base',
        'name' => 'Базовый',
        'delta' => '+7%',
        'description' => 'Стабильная динамика с текущими контрактами.',
    ],
    [
        'id' => 'stress',
        'name' => 'Стресс',
        'delta' => '-6%',
        'description' => 'Снижение спроса и рост операционных затрат.',
    ],
];

$reports = [
    ['title' => 'P&L за квартал', 'period' => 'Январь — Март', 'status' => 'Готов'],
    ['title' => 'Cash Flow', 'period' => 'Март', 'status' => 'Обновлен сегодня'],
    ['title' => 'Факторный анализ отклонений', 'period' => 'Q1', 'status' => 'Требует согласования'],
];

$settings = [
    'accent' => '#27AE60',
    'compactMode' => false,
    'notifications' => true,
];

$chartData = [
    'labels' => ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
    'actual' => [420000, 480000, 540000, null, null, null],
    'scenarios' => [
        'optimistic' => [540000, 580000, 620000, 680000],
        'base' => [540000, 560000, 590000, 615000],
        'stress' => [540000, 510000, 500000, 485000],
    ],
];
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
                <div class="sidebar-summary__label">Финансовый статус</div>
                <strong>Стабильный рост</strong>
                <span>План выполнен на 103.4%</span>
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
                            <div class="card-value"><?= htmlspecialchars($stat['value'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="content-row">
                    <section class="content-block content-block--chart">
                        <div class="block-header">
                            <div>
                                <h2>Прогноз выручки и лимиты</h2>
                                <p>Сценарии перестраиваются без перезагрузки страницы.</p>
                            </div>
                            <div class="scenario-switcher" id="scenarioSwitcher">
                                <?php foreach ($scenarios as $index => $scenario): ?>
                                    <button class="chip <?= $index === 0 ? 'is-active' : ''; ?>" type="button" data-scenario="<?= htmlspecialchars($scenario['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($scenario['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <canvas id="forecastChart" height="120"></canvas>
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
                                            <?= htmlspecialchars($transaction['amount'], ENT_QUOTES, 'UTF-8'); ?>
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
                            <p>PHP формирует таблицу лимитов, а JS выделяет критические строки.</p>
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
                                    <td><?= htmlspecialchars($article['limit'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($article['spent'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                                <span class="scenario-card__delta"><?= htmlspecialchars($scenario['delta'], ENT_QUOTES, 'UTF-8'); ?></span>
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
                            <span>Цвет акцента</span>
                            <input type="color" name="accent" value="<?= htmlspecialchars($settings['accent'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="compactMode" <?= $settings['compactMode'] ? 'checked' : ''; ?>>
                            <span>Компактный режим карточек</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="notifications" <?= $settings['notifications'] ? 'checked' : ''; ?>>
                            <span>Оповещения о рисках бюджета</span>
                        </label>
                        <div class="settings-actions">
                            <button class="button" type="submit">Сохранить локально</button>
                            <button class="button button--secondary" type="button" id="resetSettingsButton">Сбросить</button>
                        </div>
                    </form>
                    <p class="settings-hint" id="settingsHint">Изменения применяются мгновенно и сохраняются в браузере.</p>
                </div>
            </section>
        </main>
    </div>

    <script>
        window.dashboardConfig = <?= json_encode([
            'chart' => $chartData,
            'defaultScenario' => 'optimistic',
            'sections' => array_column($navItems, null, 'id'),
            'settings' => $settings,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="js/app.js"></script>
</body>
</html>
