/**
 * ФинГоризонт - JavaScript для главной страницы (Dashboard)
 */

// Глобальные переменные
let mainChartInstance = null;
let scenariosData = [];

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
    loadScenarios();
    initCreateScenarioForm();
});

// Загрузка сценариев
async function loadScenarios() {
    try {
        // В демо-режиме используем тестовые данные
        // В продакшене: const response = await FinHorizon.api.get('scenarios.php?action=list');
        
        // Демо-данные для отображения
        scenariosData = [
            { id: 1, name: 'Бюджет 2024', start_date: '2024-01-01', end_date: '2024-12-31', is_active: 1 },
            { id: 2, name: 'Проект "Развитие"', start_date: '2024-03-01', end_date: '2024-09-30', is_active: 1 }
        ];
        
        renderScenarios(scenariosData);
        updateStatistics(scenariosData);
        renderChart();
        
    } catch (error) {
        FinHorizon.notifications.show('Ошибка загрузки сценариев: ' + error.message, 'error');
        document.getElementById('scenariosBody').innerHTML = 
            '<tr><td colspan="4" class="text-center text-danger">Ошибка загрузки данных</td></tr>';
    }
}

// Отрисовка таблицы сценариев
function renderScenarios(scenarios) {
    const tbody = document.getElementById('scenariosBody');
    
    if (!scenarios || scenarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Нет сценариев</td></tr>';
        return;
    }
    
    tbody.innerHTML = scenarios.map(scenario => {
        const startDate = new Date(scenario.start_date).toLocaleDateString('ru-RU');
        const endDate = new Date(scenario.end_date).toLocaleDateString('ru-RU');
        const statusClass = scenario.is_active ? 'text-success' : 'text-danger';
        const statusText = scenario.is_active ? 'Активен' : 'Завершен';
        
        return `
            <tr>
                <td><strong>${escapeHtml(scenario.name)}</strong></td>
                <td>${startDate} - ${endDate}</td>
                <td class="${statusClass}">${statusText}</td>
                <td>
                    <a href="scenario_detail.php?id=${scenario.id}" class="btn btn-primary btn-sm">Открыть</a>
                    <button class="btn btn-outline btn-sm" onclick="editScenario(${scenario.id})">Ред.</button>
                </td>
            </tr>
        `;
    }).join('');
    
    // Обновляем счетчик
    document.getElementById('activeScenarios').textContent = scenarios.filter(s => s.is_active).length;
}

// Обновление статистики
function updateStatistics(scenarios) {
    // Демо-данные
    const totalIncome = 1250000;
    const totalExpense = 890000;
    
    document.getElementById('totalIncome').textContent = FinHorizon.utils.formatMoney(totalIncome);
    document.getElementById('totalExpense').textContent = FinHorizon.utils.formatMoney(totalExpense);
}

// Отрисовка графика
function renderChart() {
    const ctx = document.getElementById('mainChart');
    
    if (!ctx) return;
    
    // Демо-данные для графика
    const labels = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
    const incomeData = [100000, 120000, 95000, 130000, 110000, 140000, 125000, 135000, 115000, 145000, 130000, 150000];
    const expenseData = [80000, 95000, 70000, 100000, 85000, 110000, 95000, 105000, 90000, 115000, 100000, 120000];
    
    const data = {
        labels: labels,
        datasets: [
            {
                label: 'Доходы',
                data: incomeData,
                borderColor: '#27AE60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Расходы',
                data: expenseData,
                borderColor: '#E74C3C',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    };
    
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + FinHorizon.utils.formatMoney(context.raw);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k ₽';
                    }
                }
            }
        }
    };
    
    if (mainChartInstance) {
        mainChartInstance.destroy();
    }
    
    mainChartInstance = new Chart(ctx, {
        type: 'line',
        data: data,
        options: options
    });
}

// Открытие модального окна создания сценария
function openCreateScenarioModal() {
    FinHorizon.modal.open('createScenarioModal');
}

// Инициализация формы создания сценария
function initCreateScenarioForm() {
    const form = document.getElementById('createScenarioForm');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('scenarioName').value,
            description: document.getElementById('scenarioDescription').value,
            start_date: document.getElementById('scenarioStartDate').value,
            end_date: document.getElementById('scenarioEndDate').value
        };
        
        try {
            // В демо-режиме просто показываем уведомление
            // В продакшене: await FinHorizon.api.post('scenarios.php?action=create', formData);
            
            FinHorizon.notifications.show('Сценарий "' + formData.name + '" создан!', 'success');
            FinHorizon.modal.close('createScenarioModal');
            form.reset();
            
            // Перезагружаем список
            loadScenarios();
            
        } catch (error) {
            FinHorizon.notifications.show('Ошибка создания сценария: ' + error.message, 'error');
        }
    });
}

// Редактирование сценария
function editScenario(id) {
    FinHorizon.notifications.show('Функция редактирования в разработке', 'info');
}

// Утилита для экранирования HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
