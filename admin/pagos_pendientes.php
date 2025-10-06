<?php
session_start();
require_once '../includes/db_config.php';
require_once '../includes/email_config.php';

// Verificar autenticación y rol de admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conexion = conectarDB();
$mensaje = '';
$tipo_mensaje = '';

// Procesar verificación o rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pago_id = intval($_POST['pago_id']);
    $accion = $_POST['accion'];
    
    // Obtener información del pago
    $sql = "SELECT p.*, par.email as participante_email, par.nombre, par.apellido,
            prod.email as productora_email, prod.nombre as productora_nombre,
            p.escuela, p.modulo_num, p.monto_dolares, p.moneda_pago, p.monto_original,
            p.fecha_pago, p.referencia_bancaria
            FROM pagos p
            INNER JOIN participantes par ON p.participante_id = par.id
            LEFT JOIN productoras prod ON par.productora_id = prod.id
            WHERE p.id = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $pago_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $pago = $resultado->fetch_assoc();
    
    if ($pago) {
        if ($accion === 'verificar') {
            // Verificar pago
            $sql_update = "UPDATE pagos SET estado = 'VERIFICADO', verificado_por = ?, fecha_verificacion = NOW() WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("ii", $_SESSION['usuario_id'], $pago_id);
            
            if ($stmt_update->execute()) {
                // Actualizar estado de cuenta
                $sql_call = "CALL sp_actualizar_estado_cuenta(?, ?)";
                $stmt_call = $conexion->prepare($sql_call);
                $stmt_call->bind_param("is", $pago['participante_id'], $pago['escuela']);
                $stmt_call->execute();
                
                // Preparar emails de notificación
                $escuela_nombre = $pago['escuela'] == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                
                // Email al participante
                $mensaje_participante = "
                    <h2 style='color: #4CAF50;'>¡Pago Verificado!</h2>
                    <p>Hola <strong>{$pago['nombre']}</strong>,</p>
                    <p>Tu pago ha sido <strong style='color: #4CAF50;'>VERIFICADO</strong> exitosamente.</p>
                    
                    <div style='background: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3 style='color: #333; margin-top: 0;'>Detalles del Pago:</h3>
                        <p><strong>Escuela:</strong> $escuela_nombre</p>
                        <p><strong>Módulo:</strong> {$pago['modulo_num']}</p>
                        <p><strong>Fecha de Pago:</strong> {$pago['fecha_pago']}</p>
                        <p><strong>Monto:</strong> " . formatearMoneda($pago['monto_dolares']) . "</p>
                        <p><strong>Referencia:</strong> {$pago['referencia_bancaria']}</p>
                        <p><strong>Estado:</strong> <span style='background: #4CAF50; color: white; padding: 5px 10px; border-radius: 5px;'>VERIFICADO</span></p>
                    </div>
                    
                    <p>Tu estado de cuenta ha sido actualizado. Puedes continuar con tus estudios.</p>
                    <p>¡Gracias por tu pago!</p>
                ";
                
                enviarEmailPHPMailer(
                    $pago['participante_email'],
                    '✅ Pago Verificado - ' . $escuela_nombre,
                    $mensaje_participante
                );
                
                // Email a la productora
                if (!empty($pago['productora_email'])) {
                    $mensaje_productora = "
                        <h2 style='color: #4CAF50;'>Pago Verificado</h2>
                        <p>Hola <strong>{$pago['productora_nombre']}</strong>,</p>
                        <p>Un pago de tu participante ha sido verificado:</p>
                        
                        <div style='background: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                            <p><strong>Participante:</strong> {$pago['nombre']} {$pago['apellido']}</p>
                            <p><strong>Escuela:</strong> $escuela_nombre</p>
                            <p><strong>Módulo:</strong> {$pago['modulo_num']}</p>
                            <p><strong>Monto:</strong> " . formatearMoneda($pago['monto_dolares']) . "</p>
                            <p><strong>Estado:</strong> VERIFICADO</p>
                        </div>
                    ";
                    
                    enviarEmailPHPMailer(
                        $pago['productora_email'],
                        '✅ Pago Verificado - ' . $pago['nombre'] . ' ' . $pago['apellido'],
                        $mensaje_productora
                    );
                }
                
                $mensaje = "Pago verificado exitosamente. Se han enviado las notificaciones.";
                $tipo_mensaje = "success";
                
                // Registrar actividad
                registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 'VERIFICAR_PAGO', 
                    "Pago ID: $pago_id verificado");
            }
            
        } elseif ($accion === 'rechazar') {
            // Rechazar pago
            $motivo = $_POST['motivo_rechazo'] ?? 'No especificado';
            
            $sql_update = "UPDATE pagos SET estado = 'RECHAZADO', motivo_rechazo = ?, 
                          verificado_por = ?, fecha_verificacion = NOW() WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("sii", $motivo, $_SESSION['usuario_id'], $pago_id);
            
            if ($stmt_update->execute()) {
                // Email al participante sobre el rechazo
                $escuela_nombre = $pago['escuela'] == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                
                $mensaje_participante = "
                    <h2 style='color: #f44336;'>Pago Rechazado</h2>
                    <p>Hola <strong>{$pago['nombre']}</strong>,</p>
                    <p>Lamentamos informarte que tu pago ha sido <strong style='color: #f44336;'>RECHAZADO</strong>.</p>
                    
                    <div style='background: #ffebee; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3 style='color: #333; margin-top: 0;'>Motivo del Rechazo:</h3>
                        <p style='color: #f44336; font-weight: bold;'>$motivo</p>
                    </div>
                    
                    <div style='background: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                        <h3 style='color: #333; margin-top: 0;'>Detalles del Pago Rechazado:</h3>
                        <p><strong>Escuela:</strong> $escuela_nombre</p>
                        <p><strong>Fecha de Pago:</strong> {$pago['fecha_pago']}</p>
                        <p><strong>Monto:</strong> {$pago['monto_original']} {$pago['moneda_pago']}</p>
                        <p><strong>Referencia:</strong> {$pago['referencia_bancaria']}</p>
                    </div>
                    
                    <p><strong>¿Qué hacer ahora?</strong></p>
                    <ul>
                        <li>Verifica que el comprobante sea legible y corresponda al pago</li>
                        <li>Asegúrate de que la referencia sea correcta</li>
                        <li>Vuelve a registrar el pago con la información correcta</li>
                    </ul>
                    
                    <p>Si tienes dudas, contacta a tu productora: <strong>{$pago['productora_nombre']}</strong></p>
                ";
                
                enviarEmailPHPMailer(
                    $pago['participante_email'],
                    '❌ Pago Rechazado - Acción Requerida',
                    $mensaje_participante
                );
                
                // Email a la productora
                if (!empty($pago['productora_email'])) {
                    $mensaje_productora = "
                        <h2 style='color: #f44336;'>Pago Rechazado</h2>
                        <p>Un pago de tu participante ha sido rechazado:</p>
                        
                        <div style='background: #ffebee; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                            <p><strong>Participante:</strong> {$pago['nombre']} {$pago['apellido']}</p>
                            <p><strong>Motivo:</strong> $motivo</p>
                        </div>
                        
                        <p>Por favor, contacta al participante para resolver el problema.</p>
                    ";
                    
                    enviarEmailPHPMailer(
                        $pago['productora_email'],
                        '❌ Pago Rechazado - ' . $pago['nombre'] . ' ' . $pago['apellido'],
                        $mensaje_productora
                    );
                }
                
                $mensaje = "Pago rechazado. Se han enviado las notificaciones.";
                $tipo_mensaje = "warning";
                
                // Registrar actividad
                registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 'RECHAZAR_PAGO', 
                    "Pago ID: $pago_id rechazado. Motivo: $motivo");
            }
        }
    }
}

