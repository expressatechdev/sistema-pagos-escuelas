<?php
/**
 * Panel del Participante - Vista de Escuelas y Estado de Cuenta
 * Sistema de Pagos - Escuela del Sanador
 */

session_start();
require_once 'includes/db_config.php';

// Verificar autenticación
if (!isset($_SESSION['participante_autenticado']) || !$_SESSION['participante_autenticado']) {
    header("Location: index.php");
    exit();
}

// Verificar tiempo de sesión (30 minutos máximo)
if (isset($_SESSION['token_verificado_tiempo'])) {
    $tiempo_transcurrido = time() - $_SESSION['token_verificado_tiempo'];
    if ($tiempo_transcurrido > 1800) { // 30 minutos
        session_destroy();
        header("Location: index.php?msg=sesion_expirada");
        exit();
    }
}

$participante_id = $_SESSION['participante_id'];
$participante_nombre = $_SESSION['participante_nombre'];
$participante_email = $_SESSION['participante_email'];

$mensaje = '';
$inscripciones = [];

// Conectar a base de datos
$conexion = conectarDB();

// Actualizar último acceso
$sql = "UPDATE participantes SET ultimo_acceso = NOW() WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $participante_id);
$stmt->execute();

// Obtener información completa del participante
$sql = "SELECT p.*, prod.nombre as productora_nombre 
        FROM participantes p 
        LEFT JOIN productoras prod ON p.productora_id = prod.id 
        WHERE p.id = ? AND p.activo = 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $participante_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $participante = $resultado->fetch_assoc();
    
    // Buscar inscripciones activas
    $sql_inscripciones = "SELECT i.*, 
                          (SELECT COUNT(*) FROM pagos WHERE participante_id = i.participante_id 
                           AND escuela = i.escuela AND estado = 'PENDIENTE') as pagos_pendientes,
                          (SELECT modulo_actual FROM estado_cuenta WHERE participante_id = i.participante_id 
                           AND escuela = i.escuela) as modulo_actual
                          FROM inscripciones i 
                          WHERE i.participante_id = ? AND i.activa = 1";
    
    $stmt_inscripciones = $conexion->prepare($sql_inscripciones);
    $stmt_inscripciones->bind_param("i", $participante_id);
    $stmt_inscripciones->execute();
    $resultado_inscripciones = $stmt_inscripciones->get_result();
    
    while ($row = $resultado_inscripciones->fetch_assoc()) {
        // Obtener estado de cuenta COMPLETO con detalle por módulos
        $estado_completo = obtenerEstadoCuentaCompleto($conexion, $participante_id, $row['escuela']);
        $row['estado_cuenta'] = $estado_completo['resumen'];
        $row['modulos_detalle'] = $estado_completo['modulos']; // NUEVO
        $inscripciones[] = $row;
    }
    
    if (empty($inscripciones)) {
        $mensaje = '<div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    No tienes inscripciones activas en ninguna escuela. Por favor contacta a tu productora.
                    </div>';
    }
} else {
    session_destroy();
    header("Location: index.php?msg=cuenta_inactiva");
    exit();
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - Escuela del Sanador</title>
    
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
            padding: 20px;
        }
        
        .container-main {
            max-width: 900px;
            margin: 0 auto;
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
        
        .header-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            font-weight: bold;
        }
        
        .user-details h2 {
            margin: 0;
            color: var(--dark-color);
            font-size: 24px;
        }
        
        .user-details p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .badge-info {
            background: var(--light-bg);
            color: var(--dark-color);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-right: 10px;
        }
        
        .logout-btn {
            background: var(--danger-color);
            color: white;
            padding: 8px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: #d32f2f;
            color: white;
            transform: translateY(-2px);
        }
        
        .school-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .school-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .school-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .school-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .school-name h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 20px;
        }
        
        .school-name p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .school-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-box {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 10px;
        }
        
        .detail-label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: var(--dark-color);
            font-size: 18px;
            font-weight: 600;
        }
        
        .detail-value.money {
            color: var(--primary-color);
        }
        
        .detail-value.debt {
            color: var(--danger-color);
        }
        
        .school-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            flex-grow: 1;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
            color: white;
        }
        
        .btn-history {
            background: white;
            color: var(--dark-color);
            border: 2px solid #e0e0e0;
        }
        
        .btn-history:hover {
            background: var(--light-bg);
            color: var(--dark-color);
        }
        
        .session-timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 12px;
            z-index: 1000;
        }
        
        .progress-bar-custom {
            background: #e0e0e0;
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .school-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .school-details {
                grid-template-columns: 1fr;
            }
            
            .session-timer {
                position: relative;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Timer de sesión -->
    <div class="session-timer">
        <i class="fas fa-clock"></i> Sesión activa: <span id="sessionTime">30:00</span>
    </div>
    
    <div class="container-main">
        <!-- Header con info del participante -->
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($participante['nombre'], 0, 1) . substr($participante['apellido'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($participante_nombre); ?></h2>
                        <p>
                            <span class="badge-info">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($participante_email); ?>
                            </span>
                            <?php if ($participante['whatsapp']): ?>
                            <span class="badge-info">
                                <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($participante['whatsapp']); ?>
                            </span>
                            <?php endif; ?>
                        </p>
                        <p>
                            <span class="badge-info">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($participante['ciudad'] . ', ' . $participante['pais']); ?>
                            </span>
                            <span class="badge-info">
                                <i class="fas fa-user-tie"></i> 
                                Guía: <?php echo htmlspecialchars($participante['productora_nombre'] ?? 'No asignada'); ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php echo $mensaje; ?>
        
        <!-- Escuelas -->
        <div class="schools-section">
            <h3 class="text-white mb-3">Mis Escuelas</h3>
            
            <?php if (!empty($inscripciones)): ?>
                <?php foreach ($inscripciones as $inscripcion): 
                    $escuela_nombre = $inscripcion['escuela'] == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                    $modulo_actual = $inscripcion['estado_cuenta']['modulo_actual'] ?? 1;
                    $total_adeudado = $inscripcion['estado_cuenta']['total_adeudado'] ?? 0;
                    $saldo_favor = $inscripcion['estado_cuenta']['saldo_favor'] ?? 0;
                    $precio_modulo = $inscripcion['precio_modulo'];
                    $progreso = (($modulo_actual - 1) / 9) * 100;
                ?>
                <div class="school-card">
                    <div class="school-header">
                        <div class="school-title">
                            <div class="school-icon">
                                <i class="fas fa-<?php echo $inscripcion['escuela'] == 'VII_INICIAL' ? 'school' : 'university'; ?>"></i>
                            </div>
                            <div class="school-name">
                                <h3><?php echo $escuela_nombre; ?></h3>
                                <p>9 Módulos • Precio por módulo: <?php echo formatearMoneda($precio_modulo); ?></p>
                            </div>
                        </div>
                        <div>
                            <?php if ($inscripcion['pagos_pendientes'] > 0): ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> <?php echo $inscripcion['pagos_pendientes']; ?> pagos pendientes
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check-circle"></i> Activa
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="school-details">
                        <div class="detail-box">
                            <div class="detail-label">Módulo Actual</div>
                            <div class="detail-value"><?php echo $modulo_actual; ?> de 9</div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo $progreso; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="detail-box">
                            <div class="detail-label">Saldo a Favor</div>
                            <div class="detail-value money"><?php echo formatearMoneda($saldo_favor); ?></div>
                        </div>
                        
                        <div class="detail-box">
                            <div class="detail-label">Total Pendiente</div>
                            <div class="detail-value debt"><?php echo formatearMoneda($total_adeudado); ?></div>
                        </div>
                    </div>
                    
                    <div class="school-actions">
                        <a href="registro_pago.php?escuela=<?php echo $inscripcion['escuela']; ?>" class="btn-action btn-pay">
                            <i class="fas fa-credit-card"></i> Registrar Pago
                        </a>
                        <a href="historial.php?escuela=<?php echo $inscripcion['escuela']; ?>" class="btn-action btn-history">
                            <i class="fas fa-history"></i> Ver Historial
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="school-card">
                    <div class="text-center">
                        <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #999; margin-bottom: 20px;"></i>
                        <h4>No hay inscripciones activas</h4>
                        <p class="text-muted">No se encontraron inscripciones activas para tu cuenta.</p>
                        <p>Por favor contacta a tu productora o guía para más información.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== SECCIÓN NUEVA: Detalle por Módulos ========== -->
    <?php if (!empty($inscripcion['modulos_detalle'])): ?>
    <div class="modulos-detalle" style="margin-top: 20px; padding: 20px; background: white; border-radius: 10px;">
        <h4 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;">
            <i class="fas fa-list-alt"></i> Detalle por Módulo
        </h4>
        
        <?php foreach ($inscripcion['modulos_detalle'] as $modulo): 
            $info_estado = formatearEstadoModulo($modulo['estado']);
            $porcentaje_pagado = ($modulo['precio_modulo'] > 0) 
                ? round(($modulo['total_pagado'] / $modulo['precio_modulo']) * 100) 
                : 0;
            // Asegurar que el porcentaje no exceda 100% (para anticipos o pagos extra)
            $porcentaje_pagado = min($porcentaje_pagado, 100); 
        ?>
        <div class="modulo-item" style="
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid <?php echo $info_estado['color']; ?>;
            flex-wrap: wrap; /* Permitir que los elementos se apilen en móvil */
        ">
            <div style="flex: 0 0 60px; text-align: center; font-size: 24px; margin-right: 10px;">
                <?php echo $info_estado['icono']; ?>
            </div>
            
            <div style="flex: 1 1 50%; min-width: 200px;">
                <div style="font-weight: bold; color: #333; margin-bottom: 5px;">
                    Módulo <?php echo $modulo['numero_modulo']; ?> 
                    <?php if (!empty($modulo['nombre_modulo'])): ?>
                        - <?php echo htmlspecialchars($modulo['nombre_modulo']); ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($modulo['arcangeles'])): ?>
                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                    <i class="fas fa-star"></i> <?php echo htmlspecialchars($modulo['arcangeles']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($modulo['fecha_modulo'])): ?>
                <div style="font-size: 11px; color: #999;">
                    <i class="fas fa-calendar"></i> 
                    Vence: <?php echo date('d/m/Y', strtotime($modulo['fecha_modulo'])); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="flex: 0 0 200px; text-align: right; margin-left: auto;">
                <div style="margin-bottom: 5px;">
                    <span style="
                        display: inline-block;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 11px;
                        font-weight: bold;
                        color: white;
                        background: <?php echo $info_estado['color']; ?>;
                    ">
                        <?php echo $info_estado['texto']; ?>
                    </span>
                </div>
                
                <div style="font-size: 14px; font-weight: bold; color: #333;">
                    <?php echo formatearMoneda($modulo['total_pagado']); ?> / 
                    <?php echo formatearMoneda($modulo['precio_modulo']); ?>
                </div>
                
                <?php if ($modulo['total_pendiente'] > 0): ?>
                <div style="font-size: 12px; color: #f44336; margin-top: 3px;">
                    Pendiente: <?php echo formatearMoneda($modulo['total_pendiente']); ?>
                </div>
                <?php endif; ?>
                
                <div style="
                    width: 100%;
                    height: 6px;
                    background: #e0e0e0;
                    border-radius: 3px;
                    margin-top: 8px;
                    overflow: hidden;
                ">
                    <div style="
                        height: 100%;
                        width: <?php echo $porcentaje_pagado; ?>%;
                        background: <?php echo $info_estado['color']; ?>;
                        transition: width 0.3s ease;
                    "></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Timer de sesión
        let timeRemaining = 1800; // 30 minutos en segundos
        
        setInterval(() => {
            timeRemaining--;
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            
            document.getElementById('sessionTime').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Advertencia a los 5 minutos
            if (timeRemaining === 300) {
                alert('Su sesión expirará en 5 minutos. Por favor guarde su trabajo.');
            }
            
            // Cerrar sesión cuando expire
            if (timeRemaining <= 0) {
                window.location.href = 'logout.php';
            }
        }, 1000);
        
        // Renovar sesión con actividad
        document.addEventListener('click', () => {
            // Aquí podrías hacer una llamada AJAX para renovar la sesión
            // Por ahora solo resetea el contador local
            if (timeRemaining < 900) { // Si quedan menos de 15 minutos
                timeRemaining = 1800; // Resetear a 30 minutos
            }
        });
    </script>
</body>
</html>