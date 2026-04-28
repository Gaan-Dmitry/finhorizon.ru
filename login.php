<?php
/**
 * ФинГоризонт - Страница входа
 */

require_once 'includes/config.php';

// Если уже авторизован, перенаправляем на dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2C3E50 0%, #3498DB 100%);
        }
        
        .auth-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo-icon {
            width: 60px;
            height: 60px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .auth-logo-icon img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .auth-logo h1 {
            color: #2C3E50;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .auth-logo p {
            color: #27AE60;
            margin: 5px 0 0;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 2px solid #ECF0F1;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            color: #7F8C8D;
            transition: all 0.3s;
        }
        
        .auth-tab.active {
            color: #2C3E50;
            border-bottom: 3px solid #2C3E50;
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .auth-btn {
            width: 100%;
            padding: 14px;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        
        .auth-divider {
            text-align: center;
            margin: 20px 0;
            color: #7F8C8D;
        }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <img src="/logo.svg" alt="Логотип ФинГоризонт">
                </div>
                <h1>ФинГоризонт</h1>
                <p>Планируйте уверенно</p>
            </div>
            
            <div class="auth-tabs">
                <div class="auth-tab active" onclick="switchTab('login')">Вход</div>
                <div class="auth-tab" onclick="switchTab('register')">Регистрация</div>
            </div>
            
            <!-- Форма входа -->
            <form id="loginForm" class="auth-form active" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="loginEmail" class="form-control" required placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Пароль</label>
                    <input type="password" id="loginPassword" class="form-control" required placeholder="••••••••">
                </div>
                
                <div id="loginError" class="alert alert-error" style="display: none;"></div>
                
                <button type="submit" class="btn btn-primary auth-btn">Войти</button>
            </form>
            
            <!-- Форма регистрации -->
            <form id="registerForm" class="auth-form" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="registerEmail" class="form-control" required placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Название компании (необязательно)</label>
                    <input type="text" id="registerCompany" class="form-control" placeholder="ООО «Пример»">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Пароль</label>
                    <input type="password" id="registerPassword" class="form-control" required minlength="6" placeholder="Минимум 6 символов">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Подтверждение пароля</label>
                    <input type="password" id="registerPasswordConfirm" class="form-control" required minlength="6" placeholder="Повторите пароль">
                </div>
                
                <div id="registerError" class="alert alert-error" style="display: none;"></div>
                
                <button type="submit" class="btn btn-accent auth-btn">Зарегистрироваться</button>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            
            if (tab === 'login') {
                document.querySelector('.auth-tab:first-child').classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.querySelector('.auth-tab:last-child').classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
            
            // Сброс ошибок
            document.getElementById('loginError').style.display = 'none';
            document.getElementById('registerError').style.display = 'none';
        }
        
        async function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const errorDiv = document.getElementById('loginError');
            
            try {
                const response = await fetch('/api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = data.redirect || '/index.php';
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'flex';
                }
            } catch (err) {
                errorDiv.textContent = 'Ошибка соединения с сервером';
                errorDiv.style.display = 'flex';
            }
        }
        
        async function handleRegister(event) {
            event.preventDefault();
            
            const email = document.getElementById('registerEmail').value;
            const company = document.getElementById('registerCompany').value;
            const password = document.getElementById('registerPassword').value;
            const passwordConfirm = document.getElementById('registerPasswordConfirm').value;
            const errorDiv = document.getElementById('registerError');
            
            if (password !== passwordConfirm) {
                errorDiv.textContent = 'Пароли не совпадают';
                errorDiv.style.display = 'flex';
                return;
            }
            
            try {
                const response = await fetch('/api/auth.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, company_name: company, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/index.php';
                } else {
                    errorDiv.textContent = data.error;
                    errorDiv.style.display = 'flex';
                }
            } catch (err) {
                errorDiv.textContent = 'Ошибка соединения с сервером';
                errorDiv.style.display = 'flex';
            }
        }
    </script>
</body>
</html>