// Obtener pagos pendientes
$sql = "SELECT p.*, 
        CONCAT(par.nombre, ' ', par.apellido) as participante_nombre,
        par.email as participante_email,
        par.whatsapp as participante_whatsapp,
        prod.nombre as productora_nombre
        FROM pagos p
        INNER JOIN participantes par ON p.participante_id = par.id
        LEFT JOIN productoras prod ON par.productora_id = prod.id
        WHERE p.estado = 'PENDIENTE'
        ORDER BY p.fecha_registro ASC";

$resultado = $conexion->query($sql);
$pagos_pendientes = [];
while ($row = $resultado->fetch_assoc()) {
    $pagos_pendientes[] = $row;
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos Pendientes - Admin</title>
    
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
        
        /* Reutilizar estilos del dashboard */
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
        
        .topbar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            color: var(--dark-color);
            margin: 0;
            font-size: 24px;
        }
        
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .payment-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .payment-info h5 {
            color: var(--dark-color);
            margin: 0 0 5px 0;
        }
        
        .payment-info p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        
        .payment-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #fff3e0;
            color: #f57c00;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            padding: 10px;
            background: var(--light-bg);
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 14px;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-verify {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-verify:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-reject:hover {
            background: #da190b;
            transform: translateY(-2px);
        }
        
        .btn-view {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-view:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .payment-details {
                grid-template-columns: 1fr;
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
                <a href="pagos_pendientes.php" class="active">
                    <i class="fas fa-clock"></i>
                    <span>Pagos Pendientes</span>
                    <?php if(count($pagos_pendientes) > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo count($pagos_pendientes); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="participantes.php">
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
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-clock text-warning"></i> 
                Pagos Pendientes de Verificación
            </h1>
        </div>
        
        <!-- Mensajes -->
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filters-card">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchInput" placeholder="Buscar por nombre o referencia...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterEscuela">
                        <option value="">Todas las escuelas</option>
                        <option value="VII_INICIAL">VII Escuela INICIAL</option>
                        <option value="III_AVANZADO">III Escuela AVANZADO</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterMoneda">
                        <option value="">Todas las monedas</option>
                        <option value="Bolivares">Bolívares</option>
                        <option value="Dolar">Dólares</option>
                        <option value="Euro">Euros</option>
                        <option value="Zelle">Zelle</option>
                        <option value="PayPal">PayPal</option>
                        <option value="Zinli">Zinli</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Actualizar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Lista de Pagos -->
        <?php if(empty($pagos_pendientes)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No hay pagos pendientes</h3>
                <p>Todos los pagos han sido procesados</p>
            </div>
        <?php else: ?>
            <?php foreach($pagos_pendientes as $pago): 
                $escuela_nombre = $pago['escuela'] == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
            ?>
            <div class="payment-card" data-escuela="<?php echo $pago['escuela']; ?>" data-moneda="<?php echo $pago['moneda_pago']; ?>">
                <div class="payment-header">
                    <div class="payment-info">
                        <h5>
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($pago['participante_nombre']); ?>
                        </h5>
                        <p>
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($pago['participante_email']); ?>
                            <?php if($pago['participante_whatsapp']): ?>
                                | <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($pago['participante_whatsapp']); ?>
                            <?php endif; ?>
                        </p>
                        <p>
                            <i class="fas fa-user-tie"></i> Productora: <?php echo htmlspecialchars($pago['productora_nombre'] ?? 'Sin asignar'); ?>
                        </p>
                    </div>
                    <div>
                        <span class="payment-status">
                            <i class="fas fa-clock"></i> PENDIENTE
                        </span>
                    </div>
                </div>
                
                <div class="payment-details">
                    <div class="detail-item">
                        <div class="detail-label">Escuela</div>
                        <div class="detail-value"><?php echo $escuela_nombre; ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Módulo</div>
                        <div class="detail-value"><?php echo $pago['modulo_num']; ?> de 9</div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Fecha de Pago</div>
                        <div class="detail-value"><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Monto Original</div>
                        <div class="detail-value">
                            <?php echo number_format($pago['monto_original'], 2); ?> <?php echo $pago['moneda_pago']; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Equivalente USD</div>
                        <div class="detail-value" style="color: var(--primary-color);">
                            <?php echo formatearMoneda($pago['monto_dolares']); ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Referencia</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pago['referencia_bancaria']); ?></div>
                    </div>
                    
                    <?php if($pago['banco_origen']): ?>
                    <div class="detail-item">
                        <div class="detail-label">Banco/Plataforma</div>
                        <div class="detail-value"><?php echo htmlspecialchars($pago['banco_origen']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <div class="detail-label">Registrado</div>
                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($pago['fecha_registro'])); ?></div>
                    </div>
                </div>
                
                <?php if($pago['notas']): ?>
                <div class="alert alert-info" style="margin-bottom: 15px;">
                    <strong>Notas:</strong> <?php echo htmlspecialchars($pago['notas']); ?>
                </div>
                <?php endif; ?>
                
                <div class="payment-actions">
                    <?php if($pago['comprobante_url']): ?>
                        <a href="../uploads/comprobantes/<?php echo $pago['comprobante_url']; ?>" 
                           target="_blank" 
                           class="btn btn-action btn-view">
                            <i class="fas fa-file-image"></i> Ver Comprobante
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-action btn-verify" 
                            onclick="verificarPago(<?php echo $pago['id']; ?>)">
                        <i class="fas fa-check"></i> Verificar Pago
                    </button>
                    
                    <button class="btn btn-action btn-reject" 
                            onclick="rechazarPago(<?php echo $pago['id']; ?>)">
                        <i class="fas fa-times"></i> Rechazar Pago
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal para Rechazar -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="pago_id" id="reject_pago_id">
                        <input type="hidden" name="accion" value="rechazar">
                        
                        <div class="mb-3">
                            <label for="motivo_rechazo" class="form-label">Motivo del rechazo:</label>
                            <textarea class="form-control" 
                                      name="motivo_rechazo" 
                                      id="motivo_rechazo" 
                                      rows="3" 
                                      required
                                      placeholder="Explique el motivo del rechazo..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Se enviará una notificación al participante y a la productora con este motivo.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Rechazar Pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function verificarPago(pagoId) {
            if (confirm('¿Está seguro de verificar este pago? Se actualizará el estado de cuenta del participante.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="pago_id" value="${pagoId}">
                    <input type="hidden" name="accion" value="verificar">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rechazarPago(pagoId) {
            document.getElementById('reject_pago_id').value = pagoId;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
        
        // Filtros
        document.getElementById('searchInput').addEventListener('input', filterPayments);
        document.getElementById('filterEscuela').addEventListener('change', filterPayments);
        document.getElementById('filterMoneda').addEventListener('change', filterPayments);
        
        function filterPayments() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const escuela = document.getElementById('filterEscuela').value;
            const moneda = document.getElementById('filterMoneda').value;
            
            const cards = document.querySelectorAll('.payment-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const cardEscuela = card.dataset.escuela;
                const cardMoneda = card.dataset.moneda;
                
                const matchSearch = search === '' || text.includes(search);
                const matchEscuela = escuela === '' || cardEscuela === escuela;
                const matchMoneda = moneda === '' || cardMoneda === moneda;
                
                card.style.display = matchSearch && matchEscuela && matchMoneda ? 'block' : 'none';
            });
        }
        
        // Auto-refresh cada 2 minutos
        setTimeout(function() {
            location.reload();
        }, 120000);
    </script>
</body>
</html>