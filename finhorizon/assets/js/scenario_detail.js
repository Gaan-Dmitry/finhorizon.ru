/**
 * ФинГоризонт - JavaScript для страницы деталей сценария
 */

let scenarioId = null;
let budgetData = [];
let forecastChartInstance = null;
let categories = [];

// Получение ID сценария из URL
function getScenarioIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    scenarioId = getScenarioIdFromUrl();
    
    if (!scenarioId) {
        FinHorizon.notifications.show('Не указан ID сценария', 'error');
        setTimeout(() => window.location.href = 'index.php', 2000);
        return;
    }
    
    document.getElementById('itemScenarioId').value = scenarioId;
    
    loadScenarioData();
    loadCategories();
    initAddItemForm();
});

// Загрузка данных сценария
async function loadScenarioData() {
    try {
        // Демо-данные
        const scenario = {
            id: scenarioId,
            name: 'Бюджет 2024',
            description: 'Основной финансовый план на 2024 год',
            start_date: '2024-01-01',
            end_date: '2024-12-31'
        };
        
        document.getElementById('scenarioTitle').textContent = scenario.name;
        document.getElementById('scenarioDescription').textContent = scenario.description || 'Нет описания';
        
        const startDate = new Date(scenario.start_date).toLocaleDateString('ru-RU');
        const endDate = new Date(scenario.end_date).toLocaleDateString('ru-RU');
        document.getElementById('scenarioPeriod').textContent = `${startDate} - ${endDate}`;
        
        // Загрузка данных бюджета
        await loadBudgetItems();
        
    } catch (error) {
        FinHorizon.notifications.show('Ошибка загрузки данных: ' + error.message, 'error');
    }
}

// Загрузка категорий
async function loadCategories() {
    try {
        // Демо-категории
        categories = [
            { id: 1, name: 'Продажи', type: 'income' },
            { id: 2, name: 'Услуги', type: 'income' },
            { id: 3, name: 'Зарплата', type: 'expense' },
            { id: 4, name: 'Аренда', type: 'expense' },
            { id: 5, name: 'Маркетинг', type: 'expense' }
        ];
        
        const select = document.getElementById('itemCategory');
        select.innerHTML = '<option value="">Выберите категорию</option>' +
            categories.map(cat => 
                `<option value="${cat.id}" data-type="${cat.type}">${cat.name} (${cat.type === 'income' ? 'Доход' : 'Расход'})</option>`
            ).join('');
        
    } catch (error) {
        FinHorizon.notifications.show('Ошибка загрузки категорий: ' + error.message, 'error');
    }
}

// Загрузка статей бюджета
async function loadBudgetItems() {
    try {
        // Демо-данные бюджета
        const items = [
            { category_name: 'Продажи', type: 'income', period: '2024-01-01', amount: 150000 },
            { category_name: 'Продажи', type: 'income', period: '2024-02-01', amount: 165000 },
            { category_name: 'Продажи', type: 'income', period: '2024-03-01', amount: 170000 },
            { category_name: 'Продажи', type: 'income', period: '2024-04-01', amount: 180000 },
            { category_name: 'Услуги', type: 'income', period: '2024-01-01', amount: 50000 },
            { category_name: 'Услуги', type: 'income', period: '2024-02-01', amount: 55000 },
            { category_name: 'Услуги', type: 'income', period: '2024-03-01', amount: 60000 },
            { category_name: 'Услуги', type: 'income', period: '2024-04-01', amount: 62000 },
            { category_name: 'Зарплата', type: 'expense', period: '2024-01-01', amount: 80000 },
            { category_name: 'Зарплата', type: 'expense', period: '2024-02-01', amount: 80000 },
            { category_name: 'Зарплата', type: 'expense', period: '2024-03-01', amount: 85000 },
            { category_name: 'Зарплата', type: 'expense', period: '2024-04-01', amount: 85000 },
            { category_name: 'Аренда', type: 'expense', period: '2024-01-01', amount: 30000 },
            { category_name: 'Аренда', type: 'expense', period: '2024-02-01', amount: 30000 },
            { category_name: 'Аренда', type: 'expense', period: '2024-03-01', amount: 30000 },
            { category_name: 'Аренда', type: 'expense', period: '2024-04-01', amount: 30000 },
            { category_name: 'Маркетинг', type: 'expense', period: '2024-01-01', amount: 20000 },
            { category_name: 'Маркетинг', type: 'expense', period: '2024-02-01', amount: 25000 },
            { category_name: 'Маркетинг', type: 'expense', period: '2024-03-01', amount: 30000 },
            { category_name: 'Маркетинг', type: 'expense', period: '2024-04-01', amount: 28000 }
        ];
        
        budgetData = items;
        renderBudgetTable(items);
        updateStatistics(items);
        renderForecastChart(items);
        
    } catch (error) {
        FinHorizon.notifications.show('Ошибка загрузки бюджета: ' + error.message, 'error');
        document.getElementById('budgetBody').innerHTML = 
            '<tr><td colspan="3" class="text-center text-danger">Ошибка загрузки данных</td></tr>';
    }
}

