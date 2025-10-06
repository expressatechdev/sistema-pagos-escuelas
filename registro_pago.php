<?php
// Incluir configuración
require_once 'includes/db_config.php';

// Iniciar sesión
session_start();

// Verificar que el participante esté autenticado
if (!isset($_SESSION['participante_id']) || !isset($_GET['escuela'])) {
    header("Location: index.php");
    exit();
}

$participante_id = $_SESSION['participante_id'];
$participante_nombre = $_SESSION['participante_nombre'];
$escuela = $_GET['escuela'];
$mensaje = '';
$mensaje_tipo = '';

// Validar escuela
if (!in_array($escuela, ['VII_INICIAL', 'III_AVANZADO'])) {
    header("Location: verificar.php");
    exit();
}

$escuela_nombre = $escuela == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';

// Conectar a base de datos
$conexion = conectarDB();

// Obtener información de la inscripción y estado de cuenta
$estado_cuenta = obtenerEstadoCuenta($conexion, $participante_id, $escuela);

// Obtener tasas de cambio actuales
$tasa_bolivares = obtenerTasaCambio($conexion, 'Bolivares') ?? 40.00;
$tasa_euro = obtenerTasaCambio($conexion, 'Euro') ?? 0.92;

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $fecha_pago = $_POST['fecha_pago'] ?? '';
    $moneda_pago = $_POST['moneda_pago'] ?? '';
    $monto_original = floatval($_POST['monto_original'] ?? 0);
    $banco_origen = $_POST['banco_origen'] ?? '';
    $referencia = $_POST['referencia'] ?? '';
    $notas = $_POST['notas'] ?? '';
    
    // Calcular monto en dólares según la moneda
    $monto_dolares = 0;
    $tasa_usada = null;
    
    switch ($moneda_pago) {
        case 'Dolar':
        case 'Zelle':
        case 'Zinli':
        case 'PayPal':
            $monto_dolares = $monto_original;
            break;
        case 'Bolivares':
            $tasa_usada = floatval($_POST['tasa_manual'] ?? $tasa_bolivares);
            $monto_dolares = $monto_original / $tasa_usada;
            break;
        case 'Euro':
            $tasa_usada = $tasa_euro;
            $monto_dolares = $monto_original / $tasa_usada;
            break;
    }

    // ✅ LÍNEAS AGREGADAS - REDONDEAR A 2 DECIMALES
    $monto_dolares = round($monto_dolares, 2);
    $monto_original = round($monto_original, 2);
    if ($tasa_usada !== null) {
        $tasa_usada = round($tasa_usada, 2);
    }
    
    // Validaciones
    $errores = [];
    
    if (empty($fecha_pago)) {
        $errores[] = "La fecha de pago es requerida";
    }
    
    if (empty($moneda_pago)) {
        $errores[] = "Debe seleccionar la moneda de pago";
    }
    
    if ($monto_original <= 0) {
        $errores[] = "El monto debe ser mayor a cero";
    }
    
    if (empty($referencia)) {
        $errores[] = "La referencia bancaria es requerida";
    }
    
    // Procesar comprobante
    $archivo_comprobante = '';
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $resultado_upload = subirComprobante($_FILES['comprobante']);
        if ($resultado_upload['exito']) {
            $archivo_comprobante = $resultado_upload['archivo'];
        } else {
            $errores[] = $resultado_upload['mensaje'];
        }
    } else {
        $errores[] = "Debe subir un comprobante de pago";
    }
    
    // Si no hay errores, guardar en base de datos
    if (empty($errores)) {
        // Determinar el módulo al que corresponde el pago
        $modulo_num = $estado_cuenta['modulo_actual'];
        
        $sql = "INSERT INTO pagos (
                    participante_id, escuela, modulo_num, monto_dolares, 
                    moneda_pago, monto_original, tasa_cambio, banco_origen,
                    referencia_bancaria, comprobante_url, fecha_pago, 
                    registrado_por, registrado_por_id, notas, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'participante', ?, ?, 'PENDIENTE')";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("isidsddssssis", 
            $participante_id, $escuela, $modulo_num, $monto_dolares,
            $moneda_pago, $monto_original, $tasa_usada, $banco_origen,
            $referencia, $archivo_comprobante, $fecha_pago,
            $participante_id, $notas
        );
        
        if ($stmt->execute()) {
            // Registrar actividad
            registrarActividad($conexion, 'participante', $participante_id, 'REGISTRO_PAGO', 
                "Pago registrado: $monto_dolares USD para $escuela_nombre");
            
            // Enviar notificación por email
            $mensaje_email = "
                <h2>Nuevo Pago Registrado</h2>
                <p><strong>Participante:</strong> $participante_nombre</p>
                <p><strong>Escuela:</strong> $escuela_nombre</p>
                <p><strong>Módulo:</strong> $modulo_num</p>
                <p><strong>Monto:</strong> " . formatearMoneda($monto_dolares) . "</p>
                <p><strong>Fecha de Pago:</strong> $fecha_pago</p>
                <p><strong>Referencia:</strong> $referencia</p>
                <p><strong>Estado:</strong> PENDIENTE DE VERIFICACIÓN</p>
                <br>
                <p>Por favor ingrese al sistema para verificar este pago.</p>
            ";
            
            enviarEmail('gtomasif@gmail.com', 'Nuevo Pago Registrado - ' . $participante_nombre, $mensaje_email);
            
            $mensaje_tipo = 'success';
            $mensaje = "¡Pago registrado exitosamente! Tu pago está pendiente de verificación. Recibirás una notificación cuando sea procesado.";
            
            // Limpiar el formulario
            $_POST = array();
        } else {
            $mensaje_tipo = 'danger';
            $mensaje = "Error al registrar el pago. Por favor intente nuevamente.";
        }
    } else {
        $mensaje_tipo = 'danger';
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
    <title>Registrar Pago - <?php echo $escuela_nombre; ?></title>
    
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 10px;
        }
        
        .container-main {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .school-badge-large {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .form-header h2 {
            color: var(--dark-color);
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .form-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        .estado-cuenta-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .estado-cuenta-box h5 {
            color: var(--dark-color);
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .estado-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .estado-item {
            background: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
        }
        
        .estado-label {
            color: #666;
            font-size: 11px;
            margin-bottom: 5px;
        }
        
        .estado-value {
            color: var(--dark-color);
            font-size: 18px;
            font-weight: 600;
        }
        
        .estado-value.money {
            color: var(--primary-color);
        }
        
        .estado-value.debt {
            color: var(--danger-color);
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            color: var(--dark-color);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
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
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 15px;
            background: var(--light-bg);
            border: 2px dashed #999;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-label:hover {
            background: #e8f5e9;
            border-color: var(--primary-color);
        }
        
        .file-upload-label i {
            font-size: 24px;
            color: #999;
            display: block;
            margin-bottom: 8px;
        }
        
        .file-upload-label span {
            color: var(--dark-color);
            font-size: 14px;
        }
        
        .file-upload-label.has-file {
            background: #e8f5e9;
            border-color: var(--primary-color);
        }
        
        .file-upload-label.has-file i {
            color: var(--primary-color);
        }
        
        .moneda-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .moneda-btn {
            padding: 10px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 13px;
        }
        
        .moneda-btn:hover {
            background: var(--light-bg);
        }
        
        .moneda-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .tasa-info {
            background: #fff3e0;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        
        .tasa-info.show {
            display: block;
        }
        
        .tasa-info p {
            margin: 0;
            color: #f57c00;
            font-size: 13px;
        }
        
        .conversion-result {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            text-align: center;
            display: none;
        }
        
        .conversion-result.show {
            display: block;
        }
        
        .conversion-result .result {
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
        }
        
        .btn-submit {
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
        
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateX(-5px);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
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
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        @media (max-width: 576px) {
            .form-card {
                padding: 20px;
            }
            
            .moneda-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .estado-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- Botón volver -->
        <a href="verificar.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver a mis escuelas
        </a>
        
        <div class="form-card">
            <!-- Header -->
            <div class="form-header">
                <span class="school-badge-large">
                    <i class="fas fa-<?php echo $escuela == 'VII_INICIAL' ? 'school' : 'university'; ?>"></i>
                    <?php echo $escuela_nombre; ?>
                </span>
                <h2>Registrar Pago</h2>
                <p>Complete el formulario con los datos de su pago</p>
            </div>
            
            <!-- Estado de Cuenta -->
            <div class="estado-cuenta-box">
                <h5><i class="fas fa-chart-line"></i> Estado de Cuenta Actual</h5>
                <div class="estado-grid">
                    <div class="estado-item">
                        <div class="estado-label">Módulo Actual</div>
                        <div class="estado-value"><?php echo $estado_cuenta['modulo_actual']; ?> de 9</div>
                    </div>
                    <div class="estado-item">
                        <div class="estado-label">Precio por Módulo</div>
                        <div class="estado-value money"><?php echo formatearMoneda($estado_cuenta['precio_modulo']); ?></div>
                    </div>
                    <div class="estado-item">
                        <div class="estado-label">Saldo a Favor</div>
                        <div class="estado-value money"><?php echo formatearMoneda($estado_cuenta['saldo_favor']); ?></div>
                    </div>
                    <div class="estado-item">
                        <div class="estado-label">Total Adeudado</div>
                        <div class="estado-value debt"><?php echo formatearMoneda($estado_cuenta['total_adeudado']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $mensaje_tipo; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <form method="POST" action="" enctype="multipart/form-data" id="pagoForm">
                <!-- Sección: Información del Pago -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-calendar-alt"></i> Información del Pago
                    </h5>
                    
                    <div class="mb-3">
                        <label for="fecha_pago" class="form-label">
                            Fecha del Pago <span class="required">*</span>
                        </label>
                        <input type="date" 
                               class="form-control" 
                               id="fecha_pago" 
                               name="fecha_pago" 
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                        <div class="help-text">Fecha en que realizó el pago</div>
                    </div>
                </div>
                
                <!-- Sección: Detalles del Pago -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-money-bill-wave"></i> Detalles del Pago
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Moneda/Método de Pago <span class="required">*</span>
                        </label>
                        <div class="moneda-buttons">
                            <div class="moneda-btn" data-moneda="Bolivares">
                                <i class="fas fa-money-bill"></i><br>Bolívares
                            </div>
                            <div class="moneda-btn" data-moneda="Dolar">
                                <i class="fas fa-dollar-sign"></i><br>Dólares
                            </div>
                            <div class="moneda-btn" data-moneda="Euro">
                                <i class="fas fa-euro-sign"></i><br>Euros
                            </div>
                            <div class="moneda-btn" data-moneda="Zelle">
                                <i class="fas fa-mobile-alt"></i><br>Zelle
                            </div>
                            <div class="moneda-btn" data-moneda="PayPal">
                                <i class="fab fa-paypal"></i><br>PayPal
                            </div>
                            <div class="moneda-btn" data-moneda="Zinli">
                                <i class="fas fa-credit-card"></i><br>Zinli
                            </div>
                        </div>
                        <input type="hidden" id="moneda_pago" name="moneda_pago" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="monto_original" class="form-label">
                            Monto Pagado <span class="required">*</span>
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="monto_original" 
                               name="monto_original" 
                               step="0.01" 
                               min="0.01"
                               placeholder="0.00"
                               required>
                        <div class="help-text">Ingrese el monto en la moneda seleccionada</div>
                    </div>
                    
                    <!-- Info de tasa para Bolívares -->
                    <div class="tasa-info" id="tasaBolivares">
                        <p><i class="fas fa-info-circle"></i> Tasa actual: 1 USD = <?php echo number_format($tasa_bolivares, 2); ?> Bs</p>
                        <div class="mb-2 mt-2">
                            <label for="tasa_manual" class="form-label" style="font-size: 13px;">
                                Si pagó con otra tasa, indíquela aquí:
                            </label>
                            <input type="number" 
                                   class="form-control form-control-sm" 
                                   id="tasa_manual" 
                                   name="tasa_manual" 
                                   step="0.01" 
                                   min="0.01"
                                   value="<?php echo $tasa_bolivares; ?>">
                        </div>
                    </div>
                    
                    <!-- Info de tasa para Euros -->
                    <div class="tasa-info" id="tasaEuro">
                        <p><i class="fas fa-info-circle"></i> Tasa actual: 1 USD = <?php echo number_format($tasa_euro, 2); ?> EUR</p>
                    </div>
                    
                    <!-- Resultado de conversión -->
                    <div class="conversion-result" id="conversionResult">
                        <p style="margin: 0; font-size: 13px; color: #666;">Equivalente en dólares:</p>
                        <div class="result">$0.00 USD</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="banco_origen" class="form-label">
                                Banco/Plataforma
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="banco_origen" 
                                   name="banco_origen"
                                   placeholder="Ej: Banesco, Bank of America">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="referencia" class="form-label">
                                Referencia/Confirmación <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="referencia" 
                                   name="referencia"
                                   placeholder="Número de referencia"
                                   required>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Comprobante -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-file-upload"></i> Comprobante de Pago
                    </h5>
                    
                    <div class="mb-3">
                        <div class="file-upload-wrapper">
                            <input type="file" 
                                   class="file-upload-input" 
                                   id="comprobante" 
                                   name="comprobante"
                                   accept="image/*,.pdf"
                                   required>
                            <label for="comprobante" class="file-upload-label" id="fileLabel">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click aquí para subir comprobante</span>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    JPG, PNG, GIF o PDF (Máx. 5MB)
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Notas -->
                <div class="form-section">
                    <h5 class="section-title">
                        <i class="fas fa-comment-alt"></i> Notas Adicionales
                    </h5>
                    
                    <div class="mb-3">
                        <textarea class="form-control" 
                                  id="notas" 
                                  name="notas" 
                                  rows="3"
                                  placeholder="Información adicional sobre el pago (opcional)"></textarea>
                    </div>
                </div>
                
                <!-- Botón Submit -->
                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fas fa-paper-plane"></i> Registrar Pago
                </button>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Manejo de selección de moneda
        const monedaBtns = document.querySelectorAll('.moneda-btn');
        const monedaInput = document.getElementById('moneda_pago');
        const montoInput = document.getElementById('monto_original');
        const tasaBolivaresDiv = document.getElementById('tasaBolivares');
        const tasaEuroDiv = document.getElementById('tasaEuro');
        const conversionDiv = document.getElementById('conversionResult');
        const conversionResult = conversionDiv.querySelector('.result');
        const tasaManualInput = document.getElementById('tasa_manual');
        
        monedaBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remover active de todos
                monedaBtns.forEach(b => b.classList.remove('active'));
                // Agregar active al clickeado
                this.classList.add('active');
                // Guardar valor
                const moneda = this.dataset.moneda;
                monedaInput.value = moneda;
                
                // Mostrar/ocultar info de tasas
                tasaBolivaresDiv.classList.toggle('show', moneda === 'Bolivares');
                tasaEuroDiv.classList.toggle('show', moneda === 'Euro');
                
                // Calcular conversión
                calcularConversion();
            });
        });
        
        // Calcular conversión cuando cambia el monto o la tasa
        montoInput.addEventListener('input', calcularConversion);
        tasaManualInput?.addEventListener('input', calcularConversion);
        
        function calcularConversion() {
            const moneda = monedaInput.value;
            const monto = parseFloat(montoInput.value) || 0;
            let montoDolares = 0;
            
            if (monto > 0) {
                switch(moneda) {
                    case 'Bolivares':
                        const tasa = parseFloat(tasaManualInput?.value) || <?php echo $tasa_bolivares; ?>;
                        montoDolares = monto / tasa;
                        break;
                    case 'Euro':
                        montoDolares = monto / <?php echo $tasa_euro; ?>;
                        break;
                    case 'Dolar':
                    case 'Zelle':
                    case 'PayPal':
                    case 'Zinli':
                        montoDolares = monto;
                        break;
                }
                
                if (montoDolares > 0) {
                    conversionResult.textContent = '$' + montoDolares.toFixed(2) + ' USD';
                    conversionDiv.classList.add('show');
                } else {
                    conversionDiv.classList.remove('show');
                }
            } else {
                conversionDiv.classList.remove('show');
            }
        }
        
        // Manejo de archivo
        const fileInput = document.getElementById('comprobante');
        const fileLabel = document.getElementById('fileLabel');
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                
                fileLabel.classList.add('has-file');
                fileLabel.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <span>${fileName}</span>
                    <div style="font-size: 11px; color: #4CAF50; margin-top: 5px;">
                        Archivo seleccionado (${fileSize} MB)
                    </div>
                `;
            }
        });
        
        // Validación del formulario
        document.getElementById('pagoForm').addEventListener('submit', function(e) {
            const moneda = monedaInput.value;
            const monto = montoInput.value;
            const fecha = document.getElementById('fecha_pago').value;
            const referencia = document.getElementById('referencia').value;
            const comprobante = fileInput.files[0];
            
            let errores = [];
            
            if (!fecha) errores.push('Seleccione la fecha del pago');
            if (!moneda) errores.push('Seleccione la moneda de pago');
            if (!monto || parseFloat(monto) <= 0) errores.push('Ingrese el monto pagado');
            if (!referencia) errores.push('Ingrese la referencia del pago');
            if (!comprobante) errores.push('Suba el comprobante de pago');
            
            if (errores.length > 0) {
                e.preventDefault();
                alert('Por favor complete los siguientes campos:\n\n' + errores.join('\n'));
                return false;
            }
            
            // Deshabilitar botón para evitar doble envío
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
        });
        
        // Establecer fecha máxima como hoy
        document.getElementById('fecha_pago').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>