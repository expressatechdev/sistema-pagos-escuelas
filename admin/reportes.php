<?php
session_start();
require_once '../includes/db_config.php';

// Verificar autenticación y rol de admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conexion = conectarDB();

// Procesar filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d'); // Hoy
$escuela_filtro = $_GET['escuela'] ?? '';
$productora_filtro = $_GET['productora'] ?? '';
$tipo_reporte = $_GET['tipo'] ?? 'general';

// Función para exportar a Excel
if (isset($_GET['exportar'])) {
    exportarExcel($conexion, $fecha_inicio, $fecha_fin, $escuela_filtro, $productora_filtro, $tipo_reporte);
    exit();
}

// Obtener estadísticas generales del período
$sql_stats = "SELECT 
    COUNT(DISTINCT p.participante_id) as total_participantes_pagaron,
    COUNT(p.id) as total_pagos,
    SUM(CASE WHEN p.estado = 'VERIFICADO' THEN p.monto_dolares ELSE 0 END) as total_verificado,
    SUM(CASE WHEN p.estado = 'PENDIENTE' THEN p.monto_dolares ELSE 0 END) as total_pendiente,
    SUM(CASE WHEN p.estado = 'RECHAZADO' THEN p.monto_dolares ELSE 0 END) as total_rechazado,
    COUNT(CASE WHEN p.estado = 'VERIFICADO' THEN 1 END) as pagos_verificados,
    COUNT(CASE WHEN p.estado = 'PENDIENTE' THEN 1 END) as pagos_pendientes,
    COUNT(CASE WHEN p.estado = 'RECHAZADO' THEN 1 END) as pagos_rechazados
    FROM pagos p
    WHERE p.fecha_pago BETWEEN ? AND ?";

$params = [$fecha_inicio, $fecha_fin];
$types = "ss";

if ($escuela_filtro) {
    $sql_stats .= " AND p.escuela = ?";
    $params[] = $escuela_filtro;
    $types .= "s";
}

$stmt = $conexion->prepare($sql_stats);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Obtener recaudación por escuela
$sql_escuelas = "SELECT 
    p.escuela,
    COUNT(DISTINCT p.participante_id) as participantes,
    COUNT(p.id) as total_pagos,
    SUM(CASE WHEN p.estado = 'VERIFICADO' THEN p.monto_dolares ELSE 0 END) as total_verificado
    FROM pagos p
    WHERE p.fecha_pago BETWEEN ? AND ?";

$params_esc = [$fecha_inicio, $fecha_fin];
$types_esc = "ss";

if ($escuela_filtro) {
    $sql_escuelas .= " AND p.escuela = ?";
    $params_esc[] = $escuela_filtro;
    $types_esc .= "s";
}

$sql_escuelas .= " GROUP BY p.escuela";

$stmt_esc = $conexion->prepare($sql_escuelas);
$stmt_esc->bind_param($types_esc, ...$params_esc);
$stmt_esc->execute();
$resultado_escuelas = $stmt_esc->get_result();

$data_escuelas = [];
while ($row = $resultado_escuelas->fetch_assoc()) {
    $data_escuelas[] = $row;
}

// Obtener recaudación por productora
$sql_productoras = "SELECT 
    prod.nombre as productora,
    COUNT(DISTINCT p.participante_id) as participantes,
    COUNT(p.id) as total_pagos,
    SUM(CASE WHEN p.estado = 'VERIFICADO' THEN p.monto_dolares ELSE 0 END) as total_verificado,
    (SUM(CASE WHEN p.estado = 'VERIFICADO' THEN p.monto_dolares ELSE 0 END) * 0.0625) as comision_generada
    FROM pagos p
    INNER JOIN participantes part ON p.participante_id = part.id
    LEFT JOIN productoras prod ON part.productora_id = prod.id
    WHERE p.fecha_pago BETWEEN ? AND ?";

$params_prod = [$fecha_inicio, $fecha_fin];
$types_prod = "ss";

if ($productora_filtro) {
    $sql_productoras .= " AND prod.id = ?";
    $params_prod[] = $productora_filtro;
    $types_prod .= "i";
}

$sql_productoras .= " GROUP BY prod.id ORDER BY total_verificado DESC";

