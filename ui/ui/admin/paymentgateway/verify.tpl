{include file="sections/header.tpl"}

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #FF6B35 0%, #F08A5D 100%);
    --success-gradient: linear-gradient(135deg, #FF8C42 0%, #FFB347 100%);
    --danger-gradient: linear-gradient(135deg, #FF4E50 0%, #F9D423 100%);
    --sunset-gradient: linear-gradient(135deg, #FF512F 0%, #F09819 100%);
}

body {
    background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 50%, #F08A5D 100%);
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

.verification-wrapper {
    min-height: calc(100vh - 60px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    position: relative;
    z-index: 1;
}

.verification-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(255, 107, 53, 0.3);
    overflow: hidden;
    transform: translateY(0);
    transition: transform 0.3s ease;
    animation: slideInUp 0.5s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 500px;
    margin: 0 auto;
}

.verification-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 50px rgba(255, 107, 53, 0.4);
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header {
    background: var(--sunset-gradient);
    padding: 20px 20px 15px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,215,0,0.1) 0%, transparent 60%);
    animation: rotate 25s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.card-header h3 {
    margin: 0;
    color: white;
    font-size: 24px;
    font-weight: 600;
    text-shadow: 2px 2px 4px rgba(255, 81, 47, 0.3);
    position: relative;
    z-index: 1;
}

.card-header h3 i {
    margin-right: 8px;
    font-size: 24px;
    animation: bounce 2s ease infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

.card-header .subtitle {
    color: rgba(255,255,255,0.9);
    margin-top: 5px;
    font-size: 14px;
    position: relative;
    z-index: 1;
}

.card-body {
    padding: 25px 25px 20px;
    background: rgba(255, 255, 255, 0.95);
    position: relative;
}

.icon-circle {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: var(--sunset-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(255, 107, 53, 0.4);
    animation: bounceIn 0.6s ease;
}

@keyframes bounceIn {
    0% { transform: scale(0); opacity: 0; }
    70% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

.icon-circle i {
    font-size: 32px;
    color: white;
}

.security-badge {
    background: var(--sunset-gradient);
    color: white;
    padding: 10px 18px;
    border-radius: 30px;
    display: inline-block;
    margin-bottom: 20px;
    font-weight: 500;
    font-size: 14px;
    box-shadow: 0 5px 12px rgba(255, 81, 47, 0.3);
    animation: shimmer 3s infinite;
    background-size: 200% 200%;
}

@keyframes shimmer {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.timer-badge {
    background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
    border: 1px solid #FFB74D;
    padding: 8px 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #E65100;
    font-size: 13px;
    font-weight: 500;
}

.timer-badge i {
    color: #FF6B35;
    font-size: 14px;
}

.verification-form {
    margin-top: 15px;
}

.input-group-modern {
    position: relative;
    margin-bottom: 25px;
}

.input-group-modern label {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #FF8C42;
    transition: all 0.3s ease;
    pointer-events: none;
    font-size: 14px;
}

.input-group-modern.focused label,
.input-group-modern.has-value label {
    top: -10px;
    left: 12px;
    font-size: 11px;
    background: white;
    padding: 0 5px;
    color: #FF6B35;
    font-weight: 600;
}

.input-group-modern input {
    width: 100%;
    padding: 14px 45px 14px 15px;
    border: 2px solid #FFE0B2;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
    color: #333;
}

.input-group-modern input:focus {
    border-color: #FF6B35;
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
    outline: none;
}

.input-group-modern input:focus ~ label,
.input-group-modern input:valid ~ label {
    top: -10px;
    left: 12px;
    font-size: 11px;
    background: white;
    padding: 0 5px;
    color: #FF6B35;
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #FF8C42;
    transition: color 0.3s ease;
    z-index: 2;
    font-size: 16px;
}

.toggle-password:hover {
    color: #FF6B35;
}

.password-strength {
    margin-top: -15px;
    margin-bottom: 20px;
    height: 4px;
    background: #FFE0B2;
    border-radius: 4px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s ease, background 0.3s ease;
    border-radius: 4px;
}

.btn-verify {
    background: var(--sunset-gradient);
    border: none;
    color: white;
    padding: 14px 25px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    width: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(255, 81, 47, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-verify:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(255, 81, 47, 0.4);
}

.btn-verify:active {
    transform: translateY(1px);
}

.btn-verify i {
    margin-right: 8px;
    transition: transform 0.3s ease;
    font-size: 16px;
}

.btn-verify:hover i {
    transform: scale(1.1);
}

.card-footer {
    background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
    padding: 12px 20px;
    border-top: 1px solid #FFB74D;
    text-align: center;
}

.security-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #E65100;
    font-size: 12px;
    font-weight: 500;
}

.security-info i {
    color: #FF6B35;
    font-size: 14px;
}

.error-message {
    background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
    color: white;
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: shake 0.4s ease;
    box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
    font-size: 13px;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}

.error-message i {
    font-size: 18px;
}

.alert-modern {
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--sunset-gradient);
    color: white;
    box-shadow: 0 8px 20px rgba(255, 107, 53, 0.3);
}

.alert-modern i {
    font-size: 24px;
}

.alert-modern .alert-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 3px;
}

.alert-modern .alert-message {
    opacity: 0.9;
    font-size: 13px;
}

/* Loading animation */
.btn-verify.loading {
    pointer-events: none;
    opacity: 0.9;
}

.btn-verify.loading .btn-text {
    opacity: 0;
}

.btn-verify.loading:after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid white;
    border-top-color: #FFD700;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Floating oranges - smaller and fewer */
.orange-float {
    position: absolute;
    font-size: 30px;
    opacity: 0.15;
    pointer-events: none;
    z-index: 0;
    animation: float 8s ease-in-out infinite;
}

.orange-float:nth-child(1) {
    top: 15%;
    left: 5%;
    animation-delay: 0s;
}

.orange-float:nth-child(2) {
    bottom: 15%;
    right: 5%;
    animation-delay: 3s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-15px) rotate(8deg); }
}

/* Responsive for mobile */
@media (max-width: 768px) {
    .verification-card {
        max-width: 100%;
        margin: 0 10px;
    }
    
    .card-body {
        padding: 20px 15px;
    }
    
    .card-header {
        padding: 15px 15px 10px;
    }
    
    .card-header h3 {
        font-size: 20px;
    }
    
    .card-header h3 i {
        font-size: 20px;
    }
    
    .icon-circle {
        width: 60px;
        height: 60px;
    }
    
    .icon-circle i {
        font-size: 28px;
    }
    
    .security-badge {
        padding: 8px 15px;
        font-size: 12px;
    }
    
    .btn-verify {
        padding: 12px 20px;
        font-size: 15px;
    }
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    body {
        background: linear-gradient(135deg, #BF4E1C 0%, #D45D2C 100%);
    }
    
    .verification-card {
        background: rgba(40, 40, 40, 0.98);
    }
    
    .card-body {
        background: rgba(40, 40, 40, 0.98);
    }
    
    .input-group-modern input {
        background: #333;
        border-color: #BF4E1C;
        color: #FFE0B2;
    }
    
    .input-group-modern label {
        color: #FFB74D;
    }
    
    .input-group-modern input:focus ~ label,
    .input-group-modern.has-value label {
        background: #333;
    }
    
    .timer-badge {
        background: #332211;
        color: #FFB74D;
    }
}
</style>

<!-- Floating orange emojis - reduced to 2 -->
<div class="orange-float">??</div>
<div class="orange-float">??</div>

<div class="verification-wrapper">
    <div class="col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3">
        <div class="verification-card">
            <div class="card-header">
                <h3>
                    <i class="fa fa-shield-alt"></i> 
                    {Lang::T('Secure Access')}
                </h3>
                <div class="subtitle">{Lang::T('Payment Gateway')}</div>
            </div>
            
            <div class="card-body">
                <div class="icon-circle">
                    <i class="fa fa-lock"></i>
                </div>
                
                <div class="security-badge text-center">
                    <i class="fa fa-check-circle"></i> 
                    {Lang::T('Verification Required')}
                </div>
                
                {if isset($error)}
                    <div class="error-message">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span>{$error}</span>
                    </div>
                {/if}
                
                <div class="alert-modern">
                    <i class="fa fa-info-circle"></i>
                    <div>
                        <div class="alert-title">{Lang::T('Secure Verification')}</div>
                        <div class="alert-message">{Lang::T('Enter your access code')}</div>
                    </div>
                </div>
                
                <div class="timer-badge">
                    <i class="fa fa-clock-o"></i>
                    <span>{Lang::T('3 min session')}</span>
                </div>
                
                <form method="post" action="" id="verify-form" class="verification-form">
                    <div class="input-group-modern" id="password-group">
                        <input type="password" 
                               name="pg_password" 
                               id="pg_password"
                               class="form-control-modern" 
                               required
                               autofocus>
                        <label for="pg_password">
                            <i class="fa fa-key"></i> 
                            {Lang::T('Verification Code')}
                        </label>
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="fa fa-eye"></i>
                        </span>
                    </div>
                    
                    <div class="password-strength">
                        <div class="strength-bar" id="strength-bar"></div>
                    </div>

                    <button type="submit" 
                            name="verify_pg_access" 
                            value="1"
                            class="btn-verify"
                            id="verify-btn">
                        <i class="fa fa-unlock-alt"></i>
                        <span class="btn-text">{Lang::T('Verify')}</span>
                    </button>
                </form>
            </div>
            
            <div class="card-footer">
                <div class="security-info">
                    <i class="fa fa-shield"></i>
                    <span>{Lang::T('Secure Area')}</span>
                    <i class="fa fa-lock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Focus on password input
    document.getElementById('pg_password').focus();
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Input focus effects
    var passwordInput = document.getElementById('pg_password');
    var passwordGroup = document.getElementById('password-group');
    
    passwordInput.addEventListener('focus', function() {
        passwordGroup.classList.add('focused');
    });
    
    passwordInput.addEventListener('blur', function() {
        if (!this.value) {
            passwordGroup.classList.remove('focused');
        }
    });
    
    if (passwordInput.value) {
        passwordGroup.classList.add('has-value');
    }
    
    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        var strengthBar = document.getElementById('strength-bar');
        var value = this.value;
        var strength = 0;
        
        if (value.length > 0) strength += 25;
        if (value.length >= 6) strength += 25;
        if (value.match(/[a-z]/) && value.match(/[0-9]/)) strength += 25;
        if (value.match(/[^a-zA-Z0-9]/)) strength += 25;
        
        strength = Math.min(strength, 100);
        strengthBar.style.width = strength + '%';
        
        if (strength < 40) {
            strengthBar.style.background = '#FF6B35';
        } else if (strength < 70) {
            strengthBar.style.background = '#FF8C42';
        } else {
            strengthBar.style.background = '#FFB347';
        }
    });
    
    // Form loading state
    document.getElementById('verify-form').addEventListener('submit', function(e) {
        var btn = document.getElementById('verify-btn');
        btn.classList.add('loading');
    });
    
    // Random float animation
    var oranges = document.querySelectorAll('.orange-float');
    setInterval(function() {
        for (var i = 0; i < oranges.length; i++) {
            var randomY = Math.random() * 10 - 5;
            var randomRotate = Math.random() * 10 - 5;
            oranges[i].style.transform = 'translateY(' + randomY + 'px) rotate(' + randomRotate + 'deg)';
        }
    }, 4000);
});

function togglePassword() {
    var passwordInput = document.getElementById('pg_password');
    var toggleIcon = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

{include file="sections/footer.tpl"}