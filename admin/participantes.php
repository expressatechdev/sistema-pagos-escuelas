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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'toggle_activo') {
        // Activar/Desactivar participante
        $participante_id = intval($_POST['participante_id']);
        $nuevo_estado = intval($_POST['nuevo_estado']);
        
        $sql = "UPDATE participantes SET activo = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $nuevo_estado, $participante_id);
        
        if ($stmt->execute()) {
            $estado_texto = $nuevo_estado ? 'activado' : 'desactivado';
            $mensaje = "Participante $estado_texto correctamente.";
            $tipo_mensaje = 'success';
            
            registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 
                'TOGGLE_PARTICIPANTE', "Participante ID: $participante_id $estado_texto");
        }
    }
}

// Obtener filtros
$filtro_escuela = $_GET['escuela'] ?? '';
$filtro_productora = $_GET['productora'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$sql = "SELECT DISTINCT p.*, 
        prod.nombre as productora_nombre,
        GROUP_CONCAT(DISTINCT i.escuela) as escuelas,
        (SELECT SUM(pg.monto_dolares) FROM pagos pg WHERE pg.participante_id = p.id AND pg.estado = 'VERIFICADO') as total_pagado,
        (SELECT SUM(ec.total_adeudado) FROM estado_cuenta ec WHERE ec.participante_id = p.id) as total_adeudado,
        (SELECT COUNT(*) FROM pagos pg WHERE pg.participante_id = p.id AND pg.estado = 'PENDIENTE') as pagos_pendientes
        FROM participantes p
        LEFT JOIN productoras prod ON p.productora_id = prod.id
        LEFT JOIN inscripciones i ON p.id = i.participante_id
        LEFT JOIN estado_cuenta ec ON p.id = ec.participante_id
        WHERE 1=1";

$params = [];
$types = "";

// Aplicar filtros
if ($busqueda) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.email LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = &$busqueda_like;
    $params[] = &$busqueda_like;
    $params[] = &$busqueda_like;
    $types .= "sss";
}

if ($filtro_productora) {
    $sql .= " AND p.productora_id = ?";
    $params[] = &$filtro_productora;
    $types .= "i";
}

if ($filtro_estado !== '') {
    $sql .= " AND p.activo = ?";
    $params[] = &$filtro_estado;
    $types .= "i";
}

if ($filtro_escuela) {
    $sql .= " AND i.escuela = ?";
    $params[] = &$filtro_escuela;
    $types .= "s";
}

$sql .= " GROUP BY p.id ORDER BY p.fecha_registro DESC";

// Ejecutar consulta
if ($types) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $resultado = $conexion->query($sql);
}

$participantes = [];
while ($row = $resultado->fetch_assoc()) {
    $participantes[] = $row;
}

// Obtener productoras para el filtro
$sql_productoras = "SELECT id, nombre FROM productoras WHERE activa = 1 ORDER BY nombre";
$resultado_productoras = $conexion->query($sql_productoras);
$productoras = [];
while ($row = $resultado_productoras->fetch_assoc()) {
    $productoras[] = $row;
}

// Estadísticas generales
$sql_stats = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
              SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
              FROM participantes";
