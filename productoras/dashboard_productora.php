<?php
session_start();
require_once '../includes/db_config.php';

// Verificar autenticación y rol de productora
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'productora') {
    header("Location: ../admin/login.php");
    exit();
}

$productora_id = $_SESSION['usuario_id'];
$productora_nombre = $_SESSION['usuario_nombre'];

$conexion = conectarDB();

// Obtener estadísticas de la productora
// Total de participantes
$sql = "SELECT COUNT(*) as total FROM participantes WHERE productora_id = ? AND activo = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$total_participantes = $resultado->fetch_assoc()['total'] ?? 0;

// Total recaudado por sus participantes
$sql = "SELECT SUM(p.monto_dolares) as total 
        FROM pagos p
        INNER JOIN participantes par ON p.participante_id = par.id
        WHERE par.productora_id = ? AND p.estado = 'VERIFICADO'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$total_recaudado = $resultado->fetch_assoc()['total'] ?? 0;

// Comisión potencial (6.25%)
$comision_potencial = $total_recaudado * 0.0625;

// Pagos pendientes de verificar
$sql = "SELECT COUNT(*) as total 
        FROM pagos p
        INNER JOIN participantes par ON p.participante_id = par.id
        WHERE par.productora_id = ? AND p.estado = 'PENDIENTE'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$pagos_pendientes = $resultado->fetch_assoc()['total'] ?? 0;

// Participantes activos por escuela
$sql = "SELECT i.escuela, COUNT(DISTINCT p.id) as total
        FROM participantes p
        INNER JOIN inscripciones i ON p.id = i.participante_id
        WHERE p.productora_id = ? AND p.activo = 1 AND i.activa = 1
        GROUP BY i.escuela";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$participantes_escuela = [];
while ($row = $resultado->fetch_assoc()) {
    $participantes_escuela[$row['escuela']] = $row['total'];
}

// Últimos 10 participantes
$sql = "SELECT p.*, 
        (SELECT GROUP_CONCAT(i.escuela) FROM inscripciones i WHERE i.participante_id = p.id) as escuelas,
        (SELECT SUM(pg.monto_dolares) FROM pagos pg WHERE pg.participante_id = p.id AND pg.estado = 'VERIFICADO') as total_pagado
        FROM participantes p
        WHERE p.productora_id = ?
        ORDER BY p.fecha_registro DESC
        LIMIT 10";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$ultimos_participantes = [];
while ($row = $resultado->fetch_assoc()) {
    $ultimos_participantes[] = $row;
}

// Últimos pagos de sus participantes
$sql = "SELECT p.*, 
        CONCAT(par.nombre, ' ', par.apellido) as participante_nombre,
        par.email as participante_email
        FROM pagos p
        INNER JOIN participantes par ON p.participante_id = par.id
        WHERE par.productora_id = ?
        ORDER BY p.fecha_registro DESC
        LIMIT 10";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$ultimos_pagos = [];
while ($row = $resultado->fetch_assoc()) {
    $ultimos_pagos[] = $row;
}

// Comisiones liquidadas
$sql = "SELECT SUM(total_comision_dolares) as total 
        FROM liquidaciones 
        WHERE productora_id = ? AND estado = 'PAGADA'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $productora_id);
$stmt->execute();
$resultado = $stmt->get_result();
$comisiones_pagadas = $resultado->fetch_assoc()['total'] ?? 0;