$stmt_prod = $conexion->prepare($sql_productoras);
$stmt_prod->bind_param($types_prod, ...$params_prod);
$stmt_prod->execute();
$resultado_productoras = $stmt_prod->get_result();

$data_productoras = [];
while ($row = $resultado_productoras->fetch_assoc()) {
    $data_productoras[] = $row;
}

// Obtener lista de morosos
$sql_morosos = "SELECT 
    CONCAT(p.nombre, ' ', p.apellido) as participante,
    p.email,
    p.whatsapp,
    prod.nombre as productora,
    GROUP_CONCAT(DISTINCT ec.escuela) as escuelas,
    SUM(ec.total_adeudado) as total_adeudado,
    MAX(pg.fecha_pago) as ultimo_pago
    FROM participantes p
    INNER JOIN estado_cuenta ec ON p.id = ec.participante_id
    LEFT JOIN productoras prod ON p.productora_id = prod.id
    LEFT JOIN pagos pg ON p.id = pg.participante_id AND pg.estado = 'VERIFICADO'
    WHERE ec.total_adeudado > 0
    AND p.activo = 1";

if ($productora_filtro) {
    $sql_morosos .= " AND p.productora_id = ?";
}

$sql_morosos .= " GROUP BY p.id
    HAVING ultimo_pago IS NULL OR ultimo_pago < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY total_adeudado DESC";

if ($productora_filtro) {
    $stmt_morosos = $conexion->prepare($sql_morosos);
    $stmt_morosos->bind_param("i", $productora_filtro);
    $stmt_morosos->execute();
    $resultado_morosos = $stmt_morosos->get_result();
} else {
    $resultado_morosos = $conexion->query($sql_morosos);
}

$morosos = [];
while ($row = $resultado_morosos->fetch_assoc()) {
    $morosos[] = $row;
}

// Obtener lista de productoras para filtro
$sql_lista_prod = "SELECT id, nombre FROM productoras WHERE activa = 1 ORDER BY nombre";
$resultado_lista_prod = $conexion->query($sql_lista_prod);
$lista_productoras = [];
while ($row = $resultado_lista_prod->fetch_assoc()) {
    $lista_productoras[] = $row;
}

