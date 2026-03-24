const config = window.dashboardConfig || {};

const state = {
    activeSection: 'dashboard',
    activeScenario: config.defaultScenario || 'optimistic',
    settings: {
        accent: config.settings?.accent || '#27AE60',
        compactMode: Boolean(config.settings?.compactMode),
        notifications: Boolean(config.settings?.notifications),
    },
};

const storageKey = 'finhorizon-dashboard-settings';
const root = document.body;
const pageHeading = document.getElementById('pageHeading');
const pageSlogan = document.getElementById('pageSlogan');
const navButtons = Array.from(document.querySelectorAll('[data-section-target]'));
const sections = Array.from(document.querySelectorAll('.content-section'));
const scenarioButtons = Array.from(document.querySelectorAll('[data-scenario]'));
const scenarioApplyButtons = Array.from(document.querySelectorAll('[data-scenario-apply]'));
const scenarioCards = Array.from(document.querySelectorAll('[data-scenario-card]'));
const scenarioNames = config.scenarioNames || {};
const budgetTable = document.getElementById('budgetTable');
const highlightBudgetButton = document.getElementById('highlightBudgetButton');
const settingsForm = document.getElementById('settingsForm');
const resetSettingsButton = document.getElementById('resetSettingsButton');
const settingsHint = document.getElementById('settingsHint');
const sidebar = document.getElementById('sidebar');
const sidebarToggleButton = document.getElementById('sidebarToggleButton');
const operationForm = document.getElementById('operationForm');
const operationFormResult = document.getElementById('operationFormResult');
const operationSubmitButton = document.getElementById('operationSubmitButton');
const operationsTableBody = document.getElementById('operationsTableBody');

const chart = createChart();
restoreSettings();
applySettings();
bindNavigation();
bindSidebarToggle();
bindBudgetTools();
bindScenarioControls();
bindSettingsForm();
bindOperations();

function bindNavigation() {
    navButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const sectionId = button.dataset.sectionTarget;
            state.activeSection = sectionId;

            navButtons.forEach((item) => item.classList.toggle('is-active', item === button));
            sections.forEach((section) => {
                section.classList.toggle('is-active', section.dataset.section === sectionId);
            });

            pageHeading.textContent = button.dataset.heading || 'Финансовая панель';
            pageSlogan.textContent = button.dataset.slogan || '';

            root.classList.add('sidebar-collapsed');
            sidebarToggleButton?.setAttribute('aria-expanded', 'false');
        });
    });
}

function bindBudgetTools() {
    if (!highlightBudgetButton || !budgetTable) {
        return;
    }

    highlightBudgetButton.addEventListener('click', () => {
        const rows = budgetTable.querySelectorAll('tbody tr');
        rows.forEach((row) => {
            const isRisk = row.dataset.status === 'Риск перерасхода';
            row.classList.toggle('is-highlighted', isRisk);
        });

        if (state.settings.notifications) {
            settingsHint.textContent = 'Обнаружены статьи с риском перерасхода. Проверьте лимиты.';
        }
    });
}

function bindSidebarToggle() {
    if (!sidebar || !sidebarToggleButton) {
        return;
    }

    sidebarToggleButton.addEventListener('click', () => {
        const isCollapsed = root.classList.toggle('sidebar-collapsed');
        sidebarToggleButton.setAttribute('aria-expanded', String(!isCollapsed));
        sidebarToggleButton.setAttribute('aria-label', isCollapsed ? 'Развернуть меню' : 'Свернуть меню');
    });
}

function bindOperations() {
    if (!operationForm || !operationFormResult || !operationsTableBody) {
        return;
    }

    operationForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(operationForm);
        const editIndex = formData.get('editIndex');
        const description = String(formData.get('description') || '').trim();
        const category = String(formData.get('category') || '').trim();
        const date = String(formData.get('date') || '').trim();
        const direction = String(formData.get('direction') || 'inflow');
        const amount = Number(formData.get('amount') || 0);

        if (!description || !category || !date || amount <= 0) {
            operationFormResult.textContent = 'Заполните все поля корректно.';
            return;
        }

        const formattedAmount = `${direction === 'inflow' ? '+' : '-'}${Math.abs(amount).toLocaleString('ru-RU')} ₽`;
        const amountClass = direction === 'inflow' ? 'positive' : 'negative';
        const rowHtml = `
            <td>${escapeHtml(description)}</td>
            <td>${escapeHtml(category)}</td>
            <td>${escapeHtml(date.split('-').reverse().join('.'))}</td>
            <td class="amount ${amountClass}">${formattedAmount}</td>
            <td class="align-right"><button class="button button--secondary button--small" type="button" data-operation-edit>Редактировать</button></td>
        `;

        if (editIndex !== '') {
            const row = operationsTableBody.querySelector(`tr[data-operation-index="${editIndex}"]`);
            if (row) {
                row.dataset.direction = direction;
                row.innerHTML = rowHtml;
                operationFormResult.textContent = 'Операция обновлена.';
            }
        } else {
            const row = document.createElement('tr');
            row.dataset.operationIndex = String(Date.now());
            row.dataset.direction = direction;
            row.innerHTML = rowHtml;
            operationsTableBody.prepend(row);
            operationFormResult.textContent = 'Операция добавлена.';
        }

        operationForm.reset();
        operationForm.elements.editIndex.value = '';
        operationSubmitButton.textContent = 'Добавить операцию';
    });

    operationsTableBody.querySelectorAll('tr').forEach((row, index) => {
        row.dataset.operationIndex = String(index + 1);
    });

    operationsTableBody.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.matches('[data-operation-edit]')) {
            return;
        }

        const row = target.closest('tr');
        if (!row) {
            return;
        }

        const cells = row.querySelectorAll('td');
        const [description, category, date, amountCell] = cells;
        const rawAmount = Number(amountCell?.textContent?.replace(/[^\d-]/g, '') || 0);
        const direction = row.dataset.direction || (rawAmount >= 0 ? 'inflow' : 'outflow');

        operationForm.elements.description.value = description?.textContent?.trim() || '';
        operationForm.elements.category.value = category?.textContent?.trim() || '';
        operationForm.elements.date.value = (date?.textContent || '').split('.').reverse().join('-');
        operationForm.elements.direction.value = direction;
        operationForm.elements.amount.value = String(Math.abs(rawAmount));
        operationForm.elements.editIndex.value = row.dataset.operationIndex || '';
        operationSubmitButton.textContent = 'Сохранить изменения';
        operationFormResult.textContent = 'Режим редактирования операции.';
    });
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function bindScenarioControls() {
    const activateScenario = (scenarioId) => {
        state.activeScenario = scenarioId;
        scenarioButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.scenario === scenarioId);
        });
        scenarioCards.forEach((card) => {
            card.classList.toggle('is-selected', card.dataset.scenarioCard === scenarioId);
        });
        updateChart(scenarioId);
    };

    scenarioButtons.forEach((button) => {
        button.addEventListener('click', () => activateScenario(button.dataset.scenario));
    });

    scenarioApplyButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activateScenario(button.dataset.scenarioApply);
            switchToSection('dashboard');
        });
    });
}