$comisiones_pendientes = $comision_potencial - $comisiones_pagadas;

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Productora - <?php echo htmlspecialchars($productora_nombre); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #9C27B0;
            --secondary-color: #7B1FA2;
            --success-color: #4CAF50;
            --info-color: #2196F3;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --dark-color: #2c3e50;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
        }
        
        /* Sidebar */
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
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            text-align: center;
            color: white;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-header h3 {
            display: none;
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
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-menu span {
            display: none;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        /* Top Bar */
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
        
        .toggle-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--dark-color);
            cursor: pointer;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-icon.purple {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
        }
        
        .stat-icon.green {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        
        .stat-icon.orange {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Tables */
        .data-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .data-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .data-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-verified {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-rejected {
            background: #ffebee;
            color: #c62828;
        }
        
        .table {
            font-size: 14px;
        }
        
        .table th {
            border-bottom: 2px solid var(--light-bg);
            color: var(--dark-color);
            font-weight: 600;
            background: var(--light-bg);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: var(--primary-color);
        }
        
        .action-btn i {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3,
            .sidebar-menu span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-tie" style="font-size: 40px;"></i>
            <h3>Panel Productora</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_productora.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="mis_participantes.php">
                    <i class="fas fa-users"></i>
                    <span>Mis Participantes</span>
                    <?php if($total_participantes > 0): ?>
                        <span class="badge bg-light text-dark ms-auto"><?php echo $total_participantes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="nuevo_participante.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Participante</span>
                </a>
            </li>
            <li>
                <a href="pagos_participantes.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Pagos</span>
                    <?php if($pagos_pendientes > 0): ?>
                        <span class="badge bg-warning ms-auto"><?php echo $pagos_pendientes; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="mis_comisiones.php">
                    <i class="fas fa-percentage"></i>
                    <span>Mis Comisiones</span>
                </a>
            </li>
            <li>
                <a href="reportes_productora.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li>
                <a href="perfil.php">
                    <i class="fas fa-user-cog"></i>
                    <span>Mi Perfil</span>
                </a>
            </li>
            <li>
                <a href="../admin/logout.php" style="margin-top: 30px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="user-info">
                <span>Hola, <strong><?php echo htmlspecialchars($productora_nombre); ?></strong></span>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($productora_nombre, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Page Title -->
        <h1 class="mb-4">Dashboard - Resumen de tu Gestión</h1>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="nuevo_participante.php" class="action-btn">
                <i class="fas fa-user-plus text-primary"></i>
                Agregar Participante
            </a>
            <a href="registrar_pago_productora.php" class="action-btn">
                <i class="fas fa-credit-card text-success"></i>
                Registrar Pago
            </a>
            <a href="mis_participantes.php" class="action-btn">
                <i class="fas fa-users text-info"></i>
                Ver Participantes
            </a>
            <a href="mis_comisiones.php" class="action-btn">
                <i class="fas fa-percentage text-warning"></i>
                Ver Comisiones
            </a>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_participantes; ?></div>
                <div class="stat-label">Total Participantes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value"><?php echo formatearMoneda($total_recaudado); ?></div>
                <div class="stat-label">Total Recaudado</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value"><?php echo formatearMoneda($comisiones_pendientes); ?></div>
                <div class="stat-label">Comisiones Pendientes (6.25%)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $pagos_pendientes; ?></div>
                <div class="stat-label">Pagos por Verificar</div>
            </div>
        </div>
        
        <!-- Participantes por Escuela -->
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-school"></i> Participantes por Escuela
                </h5>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>VII Escuela INICIAL</h6>
                        <h3 class="text-success">
                            <?php echo $participantes_escuela['VII_INICIAL'] ?? 0; ?> participantes
                        </h3>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>III Escuela AVANZADO</h6>
                        <h3 class="text-primary">
                            <?php echo $participantes_escuela['III_AVANZADO'] ?? 0; ?> participantes
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimos Participantes -->
        <?php if(!empty($ultimos_participantes)): ?>
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-users"></i> Últimos Participantes Registrados
                </h5>
                <a href="mis_participantes.php" class="btn btn-sm btn-primary">Ver Todos</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>WhatsApp</th>
                            <th>Escuelas</th>
                            <th>Total Pagado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimos_participantes as $participante): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($participante['nombre'] . ' ' . $participante['apellido']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($participante['email']); ?></td>
                            <td><?php echo htmlspecialchars($participante['whatsapp'] ?? 'No registrado'); ?></td>
                            <td>
                                <?php 
                                $escuelas = explode(',', $participante['escuelas'] ?? '');
                                foreach($escuelas as $escuela) {
                                    if ($escuela == 'VII_INICIAL') {
                                        echo '<span class="badge bg-success me-1">VII INICIAL</span>';
                                    } elseif ($escuela == 'III_AVANZADO') {
                                        echo '<span class="badge bg-primary me-1">III AVANZADO</span>';
                                    }
                                }
                                if (empty($escuelas[0])) {
                                    echo '<span class="badge bg-secondary">Sin inscripción</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <strong><?php echo formatearMoneda($participante['total_pagado'] ?? 0); ?></strong>
                            </td>
                            <td>
                                <a href="ver_participante.php?id=<?php echo $participante['id']; ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Últimos Pagos -->
        <?php if(!empty($ultimos_pagos)): ?>
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-money-bill-wave"></i> Últimos Pagos de tus Participantes
                </h5>
                <a href="pagos_participantes.php" class="btn btn-sm btn-success">Ver Todos</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Participante</th>
                            <th>Escuela</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimos_pagos as $pago): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($pago['participante_nombre']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($pago['participante_email']); ?></small>
                            </td>
                            <td>
                                <?php echo $pago['escuela'] == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO'; ?>
                            </td>
                            <td>
                                <strong><?php echo formatearMoneda($pago['monto_dolares']); ?></strong><br>
                                <small class="text-muted"><?php echo $pago['moneda_pago']; ?></small>
                            </td>
                            <td>
                                <?php if($pago['estado'] == 'PENDIENTE'): ?>
                                    <span class="badge-status badge-pending">Pendiente</span>
                                <?php elseif($pago['estado'] == 'VERIFICADO'): ?>
                                    <span class="badge-status badge-verified">Verificado</span>
                                <?php else: ?>
                                    <span class="badge-status badge-rejected">Rechazado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    </script>
</body>
</html>