// Отрисовка таблицы бюджета
function renderBudgetTable(items) {
    // Группировка по категориям
    const grouped = {};
    const periods = new Set();
    
    items.forEach(item => {
        const key = `${item.category_name}-${item.type}`;
        if (!grouped[key]) {
            grouped[key] = {
                category_name: item.category_name,
                type: item.type,
                amounts: {}
            };
        }
        grouped[key].amounts[item.period] = item.amount;
        periods.add(item.period);
    });
    
    const sortedPeriods = Array.from(periods).sort();
    
    // Обновляем заголовок таблицы
    const periodsHeader = sortedPeriods.map(p => {
        const date = new Date(p);
        return `<th class="number-cell">${date.toLocaleDateString('ru-RU', { month: 'short', year: '2-digit' })}</th>`;
    }).join('');
    
    document.getElementById('periodsHeader').innerHTML = `Категория<th style="border-left: 2px solid #fff;"></th><th>Тип</th>${periodsHeader}`;
    
    // Отрисовка строк
    const tbody = document.getElementById('budgetBody');
    tbody.innerHTML = Object.values(grouped).map(row => {
        const typeClass = row.type === 'income' ? 'positive' : 'negative';
        const typeLabel = row.type === 'income' ? 'Доход' : 'Расход';
        
        const cells = sortedPeriods.map(p => {
            const amount = row.amounts[p] || 0;
            return `<td class="amount number-cell">${amount > 0 ? FinHorizon.utils.formatMoney(amount) : '-'}</td>`;
        }).join('');
        
        return `
            <tr>
                <td><strong>${row.category_name}</strong></td>
                <td style="border-left: 2px solid #BDC3C7;"></td>
                <td class="${typeClass}">${typeLabel}</td>
                ${cells}
            </tr>
        `;
    }).join('');
}

// Обновление статистики
function updateStatistics(items) {
    const actualIncome = items
        .filter(i => i.type === 'income')
        .reduce((sum, i) => sum + i.amount, 0);
    
    const actualExpense = items
        .filter(i => i.type === 'expense')
        .reduce((sum, i) => sum + i.amount, 0);
    
    document.getElementById('actualIncome').textContent = FinHorizon.utils.formatMoney(actualIncome);
    document.getElementById('actualExpense').textContent = FinHorizon.utils.formatMoney(actualExpense);
    
    // Прогноз доходов (демо)
    const forecastIncome = actualIncome * 1.15; // +15% прогноз
    document.getElementById('forecastIncome').textContent = FinHorizon.utils.formatMoney(forecastIncome);
}

