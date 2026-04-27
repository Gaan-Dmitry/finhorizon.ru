/**
 * ФинГоризонт - Основной JavaScript файл
 */

// Глобальный объект приложения
const FinHorizon = {
    api: {
        baseURL: 'api/'
    },
    
    // Утилиты
    utils: {
        formatMoney(amount) {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                minimumFractionDigits: 2
            }).format(amount);
        },
        
        formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: 'long'
            });
        },
        
        parseDate(dateStr) {
            const date = new Date(dateStr);
            return date.toISOString().split('T')[0];
        }
    },
    
    // API запросы
    api: {
        async request(endpoint, options = {}) {
            const url = this.baseURL + endpoint;
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            };
            
            const config = { ...defaultOptions, ...options };
            
            try {
                const response = await fetch(url, config);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Ошибка запроса');
                }
                
                return data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },
        
        get(endpoint) {
            return this.request(endpoint, { method: 'GET' });
        },
        
        post(endpoint, data) {
            return this.request(endpoint, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },
        
        put(endpoint, data) {
            return this.request(endpoint, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },
        
        delete(endpoint) {
            return this.request(endpoint, { method: 'DELETE' });
        }
    },
    
    // Модальные окна
    modal: {
        open(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        },
        
        close(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        },
        
        init() {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                    }
                });
            });
            
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = btn.closest('.modal-overlay');
                    if (modal) {
                        modal.classList.remove('active');
                    }
                });
            });
        }
    },
    
    // Уведомления
    notifications: {
        show(message, type = 'info') {
            const container = document.getElementById('notifications') || this.createContainer();
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        },
        
        createContainer() {
            const container = document.createElement('div');
            container.id = 'notifications';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(container);
            return container;
        }
    },
    
    // Графики
    charts: {
        instances: {},
        
        create(ctx, type, data, options = {}) {
            if (this.instances[ctx]) {
                this.instances[ctx].destroy();
            }
            
            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return FinHorizon.utils.formatMoney(value);
                            }
                        }
                    }
                }
            };
            
            const config = { ...defaultOptions, ...options };
            
            this.instances[ctx] = new Chart(ctx, {
                type: type,
                data: data,
                options: config
            });
            
            return this.instances[ctx];
        }
    },
    
    // Инициализация
    init() {
        this.modal.init();
        console.log('ФинГоризонт инициализирован');
    }
};

// Инициализация после загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    FinHorizon.init();
});
