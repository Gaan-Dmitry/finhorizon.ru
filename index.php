<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/config.php';

function redirectTo(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function appPath(array $query = []): string
{
    $path = $_SERVER['PHP_SELF'] ?? 'index.php';
    $path = $path !== '' ? $path : 'index.php';

    if ($query === []) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function flash(?string $type = null, ?string $message = null): array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];

        return $_SESSION['flash'];
    }

    $payload = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($payload) ? $payload : [];
}

function formatCurrency(float $value, bool $signed = false): string
{
    $prefix = $signed ? ($value >= 0 ? '+' : '-') : '';

    return sprintf('%s%s ₽', $prefix, number_format(abs($value), 0, ',', ' '));
}

function formatPercent(float $value): string
{
    return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') . '%';
}

function monthLabelsRu(): array
{
    return [1 => 'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
}

function fullMonthLabel(DateTimeImmutable $date): string
{
    $months = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

    return sprintf('%s %s', $months[(int) $date->format('n')], $date->format('Y'));
}

function budgetStatus(float $limit, float $spent): string
{
    if ($limit <= 0.0) {
        return 'Нет лимита';
    }

    $ratio = $spent / $limit;

    return match (true) {
        $ratio >= 1 => 'Перерасход',
        $ratio >= 0.85 => 'Риск перерасхода',
        default => 'В лимите',
    };
}

function statusClass(string $status): string
{
    return match ($status) {
        'Перерасход', 'Просрочен', 'Нужна реакция' => 'status-pill--danger',
        'Риск перерасхода', 'В работе' => 'status-pill--warning',
        default => 'status-pill--success',
    };
}

function currentUser(PDO $pdo): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, c.id AS company_id, c.name AS company_name, c.industry, c.base_currency
         FROM users u
         INNER JOIN company_user cu ON cu.user_id = u.id
         INNER JOIN companies c ON c.id = cu.company_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => (int) $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function authenticate(PDO $pdo, string $email, string $password): ?array
{
    $statement = $pdo->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => mb_strtolower($email)]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

function registerUser(PDO $pdo, array $payload): void
{
    $email = mb_strtolower($payload['email']);
    $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $exists->execute(['email' => $email]);

    if ((int) $exists->fetchColumn() > 0) {
        throw new RuntimeException('Пользователь с таким email уже зарегистрирован.');
    }

    $pdo->beginTransaction();

    try {
        $company = $pdo->prepare(
            'INSERT INTO companies (name, industry, plan_name, timezone, base_currency, created_at)
             VALUES (:name, :industry, :plan_name, :timezone, :base_currency, NOW())'
        );
        $company->execute([
            'name' => $payload['company_name'],
            'industry' => $payload['industry'],
            'plan_name' => 'Growth',
            'timezone' => 'Europe/Moscow',
            'base_currency' => 'RUB',
        ]);
        $companyId = (int) $pdo->lastInsertId();

        $userStatement = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, created_at)
             VALUES (:full_name, :email, :password_hash, NOW())'
        );
        $userStatement->execute([
            'full_name' => $payload['full_name'],
            'email' => $email,
            'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $membership = $pdo->prepare(
            'INSERT INTO company_user (company_id, user_id, role_name, created_at)
             VALUES (:company_id, :user_id, :role_name, NOW())'
        );
        $membership->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role_name' => 'owner',
        ]);

        seedCompanyData($pdo, $companyId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function seedCompanyData(PDO $pdo, int $companyId): void
{
    $categories = [
        ['Подписки SaaS', 'revenue', 0],
        ['Консалтинг', 'revenue', 0],
        ['Зарплаты', 'expense', 320000],
        ['Маркетинг', 'expense', 150000],
        ['Инфраструктура', 'expense', 95000],
        ['Операционные расходы', 'expense', 80000],
    ];

    $categoryStatement = $pdo->prepare(
        'INSERT INTO budget_categories (company_id, name, category_type, monthly_limit, created_at)
         VALUES (:company_id, :name, :category_type, :monthly_limit, NOW())'
    );
    foreach ($categories as [$name, $type, $limit]) {
        $categoryStatement->execute([
            'company_id' => $companyId,
            'name' => $name,
            'category_type' => $type,
            'monthly_limit' => $limit,
        ]);
    }

    $categoryRows = $pdo->prepare('SELECT id, name FROM budget_categories WHERE company_id = :company_id');
    $categoryRows->execute(['company_id' => $companyId]);
    $categoryIds = [];
    foreach ($categoryRows->fetchAll() as $row) {
        $categoryIds[$row['name']] = (int) $row['id'];
    }

    $transactionStatement = $pdo->prepare(
        'INSERT INTO transactions (company_id, category_id, txn_date, description, amount, direction, source_type, created_at)
         VALUES (:company_id, :category_id, :txn_date, :description, :amount, :direction, :source_type, NOW())'
    );

    $transactions = [
        ['-45 days', 'Годовая подписка клиента A', 210000, 'inflow', 'Подписки SaaS'],
        ['-32 days', 'Консалтинг для ритейл-сети', 135000, 'inflow', 'Консалтинг'],
        ['-28 days', 'Фонд оплаты труда', 320000, 'outflow', 'Зарплаты'],
        ['-24 days', 'Контекстная реклама', 91000, 'outflow', 'Маркетинг'],
        ['-16 days', 'Подписка клиента B', 240000, 'inflow', 'Подписки SaaS'],
        ['-12 days', 'AWS / CDN / Email', 62000, 'outflow', 'Инфраструктура'],
        ['-9 days', 'Подписка клиента C', 280000, 'inflow', 'Подписки SaaS'],
        ['-5 days', 'ФОТ текущего месяца', 320000, 'outflow', 'Зарплаты'],
        ['-3 days', 'Продвижение webinar funnel', 78000, 'outflow', 'Маркетинг'],
        ['-1 days', 'Юр. и банк. сервисы', 36000, 'outflow', 'Операционные расходы'],
    ];

    foreach ($transactions as [$relativeDate, $description, $amount, $direction, $categoryName]) {
        $transactionStatement->execute([
            'company_id' => $companyId,
            'category_id' => $categoryIds[$categoryName],
            'txn_date' => (new DateTimeImmutable($relativeDate))->format('Y-m-d'),
            'description' => $description,
            'amount' => $amount,
            'direction' => $direction,
            'source_type' => 'manual',
        ]);
    }

    $scenarioStatement = $pdo->prepare(
        'INSERT INTO scenario_forecasts (company_id, scenario_code, scenario_name, forecast_month, revenue_forecast, expense_forecast, created_at)
         VALUES (:company_id, :scenario_code, :scenario_name, :forecast_month, :revenue_forecast, :expense_forecast, NOW())'
    );

    $baseMonth = new DateTimeImmutable('first day of this month');
    $templates = [
        'base' => ['Базовый', [[890000, 515000], [940000, 525000], [1010000, 540000], [1085000, 560000]]],
        'optimistic' => ['Рост', [[940000, 520000], [1025000, 535000], [1110000, 555000], [1190000, 575000]]],
        'stress' => ['Стресс', [[770000, 540000], [730000, 545000], [710000, 550000], [695000, 560000]]],
    ];

    foreach ($templates as $code => [$name, $rows]) {
        foreach ($rows as $index => [$revenue, $expense]) {
            $scenarioStatement->execute([
                'company_id' => $companyId,
                'scenario_code' => $code,
                'scenario_name' => $name,
                'forecast_month' => $baseMonth->modify(sprintf('+%d month', $index))->format('Y-m-01'),
                'revenue_forecast' => $revenue,
                'expense_forecast' => $expense,
            ]);
        }
    }
}

function handleAuth(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        session_destroy();
        session_start();
        flash('success', 'Вы вышли из аккаунта.');
        redirectTo(appPath());
    }

    if ($action === 'login') {
        $email = post('email');
        $password = post('password');

        if ($email === '' || $password === '') {
            flash('error', 'Укажите email и пароль.');
            redirectTo(appPath());
        }

        $user = authenticate($pdo, $email, $password);
        if (!$user) {
            flash('error', 'Неверный email или пароль.');
            redirectTo(appPath());
        }

        $_SESSION['user_id'] = (int) $user['id'];
        flash('success', 'Вход выполнен. Добро пожаловать в FinHorizon.');
        redirectTo(appPath());
    }

    if ($action === 'register') {
        $payload = [
            'full_name' => post('full_name'),
            'email' => post('email'),
            'company_name' => post('company_name'),
            'industry' => post('industry'),
            'password' => post('password'),
            'password_confirm' => post('password_confirm'),
        ];

        foreach (['full_name', 'email', 'company_name', 'industry', 'password', 'password_confirm'] as $field) {
            if ($payload[$field] === '') {
                flash('error', 'Заполните все поля регистрации.');
                redirectTo(appPath(['auth' => 'register']));
            }
        }

        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Укажите корректный email.');
            redirectTo(appPath(['auth' => 'register']));
        }

        if (mb_strlen($payload['password']) < 8) {
            flash('error', 'Пароль должен содержать минимум 8 символов.');
            redirectTo(appPath(['auth' => 'register']));
        }

        if ($payload['password'] !== $payload['password_confirm']) {
            flash('error', 'Пароли не совпадают.');
            redirectTo(appPath(['auth' => 'register']));
        }

        try {
            registerUser($pdo, $payload);
            $user = authenticate($pdo, $payload['email'], $payload['password']);
            if ($user) {
                $_SESSION['user_id'] = (int) $user['id'];
            }
            flash('success', 'Аккаунт создан. Демо-данные для вашей компании уже загружены.');
        } catch (Throwable $exception) {
            flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось создать аккаунт.');
        }

        redirectTo(appPath());
    }
}