$resultado_stats = $conexion->query($sql_stats);
$stats = $resultado_stats->fetch_assoc();

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Participantes - Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
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
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            color: var(--dark-color);
            margin: 0;
            font-size: 24px;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-mini {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .stat-mini-icon.blue {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        
        .stat-mini-icon.green {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }
        
        .stat-mini-icon.red {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }
        
        .stat-mini-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--dark-color);
            margin: 0;
        }
        
        .stat-mini-label {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        
        /* Filters */
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-inactive {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-school {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin: 2px;
            display: inline-block;
        }
        
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
            margin: 0 2px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .participant-name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .participant-email {
            font-size: 12px;
            color: #666;
        }
        
        .money-amount {
            font-weight: 600;
        }
        
        .money-amount.success {
            color: var(--primary-color);
        }
        
        .money-amount.danger {
            color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats-row {
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
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Gestión de Participantes</h1>
            <div>
                <button class="btn btn-success" onclick="window.location.href='nuevo_participante_admin.php'">
                    <i class="fas fa-plus"></i> Nuevo Participante
                </button>
                <button class="btn btn-primary" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-mini-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="stat-mini-value"><?php echo $stats['total']; ?></p>
                    <p class="stat-mini-label">Total Participantes</p>
                </div>
            </div>
            
            <div class="stat-mini">
                <div class="stat-mini-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <p class="stat-mini-value"><?php echo $stats['activos']; ?></p>
                    <p class="stat-mini-label">Activos</p>
                </div>
            </div>
            
            <div class="stat-mini">
                <div class="stat-mini-icon red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div>
                    <p class="stat-mini-value"><?php echo $stats['inactivos']; ?></p>
                    <p class="stat-mini-label">Inactivos</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <input type="text" 
                           class="form-control" 
                           name="busqueda" 
                           placeholder="Buscar por nombre o email..."
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="col-md-2">
                    <select name="escuela" class="form-select">
                        <option value="">Todas las escuelas</option>
                        <option value="VII_INICIAL" <?php echo $filtro_escuela == 'VII_INICIAL' ? 'selected' : ''; ?>>
                            VII INICIAL
                        </option>
                        <option value="III_AVANZADO" <?php echo $filtro_escuela == 'III_AVANZADO' ? 'selected' : ''; ?>>
                            III AVANZADO
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="productora" class="form-select">
                        <option value="">Todas las productoras</option>
                        <?php foreach($productoras as $prod): ?>
                            <option value="<?php echo $prod['id']; ?>" 
                                    <?php echo $filtro_productora == $prod['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prod['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo $filtro_estado === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $filtro_estado === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="participantes.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table id="tablaParticipantes" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Participante</th>
                            <th>Contacto</th>
                            <th>Productora</th>
                            <th>Escuelas</th>
                            <th>Pagado</th>
                            <th>Adeudado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($participantes as $participante): ?>
                        <tr>
                            <td><?php echo $participante['id']; ?></td>
                            <td>
                                <div class="participant-name">
                                    <?php echo htmlspecialchars($participante['nombre'] . ' ' . $participante['apellido']); ?>
                                </div>
                                <div class="participant-email">
                                    <?php echo htmlspecialchars($participante['email']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if($participante['whatsapp']): ?>
                                    <i class="fab fa-whatsapp text-success"></i> 
                                    <?php echo htmlspecialchars($participante['whatsapp']); ?>
                                <?php else: ?>
                                    <span class="text-muted">No registrado</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($participante['ciudad'] . ', ' . $participante['pais']); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($participante['productora_nombre'] ?? 'Sin asignar'); ?>
                            </td>
                            <td>
                                <?php 
                                if ($participante['escuelas']) {
                                    $escuelas = explode(',', $participante['escuelas']);
                                    foreach($escuelas as $escuela) {
                                        if ($escuela == 'VII_INICIAL') {
                                            echo '<span class="badge-school">VII INICIAL</span>';
                                        } elseif ($escuela == 'III_AVANZADO') {
                                            echo '<span class="badge-school">III AVANZADO</span>';
                                        }
                                    }
                                } else {
                                    echo '<span class="text-muted">Sin inscripción</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="money-amount success">
                                    <?php echo formatearMoneda($participante['total_pagado'] ?? 0); ?>
                                </span>
                            </td>
                            <td>
                                <span class="money-amount danger">
                                    <?php echo formatearMoneda($participante['total_adeudado'] ?? 0); ?>
                                </span>
                                <?php if($participante['pagos_pendientes'] > 0): ?>
                                    <br>
                                    <small class="text-warning">
                                        <i class="fas fa-clock"></i> <?php echo $participante['pagos_pendientes']; ?> pendiente(s)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($participante['activo']): ?>
                                    <span class="badge-status badge-active">Activo</span>
                                <?php else: ?>
                                    <span class="badge-status badge-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="ver_participante.php?id=<?php echo $participante['id']; ?>" 
                                   class="btn btn-sm btn-info btn-action" 
                                   title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <a href="editar_participante.php?id=<?php echo $participante['id']; ?>" 
                                   class="btn btn-sm btn-warning btn-action" 
                                   title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="accion" value="toggle_activo">
                                    <input type="hidden" name="participante_id" value="<?php echo $participante['id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $participante['activo'] ? 0 : 1; ?>">
                                    
                                    <?php if($participante['activo']): ?>
                                        <button type="submit" 
                                                class="btn btn-sm btn-danger btn-action" 
                                                title="Desactivar"
                                                onclick="return confirm('¿Desactivar este participante?')">
                                            <i class="fas fa-user-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" 
                                                class="btn btn-sm btn-success btn-action" 
                                                title="Activar"
                                                onclick="return confirm('¿Activar este participante?')">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tablaParticipantes').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                },
                pageLength: 25,
                order: [[0, 'desc']],
                responsive: true
            });
        });
        
        function exportarExcel() {
            // Construir URL con los filtros actuales
            const params = new URLSearchParams(window.location.search);
            params.append('export', 'excel');
            window.location.href = 'exportar_participantes.php?' + params.toString();
        }
    </script>
</body>
</html>