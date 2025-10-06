<?php
session_start();
require_once '../includes/db_config.php';

// Verificar autenticación y rol de admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conexion = conectarDB();

// Obtener estadísticas generales
// Total recaudado
$sql = "SELECT SUM(monto_dolares) as total FROM pagos WHERE estado = 'VERIFICADO'";
$resultado = $conexion->query($sql);
$total_recaudado = $resultado->fetch_assoc()['total'] ?? 0;

// Pagos pendientes de verificar
$sql = "SELECT COUNT(*) as total FROM pagos WHERE estado = 'PENDIENTE'";
$resultado = $conexion->query($sql);
$pagos_pendientes = $resultado->fetch_assoc()['total'] ?? 0;

// Total participantes activos
$sql = "SELECT COUNT(DISTINCT p.id) as total 
        FROM participantes p 
        INNER JOIN inscripciones i ON p.id = i.participante_id 
        WHERE p.activo = 1 AND i.activa = 1";
$resultado = $conexion->query($sql);
$total_participantes = $resultado->fetch_assoc()['total'] ?? 0;

// Total productoras activas
$sql = "SELECT COUNT(*) as total FROM productoras WHERE activa = 1";
$resultado = $conexion->query($sql);
$total_productoras = $resultado->fetch_assoc()['total'] ?? 0;

// Recaudación por escuela
$sql = "SELECT escuela, SUM(monto_dolares) as total 
        FROM pagos 
        WHERE estado = 'VERIFICADO' 
        GROUP BY escuela";
$resultado = $conexion->query($sql);
$recaudacion_escuelas = [];
while ($row = $resultado->fetch_assoc()) {
    $recaudacion_escuelas[$row['escuela']] = $row['total'];
}

// Últimos 10 pagos pendientes
$sql = "SELECT p.id, p.fecha_pago, p.fecha_registro, p.monto_dolares, p.moneda_pago,
               p.referencia_bancaria, p.escuela,
               CONCAT(par.nombre, ' ', par.apellido) as participante_nombre,
               par.email as participante_email,
               prod.nombre as productora_nombre
        FROM pagos p
        INNER JOIN participantes par ON p.participante_id = par.id
        LEFT JOIN productoras prod ON par.productora_id = prod.id
        WHERE p.estado = 'PENDIENTE'
        ORDER BY p.fecha_registro DESC
        LIMIT 10";
$resultado = $conexion->query($sql);
$pagos_recientes = [];
while ($row = $resultado->fetch_assoc()) {
    $pagos_recientes[] = $row;
}

// Participantes morosos (más de 30 días sin pagar)
$sql = "SELECT COUNT(DISTINCT ec.participante_id) as total 
        FROM estado_cuenta ec
        WHERE ec.total_adeudado > 0 
        AND ec.ultima_actualizacion < DATE_SUB(NOW(), INTERVAL 30 DAY)";
$resultado = $conexion->query($sql);
$total_morosos = $resultado->fetch_assoc()['total'] ?? 0;

// Tasas de cambio actuales
$tasa_bolivares = obtenerTasaCambio($conexion, 'Bolivares') ?? 0;
$tasa_euro = obtenerTasaCambio($conexion, 'Euro') ?? 0;

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistema de Pagos</title>
    
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
            font-size: 20px;
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
        
        .stat-icon.green {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        
        .stat-icon.orange {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .stat-icon.purple {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
        }
        
        .stat-icon.red {
            background: linear-gradient(135deg, #f44336, #d32f2f);
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
        
        .stat-change {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
        }
        
        .stat-change.positive {
            color: var(--primary-color);
        }
        
        .stat-change.negative {
            color: var(--danger-color);
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
        
        .btn-sm-action {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
            margin: 0 2px;
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
            <i class="fas fa-graduation-cap" style="font-size: 40px;"></i>
            <h3>Admin Panel</h3>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_admin.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="pagos_pendientes.php">
                    <i class="fas fa-clock"></i>
                    <span>Pagos Pendientes</span>
                    <?php if($pagos_pendientes > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $pagos_pendientes; ?></span>
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
                <a href="configuracion.php">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
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
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="user-info">
                <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></span>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Page Title -->
        <h1 class="mb-4">Dashboard - Resumen General</h1>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="pagos_pendientes.php" class="action-btn">
                <i class="fas fa-check-circle text-success"></i>
                Verificar Pagos
            </a>
            <a href="participantes.php?action=nuevo" class="action-btn">
                <i class="fas fa-user-plus text-primary"></i>
                Nuevo Participante
            </a>
            <a href="reportes.php" class="action-btn">
                <i class="fas fa-file-excel text-info"></i>
                Generar Reportes
            </a>
            <a href="tasas.php" class="action-btn">
                <i class="fas fa-sync text-warning"></i>
                Actualizar Tasas
            </a>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value"><?php echo formatearMoneda($total_recaudado); ?></div>
                <div class="stat-label">Total Recaudado</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> Todos los tiempos
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $pagos_pendientes; ?></div>
                <div class="stat-label">Pagos Pendientes</div>
                <?php if($pagos_pendientes > 0): ?>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-circle"></i> Requieren verificación
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_participantes; ?></div>
                <div class="stat-label">Participantes Activos</div>
                <div class="stat-change positive">
                    <i class="fas fa-check"></i> En 2 escuelas
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-value"><?php echo $total_productoras; ?></div>
                <div class="stat-label">Productoras Activas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $total_morosos; ?></div>
                <div class="stat-label">Participantes Morosos</div>
                <?php if($total_morosos > 0): ?>
                    <div class="stat-change negative">
                        <i class="fas fa-calendar"></i> +30 días sin pagar
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recaudación por Escuela -->
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-school"></i> Recaudación por Escuela
                </h5>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>VII Escuela INICIAL</h6>
                        <h3 class="text-success">
                            <?php echo formatearMoneda($recaudacion_escuelas['VII_INICIAL'] ?? 0); ?>
                        </h3>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>III Escuela AVANZADO</h6>
                        <h3 class="text-primary">
                            <?php echo formatearMoneda($recaudacion_escuelas['III_AVANZADO'] ?? 0); ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tasas de Cambio -->
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-exchange-alt"></i> Tasas de Cambio Actuales
                </h5>
                <a href="tasas.php" class="btn btn-sm btn-primary">Actualizar</a>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>Bolívares</h6>
                        <h4>1 USD = <?php echo number_format($tasa_bolivares, 2); ?> Bs</h4>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h6>Euros</h6>
                        <h4>1 USD = <?php echo number_format($tasa_euro, 2); ?> EUR</h4>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimos Pagos Pendientes -->
        <?php if(!empty($pagos_recientes)): ?>
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-list"></i> Últimos Pagos Pendientes
                </h5>
                <a href="pagos_pendientes.php" class="btn btn-sm btn-warning">Ver Todos</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Participante</th>
                            <th>Escuela</th>
                            <th>Monto</th>
                            <th>Referencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pagos_recientes as $pago): ?>
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
                            <td><?php echo htmlspecialchars($pago['referencia_bancaria']); ?></td>
                            <td>
                                <a href="verificar_pago.php?id=<?php echo $pago['id']; ?>" 
                                   class="btn btn-sm btn-success btn-sm-action">
                                    <i class="fas fa-check"></i> Verificar
                                </a>
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
        
        // Auto-refresh para pagos pendientes
        setTimeout(function() {
            location.reload();
        }, 300000); // Refresh cada 5 minutos
    </script>
</body>
</html>
