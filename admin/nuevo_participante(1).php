<?php
session_start();
require_once '../includes/db_config.php';

// Verificar autenticación y rol de admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conexion = conectarDB();
$mensaje = '';
$tipo_mensaje = '';

// Obtener lista de productoras activas
$sql_productoras = "SELECT id, nombre, email FROM productoras WHERE activa = 1 ORDER BY nombre";
$resultado_productoras = $conexion->query($sql_productoras);
$productoras = [];
while ($row = $resultado_productoras->fetch_assoc()) {
    $productoras[] = $row;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos
    $nombre = limpiarDato($_POST['nombre']);
    $apellido = limpiarDato($_POST['apellido']);
    $email = limpiarDato($_POST['email']);
    $whatsapp = limpiarDato($_POST['whatsapp'] ?? '');
    $pais = limpiarDato($_POST['pais']);
    $ciudad = limpiarDato($_POST['ciudad']);
    $voluntad = $_POST['voluntad'] ?? 'Por Asignar';
    $productora_id = intval($_POST['productora_id'] ?? 0);
    $escuelas = $_POST['escuelas'] ?? [];
    $precio_inicial = floatval($_POST['precio_inicial'] ?? 100);
    $precio_avanzado = floatval($_POST['precio_avanzado'] ?? 150);
    $enviar_credenciales = isset($_POST['enviar_credenciales']);
    
    // Validaciones
    $errores = [];
    
    if (empty($nombre) || empty($apellido)) {
        $errores[] = "El nombre y apellido son requeridos";
    }
    
    if (!validarEmail($email)) {
        $errores[] = "El email no es válido";
    }
    
    // Verificar si el email ya existe
    $sql_check = "SELECT id FROM participantes WHERE email = ?";
    $stmt_check = $conexion->prepare($sql_check);
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    
    if ($resultado_check->num_rows > 0) {
        $errores[] = "Este email ya está registrado";
    }
    
    if (empty($escuelas)) {
        $errores[] = "Debe inscribir al participante en al menos una escuela";
    }
    
    // Si no hay errores, insertar
    if (empty($errores)) {
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Generar token único para acceso futuro
            $token_acceso = generarToken();
            
            // Insertar participante
            $sql = "INSERT INTO participantes (
                    nombre, apellido, email, whatsapp, pais, ciudad, 
                    voluntad, productora_id, token_acceso, activo, fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ssssssssi", 
                $nombre, $apellido, $email, $whatsapp, $pais, 
                $ciudad, $voluntad, $productora_id, $token_acceso
            );
            $stmt->execute();
            
            $participante_id = $conexion->insert_id;
            
            // Insertar inscripciones y estado de cuenta para cada escuela
            foreach ($escuelas as $escuela) {
                $precio = ($escuela == 'VII_INICIAL') ? $precio_inicial : $precio_avanzado;
                
                // Insertar inscripción
                $sql_inscripcion = "INSERT INTO inscripciones (
                    participante_id, escuela, precio_modulo, fecha_inscripcion, activa
                ) VALUES (?, ?, ?, NOW(), 1)";
                
                $stmt_inscripcion = $conexion->prepare($sql_inscripcion);
                $stmt_inscripcion->bind_param("isd", $participante_id, $escuela, $precio);
                $stmt_inscripcion->execute();
                
                // Crear estado de cuenta inicial
                $sql_estado = "INSERT INTO estado_cuenta (
                    participante_id, escuela, modulo_actual, 
                    saldo_favor, total_pagado, total_adeudado
                ) VALUES (?, ?, 1, 0, 0, ?)";
                
                $stmt_estado = $conexion->prepare($sql_estado);
                $stmt_estado->bind_param("isd", $participante_id, $escuela, $precio);
                $stmt_estado->execute();
            }
            
            // Registrar actividad
            registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 
                'CREAR_PARTICIPANTE', "Participante creado: $nombre $apellido ($email)");
            
            // Enviar email de bienvenida si se marcó la opción
            if ($enviar_credenciales && file_exists('../includes/email_config.php')) {
                require_once '../includes/email_config.php';
                
                $escuelas_nombres = [];
                foreach ($escuelas as $escuela) {
                    $escuelas_nombres[] = $escuela == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                }
                $escuelas_texto = implode(' y ', $escuelas_nombres);
                
                $productora_nombre = '';
                foreach ($productoras as $prod) {
                    if ($prod['id'] == $productora_id) {
                        $productora_nombre = $prod['nombre'];
                        break;
                    }
                }
                
                $mensaje_email = "
                    <h2 style='color: #4CAF50;'>¡Bienvenido a la Escuela del Sanador!</h2>
                    <p>Hola <strong>$nombre</strong>,</p>
                    <p>Has sido registrado exitosamente en nuestro sistema de pagos.</p>
                    
                    <div style='background: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3 style='color: #333; margin-top: 0;'>Información de tu registro:</h3>
                        <p><strong>Escuela(s):</strong> $escuelas_texto</p>
                        <p><strong>Productora/Guía:</strong> $productora_nombre</p>
                        <p><strong>Email registrado:</strong> $email</p>
                    </div>
                    
                    <div style='background: #e8f5e9; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                        <h3 style='color: #333; margin-top: 0;'>Para registrar tus pagos:</h3>
                        <ol style='margin: 10px 0;'>
                            <li>Ingresa a: <a href='https://pagos.bividelosangeles.com'>pagos.bividelosangeles.com</a></li>
                            <li>Usa tu email: <strong>$email</strong></li>
                            <li>Recibirás un código de verificación en tu email</li>
                            <li>Ingresa el código y podrás registrar tu pago</li>
                        </ol>
                    </div>
                    
                    <div style='background: #fff3e0; padding: 15px; border-radius: 10px; margin: 20px 0;'>
                        <p><strong>Precio por módulo:</strong></p>
                        <ul>";
                
                foreach ($escuelas as $escuela) {
                    $precio = ($escuela == 'VII_INICIAL') ? $precio_inicial : $precio_avanzado;
                    $nombre_esc = ($escuela == 'VII_INICIAL') ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                    $mensaje_email .= "<li>$nombre_esc: " . formatearMoneda($precio) . "</li>";
                }
                
                $mensaje_email .= "
                        </ul>
                    </div>
                    
                    <p>Si tienes alguna pregunta, contacta a tu productora: <strong>$productora_nombre</strong></p>
                    
                    <p>¡Te deseamos mucho éxito en tu formación!</p>
                ";
                
                enviarEmailPHPMailer(
                    $email,
                    'Bienvenido a la Escuela del Sanador - Registro Exitoso',
                    $mensaje_email
                );
                
                // Notificar a la productora también
                if ($productora_id > 0) {
                    foreach ($productoras as $prod) {
                        if ($prod['id'] == $productora_id && !empty($prod['email'])) {
                            $mensaje_prod = "
                                <h2>Nuevo Participante Asignado</h2>
                                <p>Se te ha asignado un nuevo participante:</p>
                                <ul>
                                    <li><strong>Nombre:</strong> $nombre $apellido</li>
                                    <li><strong>Email:</strong> $email</li>
                                    <li><strong>WhatsApp:</strong> $whatsapp</li>
                                    <li><strong>Escuela(s):</strong> $escuelas_texto</li>
                                </ul>
                            ";
                            
                            enviarEmailPHPMailer(
                                $prod['email'],
                                'Nuevo Participante Asignado - ' . $nombre . ' ' . $apellido,
                                $mensaje_prod
                            );
                            break;
                        }
                    }
                }
            }
            
            $conexion->commit();
            
            $tipo_mensaje = 'success';
            $mensaje = "¡Participante registrado exitosamente!";
            
            if ($enviar_credenciales) {
                $mensaje .= " Se ha enviado un email de bienvenida a $email";
            }
            
            // Limpiar el formulario
            $_POST = array();
            
        } catch (Exception $e) {
            $conexion->rollback();
            $tipo_mensaje = 'danger';
            $mensaje = "Error al registrar el participante. Por favor intente nuevamente.";
        }
    } else {
        $tipo_mensaje = 'danger';
        $mensaje = "Por favor corrija los siguientes errores:<br>" . implode("<br>", $errores);
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Participante - Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --dark-color: #2c3e50;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 20px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            text-align: center;
            color: white;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            font-size: 20px;
            margin: 10px 0;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.3);
        }
        
        .sidebar-menu i {
            font-size: 18px;
            width: 30px;
            text-align: center;
        }
        
        .sidebar-menu span {
            margin-left: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .form-header h2 {
            color: var(--dark-color);
            margin: 0;
            font-size: 24px;
        }
        
        .form-header p {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--dark-color);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .form-label {
            color: var(--dark-color);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-label .required {
            color: var(--danger-color);
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .form-check {
            padding: 10px;
            background: var(--light-bg);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .price-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-input-group .form-control {
            flex: 1;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-cancel {
            background: white;
            color: var(--dark-color);
            padding: 12px 30px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: var(--light-bg);
            color: var(--dark-color);
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap" style="font-size: 40px;"></i>
            <h3>Admin Panel</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_admin.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="pagos_pendientes.php">
                    <i class="fas fa-clock"></i>
                    <span>Pagos Pendientes</span>
                </a>
            </li>
            <li>
                <a href="participantes.php" class="active">
                    <i class="fas fa-users"></i>
                    <span>Participantes</span>
                </a>
            </li>
            <li>
                <a href="productoras.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Productoras</span>
                </a>
            </li>
            <li>
                <a href="reportes.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li>
                <a href="tasas.php">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Tasas de Cambio</span>
                </a>
            </li>
            <li>
                <a href="logout.php" style="margin-top: 30px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-user-plus"></i> Registrar Nuevo Participante</h2>
                <p>Complete el formulario para agregar un nuevo participante al sistema</p>
            </div>
            
            <?php if($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Información Personal -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-user"></i> Información Personal
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">
                                Nombre <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nombre" 
                                   name="nombre" 
                                   required
                                   value="<?php echo $_POST['nombre'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">
                                Apellido <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="apellido" 
                                   name="apellido" 
                                   required
                                   value="<?php echo $_POST['apellido'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   required
                                   value="<?php echo $_POST['email'] ?? ''; ?>">
                            <small class="text-muted">Este email se usará para acceder al sistema de pagos</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp" class="form-label">
                                WhatsApp
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="whatsapp" 
                                   name="whatsapp" 
                                   placeholder="+58 412 123 4567"
                                   value="<?php echo $_POST['whatsapp'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pais" class="form-label">
                                País <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="pais" 
                                   name="pais" 
                                   required
                                   value="<?php echo $_POST['pais'] ?? 'Venezuela'; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="ciudad" class="form-label">
                                Ciudad <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="ciudad" 
                                   name="ciudad" 
                                   required
                                   value="<?php echo $_POST['ciudad'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="voluntad" class="form-label">
                                Voluntad
                            </label>
                            <select class="form-select" id="voluntad" name="voluntad">
                                <option value="Por Asignar">Por Asignar</option>
                                <option value="Una Voluntad">Una Voluntad</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="productora_id" class="form-label">
                                Productora/Guía <span class="required">*</span>
                            </label>
                            <select class="form-select" id="productora_id" name="productora_id" required>
                                <option value="">Seleccione una productora...</option>
                                <?php foreach($productoras as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>">
                                        <?php echo htmlspecialchars($prod['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Inscripción a Escuelas -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-school"></i> Inscripción a Escuelas
                    </h5>
                    
                    <p class="text-muted mb-3">Seleccione las escuelas donde se inscribirá el participante:</p>
                    
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="escuelas[]" 
                               value="VII_INICIAL" 
                               id="escuela_inicial"
                               onchange="togglePriceInput('inicial')">
                        <label class="form-check-label" for="escuela_inicial">
                            <strong>VII Escuela INICIAL</strong>
                            <div class="price-input-group mt-2" id="precio_inicial_group" style="display:none;">
                                <span>Precio por módulo: $</span>
                                <input type="number" 
                                       class="form-control form-control-sm" 
                                       name="precio_inicial" 
                                       value="100" 
                                       step="0.01" 
                                       min="0">
                                <span>USD</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="escuelas[]" 
                               value="III_AVANZADO" 
                               id="escuela_avanzado"
                               onchange="togglePriceInput('avanzado')">
                        <label class="form-check-label" for="escuela_avanzado">
                            <strong>III Escuela AVANZADO</strong>
                            <div class="price-input-group mt-2" id="precio_avanzado_group" style="display:none;">
                                <span>Precio por módulo: $</span>
                                <input type="number" 
                                       class="form-control form-control-sm" 
                                       name="precio_avanzado" 
                                       value="150" 
                                       step="0.01" 
                                       min="0">
                                <span>USD</span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Opciones de Notificación -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-envelope"></i> Notificaciones
                    </h5>
                    
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="enviar_credenciales" 
                               name="enviar_credenciales"
                               checked>
                        <label class="form-check-label" for="enviar_credenciales">
                            <strong>Enviar email de bienvenida al participante</strong>
                            <br>
                            <small class="text-muted">Se enviará un email con las instrucciones de acceso al sistema</small>
                        </label>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="text-end">
                    <a href="participantes.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Registrar Participante
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePriceInput(escuela) {
            const checkbox = document.getElementById('escuela_' + escuela);
            const priceGroup = document.getElementById('precio_' + escuela + '_group');
            
            if (checkbox.checked) {
                priceGroup.style.display = 'flex';
            } else {
                priceGroup.style.display = 'none';
            }
        }
    </script>
</body>
</html>