function bindSettingsForm() {
    if (!settingsForm) {
        return;
    }

    settingsForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const formData = new FormData(settingsForm);
        state.settings.accent = formData.get('accent') || '#27AE60';
        state.settings.compactMode = formData.get('compactMode') === 'on';
        state.settings.notifications = formData.get('notifications') === 'on';

        updateChart(state.activeScenario);
        persistSettings();
        applySettings();
        settingsHint.textContent = 'Настройки сохранены локально и применены ко всем разделам.';
    });

    resetSettingsButton?.addEventListener('click', () => {
        localStorage.removeItem(storageKey);
        state.settings = {
            accent: config.settings?.accent || '#27AE60',
            compactMode: Boolean(config.settings?.compactMode),
            notifications: Boolean(config.settings?.notifications),
        };
        syncSettingsForm();
        applySettings();
        settingsHint.textContent = 'Настройки сброшены к значениям по умолчанию.';
    });
}

function switchToSection(sectionId) {
    const targetButton = navButtons.find((button) => button.dataset.sectionTarget === sectionId);
    targetButton?.click();
}

function createChart() {
    const canvas = document.getElementById('forecastChart');
    if (!canvas || typeof Chart === 'undefined') {
        return null;
    }

    return new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: config.chart?.labels || [],
            datasets: [
                {
                    label: 'Фактическая выручка',
                    data: config.chart?.actual || [],
                    borderColor: '#2C3E50',
                    backgroundColor: '#2C3E50',
                    tension: 0.2,
                    borderWidth: 3,
                },
                {
                    label: 'Сценарий',
                    data: scenarioToDataset(state.activeScenario),
                    borderColor: state.settings.accent,
                    backgroundColor: `${state.settings.accent}22`,
                    borderDash: [8, 5],
                    fill: true,
                    tension: 0.3,
                    borderWidth: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: {
                            family: 'Roboto Mono',
                        },
                        callback(value) {
                            return `${Number(value).toLocaleString('ru-RU')} ₽`;
                        },
                    },
                },
            },
        },
    });
}

function updateChart(scenarioId) {
    if (!chart) {
        return;
    }

    chart.data.datasets[1].data = scenarioToDataset(scenarioId);
    chart.data.datasets[1].label = `Сценарий: ${scenarioNames[scenarioId] || scenarioId}`;
    chart.data.datasets[1].borderColor = state.settings.accent;
    chart.data.datasets[1].backgroundColor = `${state.settings.accent}22`;
    chart.update();
}

function scenarioToDataset(scenarioId) {
    const labels = config.chart?.labels || [];
    const actualValues = config.chart?.actual || [];
    const values = config.chart?.scenarios?.[scenarioId] || [];
    const firstProjectedIndex = actualValues.findIndex((value) => value === null || value === undefined);
    const startIndex = firstProjectedIndex === -1 ? Math.max(labels.length - values.length, 0) : Math.max(firstProjectedIndex - 1, 0);
    const dataset = new Array(labels.length).fill(null);

    values.forEach((value, index) => {
        const targetIndex = startIndex + index;
        if (targetIndex < dataset.length) {
            dataset[targetIndex] = value;
        }
    });

    return dataset;
}

function restoreSettings() {
    try {
        const rawSettings = localStorage.getItem(storageKey);
        if (!rawSettings) {
            syncSettingsForm();
            return;
        }

        const savedSettings = JSON.parse(rawSettings);
        state.settings = {
            ...state.settings,
            ...savedSettings,
        };
    } catch (error) {
        console.warn('Не удалось восстановить настройки:', error);
    }

    syncSettingsForm();
}

function syncSettingsForm() {
    if (!settingsForm) {
        return;
    }

    settingsForm.elements.accent.value = state.settings.accent;
    settingsForm.elements.compactMode.checked = state.settings.compactMode;
    settingsForm.elements.notifications.checked = state.settings.notifications;
}

function persistSettings() {
    localStorage.setItem(storageKey, JSON.stringify(state.settings));
}

function applySettings() {
    root.style.setProperty('--accent-color', state.settings.accent);
    root.classList.toggle('compact-mode', state.settings.compactMode);
    updateChart(state.activeScenario);
}
