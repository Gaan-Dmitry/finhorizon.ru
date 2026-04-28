<?php
/**
 * ФинГоризонт - Страница управления бюджетом
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
    <title>Бюджет - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
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
            <li><a href="/budget.php" class="active">Бюджет</a></li>
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
            <?php if (!$scenarioId): ?>
                <div class="card">
                    <div class="alert alert-warning">
                        У вас нет активных сценариев. Создайте сценарий в разделе «Сценарии» для начала работы с бюджетом.
                    </div>
                    <a href="/scenarios.php" class="btn btn-primary">Перейти к сценариям</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Управление бюджетом: <?= htmlspecialchars($scenario['name']) ?></h2>
                        <select id="scenarioSelect" class="form-control" style="width: 300px;" onchange="location.href='/budget.php?scenario_id='+this.value">
                            <?php foreach ($allScenarios as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $scenarioId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <button class="btn btn-accent" onclick="openModal('itemModal')">+ Добавить статью</button>
                    </div>
                    
                    <!-- Фильтры -->
                    <div class="form-row" style="margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Тип</label>
                            <select id="filterType" class="form-control" onchange="loadBudgetItems()">
                                <option value="">Все</option>
                                <option value="income">Доходы</option>
                                <option value="expense">Расходы</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Категория</label>
                            <select id="filterCategory" class="form-control" onchange="loadBudgetItems()">
                                <option value="">Все</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Таблица статей -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Название</th>
                                    <th>Категория</th>
                                    <th>Тип</th>
                                    <th>Сумма</th>
                                    <th>Повторяется</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="budgetItemsTable">
                                <tr><td colspan="7" class="text-center">Загрузка...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Итоги -->
                    <div class="stats-grid mt-20" id="summaryStats">
                        <div class="stat-card income">
                            <div class="stat-label">Итого доходов</div>
                            <div class="stat-value positive monospace" id="totalIncome">0 ₽</div>
                        </div>
                        <div class="stat-card expense">
                            <div class="stat-label">Итого расходов</div>
                            <div class="stat-value negative monospace" id="totalExpense">0 ₽</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Баланс</div>
                            <div class="stat-value monospace" id="totalBalance">0 ₽</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Модальное окно статьи бюджета -->
    <div class="modal-overlay" id="itemModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="itemModalTitle">Новая статья бюджета</h3>
                <button class="modal-close" onclick="closeModal('itemModal')">&times;</button>
            </div>
            
            <form id="itemForm" onsubmit="handleItemSubmit(event)">
                <input type="hidden" id="itemId">
                
                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" id="itemName" class="form-control" required placeholder="Например: Продажа товаров">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Тип *</label>
                        <select id="itemType" class="form-control" required onchange="updateCategories()">
                            <option value="income">Доход</option>
                            <option value="expense">Расход</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Категория</label>
                        <input type="text" id="itemCategory" class="form-control" list="categoryList" placeholder="Выберите или введите">
                        <datalist id="categoryList"></datalist>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Сумма *</label>
                        <input type="number" id="itemAmount" class="form-control" required min="0.01" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Дата *</label>
                        <input type="date" id="itemDate" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="itemRecurring"> Повторяющаяся статья
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Периодичность</label>
                    <select id="itemRecurrence" class="form-control">
                        <option value="monthly">Ежемесячно</option>
                        <option value="weekly">Еженедельно</option>
                        <option value="yearly">Ежегодно</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Комментарий</label>
                    <textarea id="itemNotes" class="form-control" rows="2"></textarea>
                </div>
                
                <div id="itemError" class="alert alert-error" style="display: none;"></div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('itemModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const currentScenarioId = <?= $scenarioId ? $scenarioId : 'null' ?>;
        let allItems = [];
        let categories = {
            income: ['Продажи', 'Услуги', 'Инвестиции', 'Проценты', 'Другое'],
            expense: ['Зарплата', 'Аренда', 'Маркетинг', 'Налоги', 'Офис', 'Оборудование', 'Другое']
        };
        
        function logout() {
            fetch('/api/auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.href = '/login.php');
        }
        
        // Установка даты по умолчанию
        document.getElementById('itemDate').valueAsDate = new Date();
        
        // Загрузка статей при старте
        if (currentScenarioId) {
            loadBudgetItems();
            updateCategories();
        }
        
        async function loadBudgetItems() {
            try {
                const response = await fetch('/api/budget.php?action=list&scenario_id=' + currentScenarioId);
                const data = await response.json();
                
                if (data.success) {
                    allItems = data.data;
                    renderBudgetItems(allItems);
                    updateCategoryFilter();
                    updateSummary(allItems);
                }
            } catch (err) {
                document.getElementById('budgetItemsTable').innerHTML = `
                    <tr><td colspan="7" class="text-center">Ошибка загрузки</td></tr>
                `;
            }
        }
        
        function renderBudgetItems(items) {
            const filterType = document.getElementById('filterType').value;
            const filterCategory = document.getElementById('filterCategory').value;
            
            let filtered = items;
            if (filterType) filtered = filtered.filter(i => i.type === filterType);
            if (filterCategory) filtered = filtered.filter(i => i.category === filterCategory);
            
            // Сортировка по дате
            filtered.sort((a, b) => new Date(b.date) - new Date(a.date));
            
            const tbody = document.getElementById('budgetItemsTable');
            
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">Нет данных</td></tr>';
                return;
            }
            
            let html = '';
            filtered.forEach(item => {
                const typeLabel = item.type === 'income' ? 'Доход' : 'Расход';
                const typeClass = item.type === 'income' ? 'positive' : 'negative';
                const recurringIcon = item.is_recurring ? '🔄 ' : '';
                const recurrenceText = item.is_recurring ? 
                    ({monthly: 'Ежемесячно', weekly: 'Еженедельно', yearly: 'Ежегодно'}[item.recurrence_pattern] || '') : '—';
                
                html += `
                    <tr>
                        <td>${formatDate(item.date)}</td>
                        <td>${recurringIcon}${escapeHtml(item.name)}</td>
                        <td>${item.category || '—'}</td>
                        <td><span class="${typeClass}">${typeLabel}</span></td>
                        <td class="amount ${typeClass} monospace">${formatMoney(item.amount)}</td>
                        <td>${recurrenceText}</td>
                        <td>
                            <button class="btn btn-outline" style="padding: 5px 10px;" onclick="editItem(${item.id})">✏️</button>
                            <button class="btn btn-danger" style="padding: 5px 10px;" onclick="deleteItem(${item.id})">🗑️</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        function updateSummary(items) {
            const totalIncome = items.filter(i => i.type === 'income').reduce((sum, i) => sum + parseFloat(i.amount), 0);
            const totalExpense = items.filter(i => i.type === 'expense').reduce((sum, i) => sum + parseFloat(i.amount), 0);
            const balance = totalIncome - totalExpense;
            
            document.getElementById('totalIncome').textContent = formatMoney(totalIncome);
            document.getElementById('totalExpense').textContent = formatMoney(totalExpense);
            
            const balanceEl = document.getElementById('totalBalance');
            balanceEl.textContent = formatMoney(balance);
            balanceEl.className = 'stat-value monospace ' + (balance >= 0 ? 'positive' : 'negative');
        }
        
        function updateCategories() {
            const type = document.getElementById('itemType').value;
            const datalist = document.getElementById('categoryList');
            datalist.innerHTML = categories[type].map(c => `<option value="${c}">`).join('');
        }
        
        function updateCategoryFilter() {
            const select = document.getElementById('filterCategory');
            const allCategories = [...new Set(allItems.map(i => i.category).filter(Boolean))];
            select.innerHTML = '<option value="">Все</option>' + 
                allCategories.map(c => `<option value="${c}">${c}</option>`).join('');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            if (modalId === 'itemModal') {
                resetItemForm();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function resetItemForm() {
            document.getElementById('itemId').value = '';
            document.getElementById('itemName').value = '';
            document.getElementById('itemType').value = 'income';
            document.getElementById('itemCategory').value = '';
            document.getElementById('itemAmount').value = '';
            document.getElementById('itemDate').valueAsDate = new Date();
            document.getElementById('itemRecurring').checked = false;
            document.getElementById('itemNotes').value = '';
            document.getElementById('itemModalTitle').textContent = 'Новая статья бюджета';
            document.getElementById('itemError').style.display = 'none';
            updateCategories();
        }
        
        async function handleItemSubmit(event) {
            event.preventDefault();
            
            const id = document.getElementById('itemId').value;
            const data = {
                scenario_id: currentScenarioId,
                name: document.getElementById('itemName').value,
                type: document.getElementById('itemType').value,
                category: document.getElementById('itemCategory').value,
                amount: parseFloat(document.getElementById('itemAmount').value),
                date: document.getElementById('itemDate').value,
                is_recurring: document.getElementById('itemRecurring').checked ? 1 : 0,
                recurrence_pattern: document.getElementById('itemRecurrence').value,
                notes: document.getElementById('itemNotes').value
            };
            
            if (id) data.id = id;
            
            const errorDiv = document.getElementById('itemError');
            
            try {
                const response = await fetch('/api/budget.php?action=' + (id ? 'update' : 'create'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal('itemModal');
                    loadBudgetItems();
                } else {
                    errorDiv.textContent = result.error;
                    errorDiv.style.display = 'flex';
                }
            } catch (err) {
                errorDiv.textContent = 'Ошибка соединения с сервером';
                errorDiv.style.display = 'flex';
            }
        }
        
        async function editItem(id) {
            try {
                const response = await fetch('/api/budget.php?action=get&id=' + id);
                const data = await response.json();
                
                if (data.success) {
                    const item = data.data;
                    document.getElementById('itemId').value = item.id;
                    document.getElementById('itemName').value = item.name;
                    document.getElementById('itemType').value = item.type;
                    document.getElementById('itemCategory').value = item.category || '';
                    document.getElementById('itemAmount').value = item.amount;
                    document.getElementById('itemDate').value = item.date;
                    document.getElementById('itemRecurring').checked = item.is_recurring == 1;
                    document.getElementById('itemRecurrence').value = item.recurrence_pattern || 'monthly';
                    document.getElementById('itemNotes').value = item.notes || '';
                    document.getElementById('itemModalTitle').textContent = 'Редактирование статьи';
                    document.getElementById('itemError').style.display = 'none';
                    
                    updateCategories();
                    openModal('itemModal');
                }
            } catch (err) {
                alert('Ошибка загрузки данных');
            }
        }
        
        async function deleteItem(id) {
            if (!confirm('Удалить эту статью бюджета?')) return;
            
            try {
                const response = await fetch('/api/budget.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadBudgetItems();
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('Ошибка удаления');
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('ru-RU');
        }
        
        function formatMoney(amount) {
            return Number(amount).toLocaleString('ru-RU') + ' ₽';
        }
    </script>
</body>
</html>
