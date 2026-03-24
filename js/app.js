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
const quickCalcForm = document.getElementById('quickCalcForm');
const quickCalcResult = document.getElementById('quickCalcResult');

const chart = createChart();
restoreSettings();
applySettings();
bindNavigation();
bindSidebarToggle();
bindBudgetTools();
bindScenarioControls();
bindSettingsForm();
bindQuickCalc();

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
        sidebarToggleButton.textContent = isCollapsed ? 'Развернуть меню' : 'Свернуть меню';
        sidebarToggleButton.setAttribute('aria-expanded', String(!isCollapsed));
    });
}

function bindQuickCalc() {
    if (!quickCalcForm || !quickCalcResult) {
        return;
    }

    quickCalcForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(quickCalcForm);
        const income = Number(formData.get('income') || 0);
        const expense = Number(formData.get('expense') || 0);
        const profit = income - expense;
        const margin = income > 0 ? (profit / income) * 100 : 0;

        const profitability = margin >= 0 ? 'Положительная' : 'Отрицательная';
        const profitLabel = `${profit >= 0 ? '+' : '-'}${Math.abs(profit).toLocaleString('ru-RU')} ₽`;
        quickCalcResult.textContent = `Прибыль: ${profitLabel} · Рентабельность: ${margin.toFixed(1)}% (${profitability}).`;
    });
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