// Отрисовка графика прогноза
function renderForecastChart(items) {
    const ctx = document.getElementById('forecastChart');
    
    if (!ctx) return;
    
    // Подготовка данных по месяцам
    const monthlyData = {};
    
    items.forEach(item => {
        const month = item.period.substring(0, 7); // YYYY-MM
        if (!monthlyData[month]) {
            monthlyData[month] = { income: 0, expense: 0 };
        }
        if (item.type === 'income') {
            monthlyData[month].income += item.amount;
        } else {
            monthlyData[month].expense += item.amount;
        }
    });
    
    const labels = Object.keys(monthlyData).sort().map(m => {
        const date = new Date(m + '-01');
        return date.toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
    });
    
    const incomeData = Object.keys(monthlyData).sort().map(m => monthlyData[m].income);
    const expenseData = Object.keys(monthlyData).sort().map(m => monthlyData[m].expense);
    
    // Прогнозные значения (скользящее среднее)
    const lastThreeIncome = incomeData.slice(-3);
    const avgIncome = lastThreeIncome.reduce((a, b) => a + b, 0) / lastThreeIncome.length;
    
    const lastThreeExpense = expenseData.slice(-3);
    const avgExpense = lastThreeExpense.reduce((a, b) => a + b, 0) / lastThreeExpense.length;
    
    // Добавляем прогнозные точки
    const forecastLabels = ['Май (прогноз)', 'Июнь (прогноз)', 'Июль (прогноз)'];
    const allLabels = [...labels, ...forecastLabels];
    
    const allIncomeData = [...incomeData, avgIncome, avgIncome, avgIncome];
    const allExpenseData = [...expenseData, avgExpense, avgExpense, avgExpense];
    
    const data = {
        labels: allLabels,
        datasets: [
            {
                label: 'Доходы (факт)',
                data: incomeData.map((v, i) => i < incomeData.length ? v : null),
                borderColor: '#27AE60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Доходы (прогноз)',
                data: incomeData.concat([null, null, null]).map((v, i, arr) => {
                    if (v !== null) return null;
                    return avgIncome;
                }),
                borderColor: '#27AE60',
                borderDash: [5, 5],
                tension: 0.4,
                fill: false
            },
            {
                label: 'Расходы (факт)',
                data: expenseData.map((v, i) => i < expenseData.length ? v : null),
                borderColor: '#E74C3C',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Расходы (прогноз)',
                data: expenseData.concat([null, null, null]).map((v, i, arr) => {
                    if (v !== null) return null;
                    return avgExpense;
                }),
                borderColor: '#E74C3C',
                borderDash: [5, 5],
                tension: 0.4,
                fill: false
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
    
    if (forecastChartInstance) {
        forecastChartInstance.destroy();
    }
    
    forecastChartInstance = new Chart(ctx, {
        type: 'line',
        data: data,
        options: options
    });
}

// Генерация прогноза
async function generateForecast() {
    try {
        FinHorizon.notifications.show('Генерация прогноза...', 'info');
        
        // Имитация задержки
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // В продакшене: await FinHorizon.api.post('budget.php?action=generate_forecast&scenario_id=' + scenarioId);
        
        FinHorizon.notifications.show('Прогноз успешно сгенерирован!', 'success');
        
        // Перезагрузка данных
        await loadBudgetItems();
        
    } catch (error) {
        FinHorizon.notifications.show('Ошибка генерации прогноза: ' + error.message, 'error');
    }
}

// Открытие модального окна добавления статьи
function openAddItemModal() {
    FinHorizon.modal.open('addItemModal');
}

// Инициализация формы добавления статьи
function initAddItemForm() {
    const form = document.getElementById('addItemForm');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            scenario_id: parseInt(document.getElementById('itemScenarioId').value),
            category_id: parseInt(document.getElementById('itemCategory').value),
            period: document.getElementById('itemPeriod').value + '-01',
            amount: parseFloat(document.getElementById('itemAmount').value),
            comment: document.getElementById('itemComment').value
        };
        
        try {
            // В продакшене: await FinHorizon.api.post('budget.php?action=item', formData);
            
            FinHorizon.notifications.show('Статья бюджета сохранена!', 'success');
            FinHorizon.modal.close('addItemModal');
            form.reset();
            
            // Перезагрузка данных
            await loadBudgetItems();
            
        } catch (error) {
            FinHorizon.notifications.show('Ошибка сохранения: ' + error.message, 'error');
        }
    });
}
