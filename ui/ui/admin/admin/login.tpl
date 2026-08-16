<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#f97316">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{Lang::T('Login')} - {$_c['CompanyName']}</title>
    <link rel="shortcut icon" href="{$app_url}/ui/ui/images/logo.png" type="image/x-icon" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            position: relative;
        }

        /* Decorative circles */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.15) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(249, 115, 22, 0.2);
            overflow: hidden;
            padding: 32px 24px 40px;
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(249, 115, 22, 0.3);
        }

        .logo-circle i {
            font-size: 40px;
            color: white;
        }

        .brand-name {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .brand-subtitle {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #9ca3af;
            font-size: 18px;
            pointer-events: none;
            transition: all 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            color: #1f2937;
            background: white;
            transition: all 0.3s;
            outline: none;
        }

        .form-control:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .form-control:focus + .input-icon {
            color: #f97316;
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 16px;
            cursor: pointer;
            color: #9ca3af;
            font-size: 18px;
            padding: 8px;
            transition: color 0.3s;
            z-index: 2;
        }

        .password-toggle:active {
            color: #f97316;
        }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            margin-top: 28px;
            box-shadow: 0 5px 15px rgba(249, 115, 22, 0.3);
        }

        .btn-login:active {
            transform: scale(0.98);
            box-shadow: 0 2px 10px rgba(249, 115, 22, 0.3);
        }

        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        .btn-login.loading .spinner {
            display: inline-block;
        }

        .btn-login.loading .btn-text,
        .btn-login.loading i {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 24px;
        }

        .footer-links a {
            color: #6b7280;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            display: inline-block;
            transition: color 0.3s;
        }

        .footer-links a:active {
            color: #f97316;
        }

        /* Powered By */
        .powered-by {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .powered-by p {
            font-size: 11px;
            color: #9ca3af;
            letter-spacing: 0.5px;
        }

        .powered-by span {
            font-weight: 700;
            color: #f97316;
        }

        /* Toast Message */
        .toast-message {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            transform: translateY(150%);
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }

        @media (min-width: 481px) {
            .toast-message {
                bottom: auto;
                top: 20px;
                left: auto;
                right: 20px;
                min-width: 320px;
                max-width: 380px;
                transform: translateX(120%);
            }
            
            .toast-message.show {
                transform: translateX(0);
            }
        }

        .toast-message.show {
            transform: translateY(0);
        }

        .toast-message.success {
            border-left-color: #f97316;
            background: #fff7ed;
        }

        .toast-message.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .toast-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .toast-text {
            font-size: 12px;
            color: #6b7280;
        }

        .toast-close {
            cursor: pointer;
            padding: 6px;
            font-size: 14px;
            color: #9ca3af;
            flex-shrink: 0;
        }

        .toast-close:active {
            color: #f97316;
        }

        /* Dark Mode */
        @media (prefers-color-scheme: dark) {
            .login-card {
                background: rgba(30, 27, 22, 0.98);
            }
            
            .brand-subtitle {
                color: #9ca3af;
            }
            
            .form-control {
                background: #1f1a15;
                border-color: #3d352a;
                color: #fef3c7;
            }
            
            .form-control:focus {
                border-color: #f97316;
            }
            
            .input-icon {
                color: #6b7280;
            }
            
            .footer-links a {
                color: #9ca3af;
            }
            
            .powered-by {
                border-top-color: #3d352a;
            }
            
            .toast-message.success {
                background: #2d1f12;
            }
            
            .toast-message.error {
                background: #2c1a1f;
            }
            
            .toast-text {
                color: #cbd5e1;
            }
        }

        /* Mobile optimizations */
        @media (max-width: 480px) {
            body {
                padding: 12px;
                align-items: center;
            }
            
            .login-card {
                padding: 28px 20px 36px;
                border-radius: 28px;
            }
            
            .logo-circle {
                width: 70px;
                height: 70px;
                border-radius: 20px;
            }
            
            .logo-circle i {
                font-size: 34px;
            }
            
            .brand-name {
                font-size: 24px;
            }
            
            .brand-subtitle {
                font-size: 13px;
            }
            
            .form-control {
                padding: 12px 16px 12px 46px;
                font-size: 16px;
            }
            
            .input-icon {
                left: 14px;
                font-size: 16px;
            }
            
            .btn-login {
                padding: 12px;
                font-size: 15px;
                margin-top: 24px;
            }
        }

        /* Landscape mode */
        @media (max-width: 768px) and (orientation: landscape) {
            body {
                padding: 10px;
                align-items: center;
            }
            
            .login-card {
                padding: 20px 24px 28px;
            }
            
            .logo-section {
                margin-bottom: 20px;
            }
            
            .logo-circle {
                width: 55px;
                height: 55px;
                margin-bottom: 12px;
            }
            
            .logo-circle i {
                font-size: 28px;
            }
            
            .brand-name {
                font-size: 20px;
            }
            
            .form-group {
                margin-bottom: 14px;
            }
            
            .btn-login {
                margin-top: 18px;
            }
        }

        /* Prevent zoom on input focus */
        @media (max-width: 480px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-circle">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1 class="brand-name">{$_c['CompanyName']}</h1>
                <p class="brand-subtitle">Welcome Back</p>
            </div>

            <!-- Login Form -->
            <form action="{Text::url('admin/post')}" method="post" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="off">
                        <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

                <div class="footer-links">
                    <a href="{Text::url('forgot')}">
                        <i class="fas fa-question-circle"></i> Forgot Password?
                    </a>
                </div>

                <div class="powered-by">
                    <p>Powered by <span>RAYPROTECH</span></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastMessage" class="toast-message">
        <div class="toast-icon" id="toastIcon"></div>
        <div class="toast-content">
            <div class="toast-title" id="toastTitle">Notice</div>
            <div class="toast-text" id="toastText">Message</div>
        </div>
        <i class="fas fa-times toast-close" id="toastClose"></i>
    </div>

    <script>
        (function() {
            // DOM Elements
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const toast = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastText = document.getElementById('toastText');
            const toastClose = document.getElementById('toastClose');
            
            let toastTimeout = null;
            
            // Show Toast Function
            window.showToast = function(message, type = 'info', title = '') {
                if (!toast) return;
                
                if (toastTimeout) clearTimeout(toastTimeout);
                toast.classList.remove('show');
                
                let iconHtml = '';
                let defaultTitle = '';
                let bgClass = 'info';
                
                switch(type) {
                    case 'success':
                        iconHtml = '<i class="fas fa-check-circle" style="color: #f97316;"></i>';
                        defaultTitle = 'Success';
                        bgClass = 'success';
                        break;
                    case 'error':
                        iconHtml = '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';
                        defaultTitle = 'Error';
                        bgClass = 'error';
                        break;
                    default:
                        iconHtml = '<i class="fas fa-info-circle" style="color: #3b82f6;"></i>';
                        defaultTitle = 'Notice';
                        bgClass = 'info';
                }
                
                toastIcon.innerHTML = iconHtml;
                toastTitle.textContent = title || defaultTitle;
                toastText.textContent = message;
                toast.className = 'toast-message ' + bgClass;
                
                setTimeout(() => toast.classList.add('show'), 10);
                
                toastTimeout = setTimeout(() => {
                    toast.classList.remove('show');
                }, 4000);
            };
            
            // Password Toggle
            if (passwordToggle && passwordInput) {
                const togglePassword = function(e) {
                    e.preventDefault();
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    passwordToggle.classList.toggle('fa-eye');
                    passwordToggle.classList.toggle('fa-eye-slash');
                };
                
                passwordToggle.addEventListener('click', togglePassword);
                passwordToggle.addEventListener('touchend', togglePassword);
            }
            
            // Form Validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    const username = usernameInput ? usernameInput.value.trim() : '';
                    const password = passwordInput ? passwordInput.value : '';
                    
                    if (!username) {
                        e.preventDefault();
                        showToast('Please enter your username', 'error', 'Validation');
                        if (usernameInput) {
                            usernameInput.style.borderColor = '#ef4444';
                            usernameInput.focus();
                        }
                        return false;
                    }
                    
                    if (!password) {
                        e.preventDefault();
                        showToast('Please enter your password', 'error', 'Validation');
                        if (passwordInput) {
                            passwordInput.style.borderColor = '#ef4444';
                            passwordInput.focus();
                        }
                        return false;
                    }
                    
                    // Show loading state
                    if (loginBtn) {
                        loginBtn.classList.add('loading');
                        loginBtn.disabled = true;
                    }
                    
                    return true;
                });
                
                // Clear border color on input
                if (usernameInput) {
                    usernameInput.addEventListener('input', function() {
                        this.style.borderColor = '';
                    });
                }
                
                if (passwordInput) {
                    passwordInput.addEventListener('input', function() {
                        this.style.borderColor = '';
                    });
                }
            }
            
            // Auto focus
            if (usernameInput && !usernameInput.value) {
                setTimeout(() => usernameInput.focus(), 100);
            } else if (passwordInput && !passwordInput.value) {
                setTimeout(() => passwordInput.focus(), 100);
            }
            
            // Toast close
            if (toastClose) {
                toastClose.addEventListener('click', function() {
                    toast.classList.remove('show');
                    if (toastTimeout) clearTimeout(toastTimeout);
                });
            }
            
            // Handle server messages
            {if $popup}
                const popupHtml = `{$popup|escape:'javascript'}`;
                const popupMessageText = popupHtml.replace(/<[^>]*>/g, '');
                const lowerMessage = popupHtml.toLowerCase();
                
                const isSuccess = lowerMessage.includes('success') || lowerMessage.includes('welcome') || lowerMessage.includes('redirect');
                const isError = lowerMessage.includes('invalid') || lowerMessage.includes('fail') || lowerMessage.includes('error') || lowerMessage.includes('incorrect');
                
                setTimeout(() => {
                    if (loginBtn) {
                        loginBtn.classList.remove('loading');
                        loginBtn.disabled = false;
                    }
                }, 200);
                
                if (isSuccess) {
                    showToast(popupMessageText, 'success', 'Welcome');
                } else if (isError) {
                    showToast(popupMessageText, 'error', 'Login Failed');
                    if (passwordInput) passwordInput.value = '';
                    setTimeout(() => {
                        if (loginBtn) {
                            loginBtn.classList.remove('loading');
                            loginBtn.disabled = false;
                        }
                    }, 500);
                } else {
                    showToast(popupMessageText, 'info');
                }
            {/if}
        })();
    </script>
</body>

</html>