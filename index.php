<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Pagos - Escuela del Sanador</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --danger-color: #f44336;
            --dark-color: #2c3e50;
            --light-bg: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container-main {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header-section h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .header-section p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        
        .logo-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .form-section {
            padding: 40px 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            margin: 0 30px;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 2px;
            background: #e0e0e0;
            left: 40px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.completed {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed::after {
            background: var(--primary-color);
        }
        
        .form-label {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
            outline: none;
        }
        
        .btn-primary-custom {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-primary-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .info-text {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 20px;
        }
        
        .info-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
            font-size: 14px;
        }
        
        .verification-section {
            display: none;
        }
        
        .verification-section.active {
            display: block;
        }
        
        .token-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .token-input {
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .token-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .timer {
            text-align: center;
            color: var(--danger-color);
            font-weight: 600;
            margin: 15px 0;
        }
        
        .resend-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .resend-link button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            text-decoration: underline;
        }
        
        .resend-link button:disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 576px) {
            .header-section {
                padding: 30px 20px;
            }
            
            .header-section h1 {
                font-size: 24px;
            }
            
            .form-section {
                padding: 30px 20px;
            }
            
            .token-input {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- Header -->
        <div class="header-section">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>Escuela del Sanador</h1>
            <p>Sistema de Registro de Pagos</p>
        </div>
        
        <!-- Form Section -->
        <div class="form-section">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">1</div>
                <div class="step" id="step2">2</div>
                <div class="step" id="step3">3</div>
            </div>
            
            <!-- Email Section -->
            <div id="emailSection" class="verification-section active">
                <h4 class="text-center mb-4">Ingresa tu Email</h4>
                
                <div id="emailAlert"></div>
                
                <form id="emailForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Correo Electrónico
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               placeholder="tu-email@ejemplo.com"
                               required>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom" id="btnEnviarEmail">
                        Continuar
                        <span class="spinner" id="emailSpinner" style="display: none;"></span>
                    </button>
                </form>
                
                <div class="info-text">
                    <p>Ingresa el email registrado en la escuela</p>
                    <p>Te enviaremos un código de verificación</p>
                </div>
            </div>
            
            <!-- Token Section -->
            <div id="tokenSection" class="verification-section">
                <h4 class="text-center mb-3">Código de Verificación</h4>
                <p class="text-center text-muted">Enviamos un código de 4 dígitos a:</p>
                <p class="text-center font-weight-bold" id="emailDisplay"></p>
                
                <div id="tokenAlert"></div>
                
                <form id="tokenForm">
                    <div class="token-inputs">
                        <input type="text" class="token-input" maxlength="1" id="token1" required>
                        <input type="text" class="token-input" maxlength="1" id="token2" required>
                        <input type="text" class="token-input" maxlength="1" id="token3" required>
                        <input type="text" class="token-input" maxlength="1" id="token4" required>
                    </div>
                    
                    <div class="timer" id="timer">
                        <i class="fas fa-clock"></i> Tiempo restante: <span id="timeLeft">5:00</span>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom" id="btnVerificar">
                        Verificar Código
                        <span class="spinner" id="tokenSpinner" style="display: none;"></span>
                    </button>
                </form>
                
                <div class="resend-link">
                    <button id="btnReenviar" disabled>
                        <i class="fas fa-redo"></i> Reenviar código
                    </button>
                </div>
                
                <div class="info-text">
                    <a href="#" id="btnCambiarEmail">
                        <i class="fas fa-arrow-left"></i> Cambiar email
                    </a>
                </div>
            </div>
            
            <!-- Success Section -->
            <div id="successSection" class="verification-section">
                <div class="text-center">
                    <i class="fas fa-check-circle" style="font-size: 60px; color: var(--primary-color); margin-bottom: 20px;"></i>
                    <h4>¡Verificación Exitosa!</h4>
                    <p class="text-muted">Redirigiendo a tu panel...</p>
                    <div class="spinner" style="margin: 20px auto;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let timerInterval;
        let timeRemaining = 300;
        let intentos = 0;
        const maxIntentos = 3;
        
        // Auto-avance en inputs de token
        document.querySelectorAll('.token-input').forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < 3) {
                    document.getElementById(`token${index + 2}`).focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    document.getElementById(`token${index}`).focus();
                }
            });
        });
        
        // Enviar email
        document.getElementById('emailForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const spinner = document.getElementById('emailSpinner');
            const btn = document.getElementById('btnEnviarEmail');
            const alertDiv = document.getElementById('emailAlert');
            
            spinner.style.display = 'inline-block';
            btn.disabled = true;
            
            try {
                const response = await fetch('api/enviar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('emailSection').classList.remove('active');
                    document.getElementById('tokenSection').classList.add('active');
                    document.getElementById('emailDisplay').textContent = email;
                    document.getElementById('step1').classList.add('completed');
                    document.getElementById('step2').classList.add('active');
                    startTimer();
                    document.getElementById('token1').focus();
                } else {
                    alertDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            } catch (error) {
                alertDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> Error de conexión. Intente nuevamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            } finally {
                spinner.style.display = 'none';
                btn.disabled = false;
            }
        });
        
        // Verificar token
        document.getElementById('tokenForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const token = document.getElementById('token1').value +
                         document.getElementById('token2').value +
                         document.getElementById('token3').value +
                         document.getElementById('token4').value;
            
            const spinner = document.getElementById('tokenSpinner');
            const btn = document.getElementById('btnVerificar');
            const alertDiv = document.getElementById('tokenAlert');
            
            if (token.length !== 4) {
                alertDiv.innerHTML = `
                    <div class="alert alert-warning">Por favor ingrese los 4 dígitos</div>
                `;
                return;
            }
            
            spinner.style.display = 'inline-block';
            btn.disabled = true;
            intentos++;
            
            try {
                const response = await fetch('api/verificar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        token: token,
                        email: document.getElementById('email').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('tokenSection').classList.remove('active');
                    document.getElementById('successSection').classList.add('active');
                    document.getElementById('step2').classList.add('completed');
                    document.getElementById('step3').classList.add('active');
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'verificar.php';
                    }, 2000);
                } else {
                    if (intentos >= maxIntentos) {
                        alertDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i> Demasiados intentos fallidos. 
                                Por favor, solicite un nuevo código.
                            </div>
                        `;
                        btn.disabled = true;
                    } else {
                        alertDiv.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-times-circle"></i> ${data.message}. 
                                Intentos restantes: ${maxIntentos - intentos}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        document.querySelectorAll('.token-input').forEach(input => input.value = '');
                        document.getElementById('token1').focus();
                    }
                }
            } catch (error) {
                alertDiv.innerHTML = `
                    <div class="alert alert-danger">Error de conexión. Intente nuevamente.</div>
                `;
            } finally {
                spinner.style.display = 'none';
                if (intentos < maxIntentos) {
                    btn.disabled = false;
                }
            }
        });
        
        // Timer
        function startTimer() {
            timerInterval = setInterval(() => {
                timeRemaining--;
                
                const minutes = Math.floor(timeRemaining / 60);
                const seconds = timeRemaining % 60;
                
                document.getElementById('timeLeft').textContent = 
                    `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('timer').innerHTML = 
                        '<i class="fas fa-times-circle"></i> Código expirado';
                    document.getElementById('btnVerificar').disabled = true;
                    document.getElementById('btnReenviar').disabled = false;
                }
            }, 1000);
        }
        
        // Reenviar código
        document.getElementById('btnReenviar').addEventListener('click', async () => {
            const btn = document.getElementById('btnReenviar');
            btn.disabled = true;
            
            clearInterval(timerInterval);
            timeRemaining = 300;
            intentos = 0;
            
            document.querySelectorAll('.token-input').forEach(input => input.value = '');
            
            const email = document.getElementById('email').value;
            
            try {
                const response = await fetch('api/enviar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, reenviar: true })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('tokenAlert').innerHTML = `
                        <div class="alert alert-success">Nuevo código enviado</div>
                    `;
                    startTimer();
                    document.getElementById('btnVerificar').disabled = false;
                    document.getElementById('token1').focus();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
        
        // Cambiar email
        document.getElementById('btnCambiarEmail').addEventListener('click', (e) => {
            e.preventDefault();
            
            clearInterval(timerInterval);
            document.getElementById('emailSection').classList.add('active');
            document.getElementById('tokenSection').classList.remove('active');
            document.getElementById('step1').classList.remove('completed');
            document.getElementById('step2').classList.remove('active');
            
            document.getElementById('email').value = '';
            document.querySelectorAll('.token-input').forEach(input => input.value = '');
            
            timeRemaining = 300;
            intentos = 0;
        });
    </script>
</body>
</html>