function buildDashboard(PDO $pdo, array $user): array
{
    $companyId = (int) $user['company_id'];
    $now = new DateTimeImmutable('now');
    $monthStart = $now->modify('first day of this month')->format('Y-m-01');
    $monthEnd = $now->modify('last day of this month')->format('Y-m-d');
    $rolling30Start = $now->modify('-29 days')->format('Y-m-d');
    $historyStart = $now->modify('first day of -3 month')->format('Y-m-01');

    $statsStatement = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN direction = "inflow" AND txn_date BETWEEN ? AND ? THEN amount ELSE 0 END) AS revenue_month,
            SUM(CASE WHEN direction = "outflow" AND txn_date BETWEEN ? AND ? THEN amount ELSE 0 END) AS expenses_month,
            SUM(CASE WHEN direction = "inflow" AND txn_date BETWEEN ? AND ? THEN amount ELSE 0 END) AS cash_in_30,
            SUM(CASE WHEN direction = "outflow" AND txn_date BETWEEN ? AND ? THEN amount ELSE 0 END) AS cash_out_30
         FROM transactions
         WHERE company_id = ?'
    );
    $statsStatement->execute([
        $monthStart,
        $monthEnd,
        $monthStart,
        $monthEnd,
        $rolling30Start,
        $monthEnd,
        $rolling30Start,
        $monthEnd,
        $companyId,
    ]);
    $stats = $statsStatement->fetch() ?: [];

    $revenueMonth = (float) ($stats['revenue_month'] ?? 0);
    $expensesMonth = (float) ($stats['expenses_month'] ?? 0);
    $profitMonth = $revenueMonth - $expensesMonth;
    $cashRunwayMonths = $expensesMonth > 0 ? (($revenueMonth + 1) / max($expensesMonth, 1)) : 0;

    $forecastStatement = $pdo->prepare(
        'SELECT revenue_forecast, expense_forecast
         FROM scenario_forecasts
         WHERE company_id = :company_id AND scenario_code = "base" AND forecast_month = :forecast_month
         LIMIT 1'
    );
    $forecastStatement->execute([
        'company_id' => $companyId,
        'forecast_month' => $monthStart,
    ]);
    $baseForecast = $forecastStatement->fetch() ?: ['revenue_forecast' => 0, 'expense_forecast' => 0];
    $forecastRevenue = (float) $baseForecast['revenue_forecast'];
    $accuracy = $forecastRevenue > 0 ? max(0, 100 - abs(($revenueMonth - $forecastRevenue) / $forecastRevenue) * 100) : 100;

    $latestTransactions = $pdo->prepare(
        'SELECT t.description, t.amount, t.direction, t.txn_date, bc.name AS category_name
         FROM transactions t
         INNER JOIN budget_categories bc ON bc.id = t.category_id
         WHERE t.company_id = :company_id
         ORDER BY t.txn_date DESC, t.id DESC
         LIMIT 8'
    );
    $latestTransactions->execute(['company_id' => $companyId]);
    $transactions = array_map(
        static fn (array $row): array => [
            'description' => $row['description'],
            'category' => $row['category_name'],
            'date' => $row['txn_date'],
            'amount' => (float) $row['amount'],
            'type' => $row['direction'] === 'inflow' ? 'positive' : 'negative',
            'display_amount' => formatCurrency((float) $row['amount'], true),
        ],
        $latestTransactions->fetchAll()
    );

    $budgetStatement = $pdo->prepare(
        'SELECT bc.name, bc.monthly_limit,
                SUM(CASE WHEN t.direction = "outflow" AND DATE_FORMAT(t.txn_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m") THEN t.amount ELSE 0 END) AS spent_month
         FROM budget_categories bc
         LEFT JOIN transactions t ON t.category_id = bc.id AND t.company_id = bc.company_id
         WHERE bc.company_id = :company_id AND bc.category_type = "expense"
         GROUP BY bc.id, bc.name, bc.monthly_limit
         ORDER BY bc.name'
    );
    $budgetStatement->execute(['company_id' => $companyId]);
    $budgetArticles = [];
    foreach ($budgetStatement->fetchAll() as $row) {
        $limit = (float) $row['monthly_limit'];
        $spent = (float) $row['spent_month'];
        $status = budgetStatus($limit, $spent);
        $budgetArticles[] = [
            'name' => $row['name'],
            'limit' => $limit,
            'spent' => $spent,
            'display_limit' => formatCurrency($limit),
            'display_spent' => formatCurrency($spent),
            'status' => $status,
            'status_class' => statusClass($status),
        ];
    }

    $scenarioStatement = $pdo->prepare(
        'SELECT scenario_code, scenario_name, forecast_month, revenue_forecast, expense_forecast
         FROM scenario_forecasts
         WHERE company_id = :company_id
         ORDER BY FIELD(scenario_code, "optimistic", "base", "stress"), forecast_month'
    );
    $scenarioStatement->execute(['company_id' => $companyId]);
    $scenarioRows = $scenarioStatement->fetchAll();
    $scenarioMap = [];
    foreach ($scenarioRows as $row) {
        $scenarioCode = $row['scenario_code'];
        $scenarioMap[$scenarioCode] ??= [
            'id' => $scenarioCode,
            'name' => $row['scenario_name'],
            'points' => [],
            'revenue_total' => 0.0,
            'expense_total' => 0.0,
        ];
        $scenarioMap[$scenarioCode]['points'][] = (float) $row['revenue_forecast'];
        $scenarioMap[$scenarioCode]['revenue_total'] += (float) $row['revenue_forecast'];
        $scenarioMap[$scenarioCode]['expense_total'] += (float) $row['expense_forecast'];
    }

    $scenarios = [];
    foreach ($scenarioMap as $scenario) {
        $delta = $scenario['expense_total'] > 0
            ? (($scenario['revenue_total'] - $scenario['expense_total']) / $scenario['expense_total']) * 100
            : 0.0;
        $scenario['display_delta'] = formatPercent($delta);
        $scenario['description'] = sprintf(
            'План на %d мес.: выручка %s, расходы %s.',
            count($scenario['points']),
            formatCurrency($scenario['revenue_total']),
            formatCurrency($scenario['expense_total'])
        );
        $scenarios[] = $scenario;
    }

    $chartLabels = [];
    $actual = [];
    $labelsRu = monthLabelsRu();
    for ($i = 3; $i >= 0; $i--) {
        $date = $now->modify(sprintf('first day of -%d month', $i));
        $chartLabels[] = $labelsRu[(int) $date->format('n')];
    }
    for ($i = 1; $i <= 3; $i++) {
        $date = $now->modify(sprintf('first day of +%d month', $i));
        $chartLabels[] = $labelsRu[(int) $date->format('n')];
    }

    $historyStatement = $pdo->prepare(
        'SELECT DATE_FORMAT(txn_date, "%Y-%m-01") AS month_bucket,
                SUM(CASE WHEN direction = "inflow" THEN amount ELSE 0 END) AS revenue_month
         FROM transactions
         WHERE company_id = ? AND txn_date >= ?
         GROUP BY month_bucket
         ORDER BY month_bucket'
    );
    $historyStatement->execute([
        $companyId,
        $historyStart,
    ]);
    $historyRows = [];
    foreach ($historyStatement->fetchAll() as $row) {
        $historyRows[$row['month_bucket']] = (float) $row['revenue_month'];
    }

    for ($i = 3; $i >= 0; $i--) {
        $date = $now->modify(sprintf('first day of -%d month', $i))->format('Y-m-01');
        $actual[] = $historyRows[$date] ?? 0.0;
    }
    $actual = array_merge($actual, [null, null, null]);

    $chartScenarios = [];
    foreach ($scenarioRows as $row) {
        $chartScenarios[$row['scenario_code']] ??= array_fill(0, count($chartLabels), null);
        $forecastMonth = new DateTimeImmutable($row['forecast_month']);
        $offset = ((int) $forecastMonth->format('Y') - (int) $now->format('Y')) * 12 + ((int) $forecastMonth->format('n') - (int) $now->format('n'));
        $targetIndex = 3 + $offset;
        if ($targetIndex >= 0 && $targetIndex < count($chartLabels)) {
            $chartScenarios[$row['scenario_code']][$targetIndex] = (float) $row['revenue_forecast'];
        }
    }

    $reports = [
        [
            'title' => 'P&L за текущий месяц',
            'period' => ucfirst(fullMonthLabel($now)),
            'status' => $profitMonth >= 0 ? 'Готов' : 'Нужна реакция',
            'value' => sprintf('Маржа %s', formatCurrency($profitMonth)),
        ],
        [
            'title' => 'Cash Flow 30 дней',
            'period' => $now->modify('-29 days')->format('d.m') . ' — ' . $now->format('d.m'),
            'status' => ((float) $stats['cash_in_30'] - (float) $stats['cash_out_30']) >= 0 ? 'Готов' : 'В работе',
            'value' => sprintf('Чистый поток %s', formatCurrency((float) $stats['cash_in_30'] - (float) $stats['cash_out_30'])),
        ],
        [
            'title' => 'Контроль бюджета',
            'period' => ucfirst(fullMonthLabel($now)),
            'status' => count(array_filter($budgetArticles, static fn (array $item): bool => $item['status'] !== 'В лимите')) > 0 ? 'В работе' : 'Готов',
            'value' => sprintf('%d статей расходов', count($budgetArticles)),
        ],
    ];

    return [
        'product_overview' => [
            'title' => 'Ваш финансовый обзор',
            'description' => 'Ключевые показатели и прогноз по данным вашей компании.',
            'highlights' => [
                'Выручка, расходы и прибыль в одном окне',
                'Контроль бюджета по статьям',
                'Быстрое переключение сценариев',
            ],
        ],
        'stats' => [
            ['label' => 'Выручка за месяц', 'value' => formatCurrency($revenueMonth), 'state' => 'success'],
            ['label' => 'Расходы за месяц', 'value' => formatCurrency($expensesMonth), 'state' => 'danger'],
            ['label' => 'Чистая прибыль', 'value' => formatCurrency($profitMonth), 'state' => $profitMonth >= 0 ? 'success' : 'danger'],
            ['label' => 'Точность плана', 'value' => formatPercent($accuracy), 'state' => 'neutral'],
            ['label' => 'Runway / burn', 'value' => number_format($cashRunwayMonths, 1, ',', ' ') . 'x', 'state' => 'neutral'],
            ['label' => 'Клиентская база', 'value' => (string) count(array_filter($transactions, static fn (array $item): bool => $item['type'] === 'positive')) . ' оплат', 'state' => 'neutral'],
        ],
        'transactions' => $transactions,
        'budget_articles' => $budgetArticles,
        'scenarios' => $scenarios,
        'reports' => $reports,
        'settings' => [
            'accent' => '#27AE60',
            'compactMode' => false,
            'notifications' => true,
        ],
        'chart' => [
            'labels' => $chartLabels,
            'actual' => $actual,
            'scenarios' => $chartScenarios,
        ],
    ];
}

$flash = [];
$dbError = null;
$user = null;
$app = appConfig();

try {
    $pdo = databaseConnection();
    handleAuth($pdo);
    $user = currentUser($pdo);
    $flash = flash();
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
    $flash = flash();
}

$authView = $_GET['auth'] ?? 'login';
$authView = $authView === 'register' ? 'register' : 'login';
$dashboard = $user && !isset($pdo) ? null : ($user ? buildDashboard($pdo, $user) : null);
$scenarioNames = $dashboard ? array_column($dashboard['scenarios'], 'name', 'id') : [];
$defaultScenario = $dashboard['scenarios'][0]['id'] ?? 'base';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinHorizon — SaaS-платформа финансового планирования</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Roboto+Mono&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php if (!$user): ?>
    <div class="auth-layout">
        <section class="auth-hero">
            <div class="logo-container">
                <img class="brand-logo" src="img/logo.png" alt="Логотип FinHorizon">
                <div>
                    <div class="brand-name">FinHorizon</div>
                    <div class="brand-subtitle">Финансовый кабинет</div>
                </div>
            </div>
            <h1>Контролируйте финансы в одном месте.</h1>
            <p>Регистрируйтесь, указывайте данные компании и сразу получайте расчет ключевых показателей.</p>
            <ul class="hero-card__list auth-list">
                <li>Быстрый вход и регистрация.</li>
                <li>Динамический расчет метрик.</li>
                <li>Сценарии и бюджет в реальном времени.</li>
            </ul>
            <div class="sidebar-summary auth-summary">
                <div class="sidebar-summary__label">Быстрый старт</div>
                <strong>Demo login: owner@demo.fin / DemoPass123!</strong>
                <span>Войдите или зарегистрируйте компанию, чтобы начать работу.</span>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-tabs">
                <a class="auth-tab <?= $authView === 'login' ? 'is-active' : ''; ?>" href="<?= h(appPath(['auth' => 'login'])); ?>">Вход</a>
                <a class="auth-tab <?= $authView === 'register' ? 'is-active' : ''; ?>" href="<?= h(appPath(['auth' => 'register'])); ?>">Регистрация</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash flash--<?= h($flash['type'] ?? 'success'); ?>"><?= h($flash['message'] ?? ''); ?></div>
            <?php endif; ?>

            <?php if ($dbError): ?>
                <div class="flash flash--error">Не удалось подключиться к MySQL: <?= h($dbError); ?></div>
            <?php endif; ?>

            <?php if ($authView === 'register'): ?>
                <form class="auth-form" method="post" action="<?= h(appPath(['auth' => 'register'])); ?>">
                    <input type="hidden" name="action" value="register">
                    <label class="field">
                        <span>Ваше имя</span>
                        <input type="text" name="full_name" placeholder="Анна Смирнова" required>
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" placeholder="you@company.ru" required>
                    </label>
                    <label class="field">
                        <span>Компания</span>
                        <input type="text" name="company_name" placeholder="ООО Альфа" required>
                    </label>
                    <label class="field">
                        <span>Отрасль</span>
                        <input type="text" name="industry" placeholder="SaaS / Retail / Agency" required>
                    </label>
                    <label class="field">
                        <span>Пароль</span>
                        <input type="password" name="password" placeholder="Минимум 8 символов" required>
                    </label>
                    <label class="field">
                        <span>Подтверждение пароля</span>
                        <input type="password" name="password_confirm" required>
                    </label>
                    <button class="button button--full" type="submit">Создать аккаунт и компанию</button>
                </form>
            <?php else: ?>
                <form class="auth-form" method="post" action="<?= h(appPath(['auth' => 'login'])); ?>">
                    <input type="hidden" name="action" value="login">
                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="owner@demo.fin" required>
                    </label>
                    <label class="field">
                        <span>Пароль</span>
                        <input type="password" name="password" value="DemoPass123!" required>
                    </label>
                    <button class="button button--full" type="submit">Войти в платформу</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <img class="brand-logo" src="img/logo.png" alt="Логотип FinHorizon">
                    <div>
                        <div class="brand-name">FinHorizon</div>
                        <div class="brand-subtitle">Финансовая операционная система</div>
                    </div>
                </div>
                <button class="sidebar-toggle" type="button" id="sidebarToggleButton" aria-expanded="true" aria-controls="sidebar" aria-label="Свернуть меню">
                    ☰
                </button>
            </div>

            <div class="logo-container sidebar-compact-logo">
                <img class="brand-logo" src="img/logo.png" alt="Логотип FinHorizon">
            </div>

            <div class="sidebar-product-card">
                <p class="sidebar-product-card__eyebrow"><?= h($user['industry']); ?></p>
                <strong><?= h($user['company_name']); ?></strong>
                <span>Рабочее пространство вашей компании.</span>
            </div>

            <nav class="sidebar-nav" aria-label="Основная навигация">
                <ul>
                    <li><button class="nav-button is-active" type="button" data-section-target="dashboard" data-heading="Финансовый командный центр" data-slogan="Актуальные KPI, cash flow и сценарии по данным вашей компании."><span class="nav-button__icon"><svg viewBox="0 0 24 24"><use href="img/icons.svg#dashboard"></use></svg></span><span>Дашборд</span></button></li>
                    <li><button class="nav-button" type="button" data-section-target="articles" data-heading="Контроль бюджета" data-slogan="Статьи и лимиты считаются по операциям текущего месяца."><span class="nav-button__icon"><svg viewBox="0 0 24 24"><use href="img/icons.svg#articles"></use></svg></span><span>Бюджеты</span></button></li>
                    <li><button class="nav-button" type="button" data-section-target="scenarios" data-heading="Сценарное моделирование" data-slogan="Сравнение базового, ростового и стресс-сценариев."><span class="nav-button__icon"><svg viewBox="0 0 24 24"><use href="img/icons.svg#scenarios"></use></svg></span><span>Сценарии</span></button></li>
                    <li><button class="nav-button" type="button" data-section-target="reports" data-heading="Управленческие отчеты" data-slogan="P&L, cash flow и контроль бюджета рассчитываются динамически."><span class="nav-button__icon"><svg viewBox="0 0 24 24"><use href="img/icons.svg#reports"></use></svg></span><span>Отчеты</span></button></li>
                    <li><button class="nav-button" type="button" data-section-target="settings" data-heading="Настройки кабинета" data-slogan="Локальные интерфейсные настройки и подсветка рисков."><span class="nav-button__icon"><svg viewBox="0 0 24 24"><use href="img/icons.svg#settings"></use></svg></span><span>Настройки</span></button></li>
                </ul>
            </nav>

            <div class="sidebar-summary">
                <div class="sidebar-summary__label">Профиль</div>
                <strong><?= h($user['full_name']); ?></strong>
                <span><?= h($user['email']); ?></span>
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <button class="sidebar-toggle button button--secondary" type="button" id="sidebarToggleButton" aria-expanded="true" aria-controls="sidebar">Свернуть меню</button>
                    <h1 id="pageHeading">Финансовый командный центр</h1>
                    <p class="slogan" id="pageSlogan">Актуальные KPI, денежный поток и сценарии вашей компании.</p>
                </div>
                <div class="profile-panel">
                    <div class="profile-card">
                        <span class="profile-card__label">Аккаунт</span>
                        <strong><?= h($user['full_name']); ?></strong>
                        <small><?= h($user['email']); ?></small>
                    </div>
                    <form method="post" action="<?= h(appPath()); ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="button button--secondary" type="submit">Выйти</button>
                    </form>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="flash flash--<?= h($flash['type'] ?? 'success'); ?>"><?= h($flash['message'] ?? ''); ?></div>
            <?php endif; ?>

            <section class="hero-card">
                <div class="hero-card__content">
                    <p class="hero-card__eyebrow">Коротко</p>
                    <h2><?= h($dashboard['product_overview']['title']); ?></h2>
                    <p><?= h($dashboard['product_overview']['description']); ?></p>
                    <ul class="hero-card__list">
                        <?php foreach ($dashboard['product_overview']['highlights'] as $highlight): ?>
                            <li><?= h($highlight); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="hero-card__media">
                    <img src="img/logo.png" alt="Логотип FinHorizon">
                </div>
            </section>

            <section class="content-section is-active" data-section="dashboard">
                <section class="content-block quick-calc">
                    <div class="block-header">
                        <div>
                            <h2>Операции</h2>
                            <p>Добавляйте и редактируйте доходы и расходы.</p>
                        </div>
                    </div>
                    <form class="quick-calc__form" id="operationForm">
                        <input type="hidden" name="editIndex" value="">
                        <label class="field">
                            <span>Название</span>
                            <input type="text" name="description" placeholder="Оплата клиента" required>
                        </label>
                        <label class="field">
                            <span>Категория</span>
                            <input type="text" name="category" placeholder="Подписки" required>
                        </label>
                        <label class="field">
                            <span>Дата</span>
                            <input type="date" name="date" required>
                        </label>
                        <label class="field">
                            <span>Тип</span>
                            <select name="direction" required>
                                <option value="inflow">Доход</option>
                                <option value="outflow">Расход</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Сумма, ₽</span>
                            <input type="number" name="amount" min="1" step="1" placeholder="50000" required>
                        </label>
                        <button class="button" type="submit" id="operationSubmitButton">Добавить операцию</button>
                    </form>
                    <div class="quick-calc__result" id="operationFormResult">Заполните форму, чтобы добавить операцию.</div>
                </section>

                <div class="dashboard-grid dashboard-grid--six">
                    <?php foreach ($dashboard['stats'] as $stat): ?>
                        <article class="card <?= $stat['state'] === 'neutral' ? '' : 'card--' . $stat['state']; ?>">
                            <div class="card-label"><?= h($stat['label']); ?></div>
                            <div class="card-value"><?= h($stat['value']); ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="content-row">
                    <section class="content-block content-block--chart">
                        <div class="block-header">
                            <div>
                                <h2>Прогноз выручки</h2>
                                <p>История и сценарии по периодам.</p>
                            </div>
                            <div class="scenario-switcher">
                                <?php foreach ($dashboard['scenarios'] as $index => $scenario): ?>
                                    <button class="chip <?= $index === 0 ? 'is-active' : ''; ?>" type="button" data-scenario="<?= h($scenario['id']); ?>"><?= h($scenario['name']); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="chart-surface">
                            <canvas id="forecastChart"></canvas>
                        </div>
                        <div class="forecast-summary" id="forecastSummary">
                            <article class="forecast-summary__item">
                                <span>Ближайший прогноз</span>
                                <strong id="forecastNearest">—</strong>
                            </article>
                            <article class="forecast-summary__item">
                                <span>Средний прогноз</span>
                                <strong id="forecastAverage">—</strong>
                            </article>
                            <article class="forecast-summary__item">
                                <span>Пик прогноза</span>
                                <strong id="forecastPeak">—</strong>
                            </article>
                        </div>
                    </section>

                    <section class="content-block content-block--operations">
                        <h2>Последние операции</h2>
                        <table id="operationsTable">
                            <thead>
                            <tr>
                                <th>Операция</th>
                                <th>Категория</th>
                                <th>Дата</th>
                                <th class="align-right">Сумма</th>
                                <th class="align-right">Действие</th>
                            </tr>
                            </thead>
                            <tbody id="operationsTableBody">
                            <?php foreach ($dashboard['transactions'] as $transaction): ?>
                                <tr data-direction="<?= $transaction['type'] === 'positive' ? 'inflow' : 'outflow'; ?>">
                                    <td><?= h($transaction['description']); ?></td>
                                    <td><?= h($transaction['category']); ?></td>
                                    <td><?= h($transaction['date']); ?></td>
                                    <td class="amount <?= h($transaction['type']); ?>"><?= h($transaction['display_amount']); ?></td>
                                    <td class="align-right"><button class="button button--secondary button--small" type="button" data-operation-edit>Редактировать</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            </section>

            <section class="content-section" data-section="articles">
                <div class="content-block">
                    <div class="block-header">
                        <div>
                            <h2>Бюджетные статьи</h2>
                            <p>Лимиты и использование по текущему месяцу.</p>
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
                        <?php foreach ($dashboard['budget_articles'] as $article): ?>
                            <tr data-status="<?= h($article['status']); ?>">
                                <td><?= h($article['name']); ?></td>
                                <td><?= h($article['display_limit']); ?></td>
                                <td><?= h($article['display_spent']); ?></td>
                                <td><span class="status-pill <?= h($article['status_class']); ?>"><?= h($article['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="content-section" data-section="scenarios">
                <div class="cards-row">
                    <?php foreach ($dashboard['scenarios'] as $index => $scenario): ?>
                        <article class="scenario-card <?= $index === 0 ? 'is-selected' : ''; ?>" data-scenario-card="<?= h($scenario['id']); ?>">
                            <div class="scenario-card__header">
                                <h2><?= h($scenario['name']); ?></h2>
                                <span class="scenario-card__delta"><?= h($scenario['display_delta']); ?></span>
                            </div>
                            <p><?= h($scenario['description']); ?></p>
                            <button class="button" type="button" data-scenario-apply="<?= h($scenario['id']); ?>">Показать на графике</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="content-section" data-section="reports">
                <div class="content-block">
                    <h2>Управленческие отчеты</h2>
                    <div class="report-list">
                        <?php foreach ($dashboard['reports'] as $report): ?>
                            <article class="report-card">
                                <div>
                                    <h3><?= h($report['title']); ?></h3>
                                    <p><?= h($report['period']); ?></p>
                                </div>
                                <div class="report-card__meta">
                                    <strong><?= h($report['value']); ?></strong>
                                    <span class="status-pill <?= h(statusClass($report['status'])); ?>"><?= h($report['status']); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="content-section" data-section="settings">
                <div class="content-block">
                    <h2>Настройки интерфейса</h2>
                    <form class="settings-form" id="settingsForm">
                        <label class="field">
                            <span>Акцентный цвет</span>
                            <input type="color" name="accent" value="#27AE60">
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="compactMode">
                            <span>Компактный режим карточек</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="notifications" checked>
                            <span>Уведомления о рисках</span>
                        </label>
                        <div class="settings-actions">
                            <button class="button" type="submit">Сохранить локальные настройки</button>
                            <button class="button button--secondary" type="button" id="resetSettingsButton">Сбросить</button>
                        </div>
                        <p class="settings-hint" id="settingsHint">Настройки интерфейса сохраняются в браузере, финансовые данные — в MySQL.</p>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script>
        window.dashboardConfig = <?= json_encode([
            'defaultScenario' => $defaultScenario,
            'settings' => $dashboard['settings'],
            'chart' => $dashboard['chart'],
            'scenarioNames' => $scenarioNames,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
    </script>
    <script src="js/app.js"></script>
<?php endif; ?>
</body>
</html>
