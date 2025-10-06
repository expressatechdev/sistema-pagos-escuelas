<?php
session_start();
require_once '../includes/db_config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tipo_usuario'])) {
    header("Location: login.php");
    exit();
}

$participante_id = intval($_GET['id'] ?? 0);

if (!$participante_id) {
    header("Location: participantes.php");
    exit();
}

$conexion = conectarDB();

// Obtener información del participante
$sql = "SELECT p.*, prod.nombre as productora_nombre, prod.email as productora_email
        FROM participantes p
        LEFT JOIN productoras prod ON p.productora_id = prod.id
        WHERE p.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $participante_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: participantes.php");
    exit();
}

$participante = $resultado->fetch_assoc();

// Obtener inscripciones y estado de cuenta
$sql_inscripciones = "SELECT i.*, ec.modulo_actual, ec.saldo_favor, ec.total_pagado, ec.total_adeudado
                      FROM inscripciones i
                      LEFT JOIN estado_cuenta ec ON i.participante_id = ec.participante_id AND i.escuela = ec.escuela
                      WHERE i.participante_id = ?";

$stmt_inscripciones = $conexion->prepare($sql_inscripciones);
$stmt_inscripciones->bind_param("i", $participante_id);
$stmt_inscripciones->execute();
$resultado_inscripciones = $stmt_inscripciones->get_result();

$inscripciones = [];
while ($row = $resultado_inscripciones->fetch_assoc()) {
    $inscripciones[] = $row;
}

// Obtener historial de pagos
$sql_pagos = "SELECT * FROM pagos WHERE participante_id = ? ORDER BY fecha_registro DESC";
$stmt_pagos = $conexion->prepare($sql_pagos);
$stmt_pagos->bind_param("i", $participante_id);
$stmt_pagos->execute();
$resultado_pagos = $stmt_pagos->get_result();

$pagos = [];
$total_verificado = 0;
$total_pendiente = 0;
$total_rechazado = 0;