// Función para exportar a Excel
function exportarExcel($conexion, $fecha_inicio, $fecha_fin, $escuela_filtro, $productora_filtro, $tipo_reporte) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="reporte_' . $tipo_reporte . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Inicio del archivo Excel
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="ProgId" content="Excel.Sheet">
    </head>
    <body>';
    
    echo '<h2>Reporte de Pagos - Escuela del Sanador</h2>';
    echo '<p>Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . '</p>';
    
    // Tabla de pagos detallados
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead>
            <tr style="background-color: #4CAF50; color: white;">
                <th>Fecha Pago</th>
                <th>Participante</th>
                <th>Email</th>
                <th>Escuela</th>
                <th>Módulo</th>
                <th>Monto USD</th>
                <th>Moneda</th>
                <th>Monto Original</th>
                <th>Referencia</th>
                <th>Estado</th>
                <th>Productora</th>
                <th>Fecha Registro</th>
            </tr>
          </thead>
          <tbody>';
    
    // Query para obtener todos los pagos del período
    $sql = "SELECT 
            p.fecha_pago,
            CONCAT(part.nombre, ' ', part.apellido) as participante,
            part.email,
            p.escuela,
            p.modulo_num,
            p.monto_dolares,
            p.moneda_pago,
            p.monto_original,
            p.referencia_bancaria,
            p.estado,
            prod.nombre as productora,
            p.fecha_registro
            FROM pagos p
            INNER JOIN participantes part ON p.participante_id = part.id
            LEFT JOIN productoras prod ON part.productora_id = prod.id
            WHERE p.fecha_pago BETWEEN ? AND ?";
    
    $params = [$fecha_inicio, $fecha_fin];
    $types = "ss";
    
    if ($escuela_filtro) {
        $sql .= " AND p.escuela = ?";
        $params[] = $escuela_filtro;
        $types .= "s";
    }
    
    if ($productora_filtro) {
        $sql .= " AND part.productora_id = ?";
        $params[] = $productora_filtro;
        $types .= "i";
    }
    
    $sql .= " ORDER BY p.fecha_pago DESC, p.id DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $total_general = 0;
    while ($row = $resultado->fetch_assoc()) {
        $escuela_nombre = $row['escuela'] == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO';
        
        $bg_color = '';
        if ($row['estado'] == 'VERIFICADO') {
            $bg_color = 'background-color: #e8f5e9;';
            $total_general += $row['monto_dolares'];
        } elseif ($row['estado'] == 'PENDIENTE') {
            $bg_color = 'background-color: #fff3e0;';
        } else {
            $bg_color = 'background-color: #ffebee;';
        }
        
        echo '<tr style="' . $bg_color . '">
                <td>' . date('d/m/Y', strtotime($row['fecha_pago'])) . '</td>
                <td>' . htmlspecialchars($row['participante']) . '</td>
                <td>' . htmlspecialchars($row['email']) . '</td>
                <td>' . $escuela_nombre . '</td>
                <td>' . $row['modulo_num'] . '</td>
                <td>$' . number_format($row['monto_dolares'], 2) . '</td>
                <td>' . $row['moneda_pago'] . '</td>
                <td>' . number_format($row['monto_original'], 2) . '</td>
                <td>' . htmlspecialchars($row['referencia_bancaria']) . '</td>
                <td>' . $row['estado'] . '</td>
                <td>' . htmlspecialchars($row['productora']) . '</td>
                <td>' . date('d/m/Y H:i', strtotime($row['fecha_registro'])) . '</td>
              </tr>';
    }
    
    echo '<tr style="background-color: #4CAF50; color: white; font-weight: bold;">
            <td colspan="5" align="right">TOTAL VERIFICADO:</td>
            <td>$' . number_format($total_general, 2) . '</td>
            <td colspan="6"></td>
          </tr>';
    
    echo '</tbody></table>';
    echo '</body></html>';
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Pagos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        }
        
        .page-header h1 {
            color: var(--dark-color);
            margin: 0;
            font-size: 24px;
        }
        
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .report-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .table-export {
            font-size: 14px;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-card {
                display: none;
            }
            
            .btn {
                display: none;
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
                <a href="reportes.php" class="active">
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
            <h1><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</h1>
        </div>
        
        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" 
                           class="form-control" 
                           id="fecha_inicio" 
                           name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" 
                           class="form-control" 
                           id="fecha_fin" 
                           name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="escuela" class="form-label">Escuela</label>
                    <select name="escuela" id="escuela" class="form-select">
                        <option value="">Todas</option>
                        <option value="VII_INICIAL" <?php echo $escuela_filtro == 'VII_INICIAL' ? 'selected' : ''; ?>>
                            VII INICIAL
                        </option>
                        <option value="III_AVANZADO" <?php echo $escuela_filtro == 'III_AVANZADO' ? 'selected' : ''; ?>>
                            III AVANZADO
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="productora" class="form-label">Productora</label>
                    <select name="productora" id="productora" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach($lista_productoras as $prod): ?>
                            <option value="<?php echo $prod['id']; ?>" 
                                    <?php echo $productora_filtro == $prod['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prod['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-3">
                <button onclick="exportarExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </button>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value text-success">
                    <?php echo formatearMoneda($stats['total_verificado']); ?>
                </div>
                <div class="stat-label">Total Recaudado</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value text-warning">
                    <?php echo formatearMoneda($stats['total_pendiente']); ?>
                </div>
                <div class="stat-label">Pagos Pendientes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value text-primary">
                    <?php echo $stats['total_pagos']; ?>
                </div>
                <div class="stat-label">Total de Pagos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value text-info">
                    <?php echo $stats['total_participantes_pagaron']; ?>
                </div>
                <div class="stat-label">Participantes que Pagaron</div>
            </div>
        </div>
        
        <!-- Gráfico de Recaudación -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="fas fa-chart-line"></i> Recaudación por Escuela
                </h5>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <canvas id="chartEscuelas"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Escuela</th>
                                    <th>Participantes</th>
                                    <th>Pagos</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($data_escuelas as $escuela): 
                                    $nombre = $escuela['escuela'] == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO';
                                ?>
                                <tr>
                                    <td><?php echo $nombre; ?></td>
                                    <td><?php echo $escuela['participantes']; ?></td>
                                    <td><?php echo $escuela['total_pagos']; ?></td>
                                    <td class="fw-bold"><?php echo formatearMoneda($escuela['total_verificado']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reporte por Productoras -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="fas fa-users"></i> Recaudación por Productora
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Productora</th>
                            <th>Participantes</th>
                            <th>Pagos</th>
                            <th>Total Recaudado</th>
                            <th>Comisión (6.25%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_comisiones = 0;
                        foreach($data_productoras as $prod): 
                            $total_comisiones += $prod['comision_generada'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prod['productora'] ?? 'Sin Productora'); ?></td>
                            <td><?php echo $prod['participantes']; ?></td>
                            <td><?php echo $prod['total_pagos']; ?></td>
                            <td class="fw-bold"><?php echo formatearMoneda($prod['total_verificado']); ?></td>
                            <td class="text-primary"><?php echo formatearMoneda($prod['comision_generada']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-active fw-bold">
                            <td colspan="3" class="text-end">TOTALES:</td>
                            <td><?php echo formatearMoneda($stats['total_verificado']); ?></td>
                            <td class="text-primary"><?php echo formatearMoneda($total_comisiones); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Lista de Morosos -->
        <div class="report-card">
            <div class="report-header">
                <h5 class="report-title">
                    <i class="fas fa-exclamation-triangle text-danger"></i> 
                    Participantes Morosos (+30 días sin pagar)
                </h5>
            </div>
            
            <?php if(empty($morosos)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No hay participantes morosos en este momento.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-export">
                        <thead>
                            <tr>
                                <th>Participante</th>
                                <th>Email</th>
                                <th>WhatsApp</th>
                                <th>Productora</th>
                                <th>Escuelas</th>
                                <th>Total Adeudado</th>
                                <th>Último Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($morosos as $moroso): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($moroso['participante']); ?></td>
                                <td><?php echo htmlspecialchars($moroso['email']); ?></td>
                                <td><?php echo htmlspecialchars($moroso['whatsapp'] ?? 'No registrado'); ?></td>
                                <td><?php echo htmlspecialchars($moroso['productora'] ?? 'Sin productora'); ?></td>
                                <td>
                                    <?php 
                                    $escuelas = explode(',', $moroso['escuelas']);
                                    foreach($escuelas as $esc) {
                                        echo '<span class="badge bg-secondary me-1">';
                                        echo $esc == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO';
                                        echo '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="fw-bold text-danger">
                                    <?php echo formatearMoneda($moroso['total_adeudado']); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($moroso['ultimo_pago']) {
                                        echo date('d/m/Y', strtotime($moroso['ultimo_pago']));
                                        $dias = floor((time() - strtotime($moroso['ultimo_pago'])) / 86400);
                                        echo '<br><small class="text-muted">Hace ' . $dias . ' días</small>';
                                    } else {
                                        echo '<span class="text-muted">Nunca ha pagado</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Datos para el gráfico
        const dataEscuelas = {
            labels: [
                <?php 
                foreach($data_escuelas as $esc) {
                    $nombre = $esc['escuela'] == 'VII_INICIAL' ? 'VII INICIAL' : 'III AVANZADO';
                    echo '"' . $nombre . '",';
                }
                ?>
            ],
            datasets: [{
                label: 'Recaudación USD',
                data: [
                    <?php 
                    foreach($data_escuelas as $esc) {
                        echo $esc['total_verificado'] . ',';
                    }
                    ?>
                ],
                backgroundColor: ['#4CAF50', '#2196F3'],
                borderColor: ['#45a049', '#1976D2'],
                borderWidth: 2
            }]
        };
        
        // Configuración del gráfico
        const configEscuelas = {
            type: 'bar',
            data: dataEscuelas,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Recaudación por Escuela (USD)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        };
        
        // Crear el gráfico
        const chartEscuelas = new Chart(
            document.getElementById('chartEscuelas'),
            configEscuelas
        );
        
        // Función para exportar a Excel
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.append('exportar', '1');
            window.location.href = 'reportes.php?' + params.toString();
        }
    </script>
</body>
</html>