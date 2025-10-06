<?php
session_start();
require_once '../includes/db_config.php';

// Si ya está autenticado, redirigir
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['tipo_usuario'] === 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: ../productoras/dashboard_productora.php");
    }
    exit();
}

$mensaje = '';
$tipo_alerta = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    $conexion = conectarDB();
    
    // Primero intentar como admin
    $sql = "SELECT id, nombre, email, password FROM admins WHERE email = ? AND activo = 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        if (password_verify($password, $usuario['password'])) {
            // Login exitoso como admin
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['tipo_usuario'] = 'admin';
            
            // Actualizar último acceso
            $sql_update = "UPDATE admins SET ultimo_acceso = NOW() WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("i", $usuario['id']);
            $stmt_update->execute();
            
            // Registrar actividad
            registrarActividad($conexion, 'admin', $usuario['id'], 'LOGIN', 'Inicio de sesión exitoso');
            
            header("Location: dashboard_admin.php");
            exit();
        } else {
            $mensaje = "Contraseña incorrecta";
            $tipo_alerta = "danger";
        }
    } else {
        // Intentar como productora
        $sql = "SELECT id, nombre, email, password FROM productoras WHERE email = ? AND activa = 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows > 0) {
            $usuario = $resultado->fetch_assoc();
            if (password_verify($password, $usuario['password'])) {
                // Login exitoso como productora
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['tipo_usuario'] = 'productora';
                
                // Registrar actividad
                registrarActividad($conexion, 'productora', $usuario['id'], 'LOGIN', 'Inicio de sesión exitoso');
                
                header("Location: ../productoras/dashboard_productora.php");
                exit();
            } else {
                $mensaje = "Contraseña incorrecta";
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Email no registrado o cuenta inactiva";
            $tipo_alerta = "danger";
        }
    }
    
    $conexion->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Sistema de Pagos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
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
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 450px;
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
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.95;
            margin: 0;
        }
        
        .logo-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-label {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 10;
        }
        
        .form-control.with-icon {
            padding-left: 45px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            color: #999;
            font-size: 13px;
            position: relative;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--secondary-color);
            transform: translateX(-3px);
        }
        
        .info-box {
            background: var(--light-bg);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        
        .info-box i {
            color: var(--primary-color);
            margin-right: 8px;
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 576px) {
            .login-container {
                margin: 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Acceso al Sistema</h1>
            <p>Panel de Administración y Productoras</p>
        </div>
        
        <!-- Body -->
        <div class="login-body">
            <?php if($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Correo Electrónico
                    </label>
                    <div class="input-group">
                        <i class="fas fa-at input-group-icon"></i>
                        <input type="email" 
                               class="form-control with-icon" 
                               id="email" 
                               name="email" 
                               placeholder="tu-email@ejemplo.com"
                               required 
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-group">
                        <i class="fas fa-key input-group-icon"></i>
                        <input type="password" 
                               class="form-control with-icon" 
                               id="password" 
                               name="password" 
                               placeholder="Tu contraseña"
                               required 
                               autocomplete="current-password">
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" id="btnSubmit">
                    Iniciar Sesión
                    <span class="spinner" id="spinner"></span>
                </button>
            </form>
            
            <div class="divider">
                <span>o</span>
            </div>
            
            <div class="text-center">
                <a href="../index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al Portal de Pagos
                </a>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>¿No puedes acceder?</strong><br>
                Contacta al administrador: <a href="mailto:gtomasif@gmail.com">gtomasif@gmail.com</a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btnSubmit = document.getElementById('btnSubmit');
            const spinner = document.getElementById('spinner');
            
            btnSubmit.disabled = true;
            spinner.style.display = 'inline-block';
        });
        
        // Auto-focus
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>