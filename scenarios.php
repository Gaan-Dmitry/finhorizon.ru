<?php
/**
 * ФинГоризонт - Страница управления сценариями
 */

require_once 'includes/config.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сценарии - <?= APP_NAME ?></title>
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
                <img src="/logo.svg" alt="Логотип ФинГоризонт">
            </div>
            <div class="logo-text">
                <h1>ФинГоризонт</h1>
                <p><?= APP_SLOGAN ?></p>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li><a href="/index.php">Dashboard</a></li>
            <li><a href="/scenarios.php" class="active">Сценарии</a></li>
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
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Управление сценариями</h2>
                    <button class="btn btn-primary" onclick="openModal('createScenarioModal')">+ Новый сценарий</button>
                </div>
                
                <div id="scenariosList">
                    <div class="text-center" style="padding: 40px;">Загрузка...</div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Модальное окно создания/редактирования сценария -->
    <div class="modal-overlay" id="createScenarioModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Новый сценарий</h3>
                <button class="modal-close" onclick="closeModal('createScenarioModal')">&times;</button>
            </div>
            
            <form id="scenarioForm" onsubmit="handleScenarioSubmit(event)">
                <input type="hidden" id="scenarioId">
                
                <div class="form-group">
                    <label class="form-label">Название сценария *</label>
                    <input type="text" id="scenarioName" class="form-control" required placeholder="Например: Бюджет 2024">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea id="scenarioDescription" class="form-control" rows="3" placeholder="Краткое описание сценария"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Дата начала *</label>
                        <input type="date" id="scenarioStartDate" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Дата окончания *</label>
                        <input type="date" id="scenarioEndDate" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="scenarioActive" checked> Активный сценарий
                    </label>
                </div>
                
                <div id="scenarioError" class="alert alert-error" style="display: none;"></div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createScenarioModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function logout() {
            fetch('/api/auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.href = '/login.php');
        }
        
        // Загрузка сценариев
        loadScenarios();
        
        async function loadScenarios() {
            try {
                const response = await fetch('/api/scenarios.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    renderScenarios(data.data);
                } else {
                    document.getElementById('scenariosList').innerHTML = `
                        <div class="alert alert-error">${data.error}</div>
                    `;
                }
            } catch (err) {
                document.getElementById('scenariosList').innerHTML = `
                    <div class="alert alert-error">Ошибка загрузки данных</div>
                `;
            }
        }
        
        function renderScenarios(scenarios) {
            const container = document.getElementById('scenariosList');
            
            if (scenarios.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        У вас пока нет сценариев. Создайте первый сценарий для начала работы.
                    </div>
                `;
                return;
            }
            
            let html = '<div class="table-container"><table class="data-table">';
            html += `
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Период</th>
                        <th>Статей</th>
                        <th>Доход</th>
                        <th>Расход</th>
                        <th>Баланс</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            scenarios.forEach(s => {
                const balance = (parseFloat(s.total_income) || 0) - (parseFloat(s.total_expense) || 0);
                const statusClass = s.is_active ? 'positive' : '';
                const statusText = s.is_active ? '✓ Активен' : '○ Архив';
                
                html += `
                    <tr>
                        <td><strong>${escapeHtml(s.name)}</strong></td>
                        <td>${formatDate(s.start_date)} - ${formatDate(s.end_date)}</td>
                        <td class="monospace">${s.items_count || 0}</td>
                        <td class="amount positive monospace">${formatMoney(s.total_income)}</td>
                        <td class="amount negative monospace">${formatMoney(s.total_expense)}</td>
                        <td class="amount monospace ${balance >= 0 ? 'positive' : 'negative'}">${formatMoney(balance)}</td>
                        <td><span class="${statusClass}">${statusText}</span></td>
                        <td>
                            <a href="/budget.php?scenario_id=${s.id}" class="btn btn-outline" style="padding: 5px 10px;">Бюджет</a>
                            <button class="btn btn-outline" style="padding: 5px 10px;" onclick="editScenario(${s.id})">✏️</button>
                            <button class="btn btn-danger" style="padding: 5px 10px;" onclick="deleteScenario(${s.id})">🗑️</button>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            if (modalId === 'createScenarioModal') {
                resetForm();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function resetForm() {
            document.getElementById('scenarioId').value = '';
            document.getElementById('scenarioName').value = '';
            document.getElementById('scenarioDescription').value = '';
            document.getElementById('scenarioStartDate').value = '';
            document.getElementById('scenarioEndDate').value = '';
            document.getElementById('scenarioActive').checked = true;
            document.getElementById('modalTitle').textContent = 'Новый сценарий';
            document.getElementById('scenarioError').style.display = 'none';
        }
        
        async function handleScenarioSubmit(event) {
            event.preventDefault();
            
            const id = document.getElementById('scenarioId').value;
            const data = {
                name: document.getElementById('scenarioName').value,
                description: document.getElementById('scenarioDescription').value,
                start_date: document.getElementById('scenarioStartDate').value,
                end_date: document.getElementById('scenarioEndDate').value,
                is_active: document.getElementById('scenarioActive').checked ? 1 : 0
            };
            
            if (id) {
                data.id = id;
            }
            
            const errorDiv = document.getElementById('scenarioError');
            
            try {
                const response = await fetch('/api/scenarios.php?action=' + (id ? 'update' : 'create'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal('createScenarioModal');
                    loadScenarios();
                } else {
                    errorDiv.textContent = result.error;
                    errorDiv.style.display = 'flex';
                }
            } catch (err) {
                errorDiv.textContent = 'Ошибка соединения с сервером';
                errorDiv.style.display = 'flex';
            }
        }
        
        async function editScenario(id) {
            try {
                const response = await fetch('/api/scenarios.php?action=get&id=' + id);
                const data = await response.json();
                
                if (data.success) {
                    const s = data.data;
                    document.getElementById('scenarioId').value = s.id;
                    document.getElementById('scenarioName').value = s.name;
                    document.getElementById('scenarioDescription').value = s.description || '';
                    document.getElementById('scenarioStartDate').value = s.start_date;
                    document.getElementById('scenarioEndDate').value = s.end_date;
                    document.getElementById('scenarioActive').checked = s.is_active == 1;
                    document.getElementById('modalTitle').textContent = 'Редактирование сценария';
                    document.getElementById('scenarioError').style.display = 'none';
                    
                    openModal('createScenarioModal');
                }
            } catch (err) {
                alert('Ошибка загрузки данных сценария');
            }
        }
        
        async function deleteScenario(id) {
            if (!confirm('Вы уверены? Все статьи бюджета этого сценария будут удалены.')) {
                return;
            }
            
            try {
                const response = await fetch('/api/scenarios.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadScenarios();
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('Ошибка удаления сценария');
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('ru-RU');
        }
        
        function formatMoney(amount) {
            if (!amount) return '0 ₽';
            return Number(amount).toLocaleString('ru-RU') + ' ₽';
        }
    </script>
</body>
</html>