while ($row = $resultado_pagos->fetch_assoc()) {
    $pagos[] = $row;
    if ($row['estado'] == 'VERIFICADO') {
        $total_verificado += $row['monto_dolares'];
    } elseif ($row['estado'] == 'PENDIENTE') {
        $total_pendiente += $row['monto_dolares'];
    } else {
        $total_rechazado += $row['monto_dolares'];
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Participante - <?php echo htmlspecialchars($participante['nombre'] . ' ' . $participante['apellido']); ?></title>
    
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
            padding: 20px;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .header-section h1 {
            margin: 0;
            font-size: 28px;
        }
        
        .header-section p {
            margin: 10px 0 0 0;
            opacity: 0.95;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .info-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: var(--light-bg);
            border-radius: 10px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .school-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .school-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .school-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .school-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 16px;
        }
        
        .progress-bar-custom {
            background: #e0e0e0;
            height: 10px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-box {
            text-align: center;
            padding: 10px;
            background: var(--light-bg);
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .payment-row {
            padding: 15px;
            background: var(--light-bg);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .payment-row:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-verified {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-rejected {
            background: #ffebee;
            color: #c62828;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-section">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($participante['nombre'] . ' ' . $participante['apellido']); ?>
                    </h1>
                    <p>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($participante['email']); ?>
                        <?php if($participante['whatsapp']): ?>
                            | <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($participante['whatsapp']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if($participante['activo']): ?>
                        <span class="badge bg-success">ACTIVO</span>
                    <?php else: ?>
                        <span class="badge bg-danger">INACTIVO</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Información Personal -->
        <div class="info-card">
            <div class="info-card-header">
                <h5 class="info-card-title">
                    <i class="fas fa-info-circle"></i> Información Personal
                </h5>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nombre Completo</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['nombre'] . ' ' . $participante['apellido']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['email']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">WhatsApp</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['whatsapp'] ?: 'No registrado'); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Ubicación</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['ciudad'] . ', ' . $participante['pais']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Productora Guía</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['productora_nombre'] ?: 'Sin asignar'); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Voluntad</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($participante['voluntad']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Fecha de Registro</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y', strtotime($participante['fecha_registro'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Token de Acceso</div>
                    <div class="info-value" style="font-size: 10px; word-break: break-all;">
                        <?php echo substr($participante['token_acceso'], 0, 20); ?>...
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Inscripciones y Estado de Cuenta -->
        <div class="info-card">
            <div class="info-card-header">
                <h5 class="info-card-title">
                    <i class="fas fa-graduation-cap"></i> Escuelas y Estado de Cuenta
                </h5>
            </div>
            
            <?php if(empty($inscripciones)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Este participante no está inscrito en ninguna escuela.
                </div>
            <?php else: ?>
                <?php foreach($inscripciones as $inscripcion): 
                    $escuela_nombre = $inscripcion['escuela'] == 'VII_INICIAL' ? 'VII Escuela INICIAL' : 'III Escuela AVANZADO';
                    $progreso = (($inscripcion['modulo_actual'] - 1) / 9) * 100;
                ?>
                <div class="school-card">
                    <div class="school-header">
                        <div class="school-name">
                            <i class="fas fa-school"></i> <?php echo $escuela_nombre; ?>
                        </div>
                        <div>
                            <?php if($inscripcion['activa']): ?>
                                <span class="badge bg-success">Activa</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactiva</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Progreso:</strong> Módulo <?php echo $inscripcion['modulo_actual'] ?? 1; ?> de 9
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $progreso; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value text-primary">
                                <?php echo formatearMoneda($inscripcion['precio_modulo']); ?>
                            </div>
                            <div class="stat-label">Precio/Módulo</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-value text-success">
                                <?php echo formatearMoneda($inscripcion['total_pagado'] ?? 0); ?>
                            </div>
                            <div class="stat-label">Total Pagado</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-value text-info">
                                <?php echo formatearMoneda($inscripcion['saldo_favor'] ?? 0); ?>
                            </div>
                            <div class="stat-label">Saldo a Favor</div>
                        </div>
                        
                        <div class="stat-box">
                            <div class="stat-value text-danger">
                                <?php echo formatearMoneda($inscripcion['total_adeudado'] ?? 0); ?>
                            </div>
                            <div class="stat-label">Total Adeudado</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Historial de Pagos -->
        <div class="info-card">
            <div class="info-card-header">
                <h5 class="info-card-title">
                    <i class="fas fa-money-bill-wave"></i> Historial de Pagos
                </h5>
                <div>
                    <span class="badge bg-success">Verificados: <?php echo formatearMoneda($total_verificado); ?></span>
                    <span class="badge bg-warning">Pendientes: <?php echo formatearMoneda($total_pendiente); ?></span>
                    <span class="badge bg-danger">Rechazados: <?php echo formatearMoneda($total_rechazado); ?></span>
                </div>
            </div>
            
            <?php if(empty($pagos)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay pagos registrados.
                </div>
            <?php else: ?>
                <?php foreach($pagos as $pago): 
                    $escuela_nombre = $pago['escuela'] == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO';
                ?>
                <div class="payment-row">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <strong><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></strong><br>
                            <small class="text-muted">Módulo <?php echo $pago['modulo_num']; ?></small>
                        </div>
                        
                        <div class="col-md-2">
                            <span class="badge bg-secondary"><?php echo $escuela_nombre; ?></span>
                        </div>
                        
                        <div class="col-md-2">
                            <strong><?php echo formatearMoneda($pago['monto_dolares']); ?></strong><br>
                            <small><?php echo $pago['moneda_pago']; ?></small>
                        </div>
                        
                        <div class="col-md-3">
                            Ref: <?php echo htmlspecialchars($pago['referencia_bancaria']); ?><br>
                            <small><?php echo htmlspecialchars($pago['banco_origen'] ?? ''); ?></small>
                        </div>
                        
                        <div class="col-md-2">
                            <?php if($pago['estado'] == 'VERIFICADO'): ?>
                                <span class="badge-status badge-verified">
                                    <i class="fas fa-check"></i> Verificado
                                </span>
                            <?php elseif($pago['estado'] == 'PENDIENTE'): ?>
                                <span class="badge-status badge-pending">
                                    <i class="fas fa-clock"></i> Pendiente
                                </span>
                            <?php else: ?>
                                <span class="badge-status badge-rejected">
                                    <i class="fas fa-times"></i> Rechazado
                                </span>
                                <?php if($pago['motivo_rechazo']): ?>
                                    <br>
                                    <small class="text-danger">
                                        <?php echo htmlspecialchars($pago['motivo_rechazo']); ?>
                                    </small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-1 text-end">
                            <?php if($pago['comprobante_url']): ?>
                                <a href="../uploads/comprobantes/<?php echo $pago['comprobante_url']; ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Ver comprobante">
                                    <i class="fas fa-file-image"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button onclick="window.history.back()" class="btn btn-secondary btn-action">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
            
            <?php if($_SESSION['tipo_usuario'] === 'admin'): ?>
                <a href="editar_participante.php?id=<?php echo $participante_id; ?>" 
                   class="btn btn-warning btn-action">
                    <i class="fas fa-edit"></i> Editar
                </a>
                
                <a href="inscribir_escuela.php?id=<?php echo $participante_id; ?>" 
                   class="btn btn-success btn-action">
                    <i class="fas fa-plus"></i> Inscribir en Escuela
                </a>
            <?php endif; ?>
            
            <button onclick="window.print()" class="btn btn-info btn-action">
                <i class="fas fa-print"></i> Imprimir
            </button>
            
            <a href="estado_cuenta_pdf.php?id=<?php echo $participante_id; ?>" 
               class="btn btn-danger btn-action" 
               target="_blank">
                <i class="fas fa-file-pdf"></i> Generar PDF
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>