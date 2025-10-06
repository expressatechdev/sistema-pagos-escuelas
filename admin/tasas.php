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

// Procesar actualización de tasa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moneda = $_POST['moneda'] ?? '';
    $nueva_tasa = floatval($_POST['tasa'] ?? 0);
    
    if ($moneda && $nueva_tasa > 0) {
        // Desactivar tasas anteriores de esta moneda
        $sql_desactivar = "UPDATE tasas_cambio SET activa = 0 WHERE moneda = ?";
        $stmt_desactivar = $conexion->prepare($sql_desactivar);
        $stmt_desactivar->bind_param("s", $moneda);
        $stmt_desactivar->execute();
        
        // Insertar nueva tasa
        $sql_insertar = "INSERT INTO tasas_cambio (moneda, tasa, actualizado_por, activa) VALUES (?, ?, ?, 1)";
        $stmt_insertar = $conexion->prepare($sql_insertar);
        $stmt_insertar->bind_param("sdi", $moneda, $nueva_tasa, $_SESSION['usuario_id']);
        
        if ($stmt_insertar->execute()) {
            $mensaje = "Tasa de cambio actualizada correctamente para $moneda: " . number_format($nueva_tasa, 2);
            $tipo_mensaje = 'success';
            
            // Registrar actividad
            registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 
                'ACTUALIZAR_TASA', "Tasa $moneda actualizada a $nueva_tasa");
        } else {
            $mensaje = "Error al actualizar la tasa de cambio.";
            $tipo_mensaje = 'danger';
        }
    } else {
        $mensaje = "Por favor ingrese valores válidos.";
        $tipo_mensaje = 'warning';
    }
}

// Obtener tasas actuales
$sql_tasas = "SELECT * FROM tasas_cambio WHERE activa = 1 ORDER BY moneda";
$resultado_tasas = $conexion->query($sql_tasas);
$tasas_actuales = [];
while ($row = $resultado_tasas->fetch_assoc()) {
    $tasas_actuales[$row['moneda']] = $row;
}

// Obtener historial de tasas
$sql_historial = "SELECT tc.*, a.nombre as actualizado_por_nombre
                  FROM tasas_cambio tc
                  LEFT JOIN admins a ON tc.actualizado_por = a.id
                  ORDER BY tc.fecha_actualizacion DESC
                  LIMIT 50";
$resultado_historial = $conexion->query($sql_historial);
$historial = [];
while ($row = $resultado_historial->fetch_assoc()) {
    $historial[] = $row;
}

// Obtener estadísticas de uso de monedas
$sql_stats = "SELECT 
              moneda_pago,
              COUNT(*) as total_pagos,
              SUM(monto_original) as total_monto_original,
              SUM(monto_dolares) as total_dolares,
              AVG(CASE WHEN tasa_cambio IS NOT NULL THEN tasa_cambio END) as tasa_promedio
              FROM pagos
              WHERE fecha_pago >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY moneda_pago
              ORDER BY total_pagos DESC";
$resultado_stats = $conexion->query($sql_stats);
$stats_monedas = [];
while ($row = $resultado_stats->fetch_assoc()) {
    $stats_monedas[] = $row;
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tasas de Cambio - Admin</title>
    
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
        
        .currency-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .currency-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .currency-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            margin-bottom: 15px;
        }
        
        .currency-icon.bolivares {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .currency-icon.euro {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }
        
        .currency-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .currency-rate {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .currency-update {
            font-size: 12px;
            color: #666;
        }
        
        .form-update {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .form-update input {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
        }
        
        .form-update button {
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-update button:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .stats-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .stats-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .history-table {
            font-size: 14px;
        }
        
        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-inactive {
            background: #f5f5f5;
            color: #999;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .alert-info-custom {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .alert-info-custom h5 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        
        .alert-info-custom p {
            margin-bottom: 5px;
            color: #333;
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
                <a href="tasas.php" class="active">
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
            <h1><i class="fas fa-exchange-alt"></i> Gestión de Tasas de Cambio</h1>
        </div>
        
        <!-- Mensajes -->
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Info Alert -->
        <div class="alert-info-custom">
            <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
            <p><strong>Actualiza las tasas diariamente</strong> para mantener los cálculos de conversión correctos.</p>
            <p>Las tasas se aplican automáticamente a los nuevos pagos registrados.</p>
            <p>Formato: 1 USD = X (Moneda)</p>
        </div>
        
        <!-- Currency Cards -->
        <div class="row">
            <!-- Bolívares -->
            <div class="col-md-6">
                <div class="currency-card">
                    <div class="currency-icon bolivares">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    
                    <div class="currency-name">Bolívares (Bs)</div>
                    
                    <?php if(isset($tasas_actuales['Bolivares'])): ?>
                        <div class="currency-rate">
                            1 USD = <?php echo number_format($tasas_actuales['Bolivares']['tasa'], 2); ?> Bs
                        </div>
                        <div class="currency-update">
                            <i class="fas fa-clock"></i> 
                            Última actualización: <?php echo date('d/m/Y H:i', strtotime($tasas_actuales['Bolivares']['fecha_actualizacion'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="currency-rate text-muted">
                            Sin tasa definida
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="form-update">
                        <input type="hidden" name="moneda" value="Bolivares">
                        <input type="number" 
                               name="tasa" 
                               step="0.01" 
                               min="0.01" 
                               placeholder="Nueva tasa..."
                               value="<?php echo $tasas_actuales['Bolivares']['tasa'] ?? ''; ?>"
                               required>
                        <button type="submit">
                            <i class="fas fa-sync"></i> Actualizar
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Euros -->
            <div class="col-md-6">
                <div class="currency-card">
                    <div class="currency-icon euro">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    
                    <div class="currency-name">Euros (EUR)</div>
                    
                    <?php if(isset($tasas_actuales['Euro'])): ?>
                        <div class="currency-rate">
                            1 USD = <?php echo number_format($tasas_actuales['Euro']['tasa'], 4); ?> EUR
                        </div>
                        <div class="currency-update">
                            <i class="fas fa-clock"></i> 
                            Última actualización: <?php echo date('d/m/Y H:i', strtotime($tasas_actuales['Euro']['fecha_actualizacion'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="currency-rate text-muted">
                            Sin tasa definida
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="form-update">
                        <input type="hidden" name="moneda" value="Euro">
                        <input type="number" 
                               name="tasa" 
                               step="0.0001" 
                               min="0.01" 
                               placeholder="Nueva tasa..."
                               value="<?php echo $tasas_actuales['Euro']['tasa'] ?? ''; ?>"
                               required>
                        <button type="submit">
                            <i class="fas fa-sync"></i> Actualizar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas de Uso -->
        <div class="stats-card">
            <div class="stats-header">
                <h5 class="stats-title">
                    <i class="fas fa-chart-pie"></i> Uso de Monedas (Últimos 30 días)
                </h5>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <canvas id="chartMonedas" height="200"></canvas>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Moneda</th>
                                    <th>Pagos</th>
                                    <th>Total Original</th>
                                    <th>Total USD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats_monedas as $stat): ?>
                                <tr>
                                    <td><strong><?php echo $stat['moneda_pago']; ?></strong></td>
                                    <td><?php echo $stat['total_pagos']; ?></td>
                                    <td><?php echo number_format($stat['total_monto_original'], 2); ?></td>
                                    <td class="text-success">
                                        <?php echo formatearMoneda($stat['total_dolares']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Historial de Cambios -->
        <div class="stats-card">
            <div class="stats-header">
                <h5 class="stats-title">
                    <i class="fas fa-history"></i> Historial de Cambios
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table history-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Moneda</th>
                            <th>Tasa</th>
                            <th>Actualizado por</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historial as $h): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($h['fecha_actualizacion'])); ?></td>
                            <td>
                                <strong><?php echo $h['moneda']; ?></strong>
                            </td>
                            <td>
                                <?php echo number_format($h['tasa'], $h['moneda'] == 'Euro' ? 4 : 2); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($h['actualizado_por_nombre'] ?? 'Sistema'); ?>
                            </td>
                            <td>
                                <?php if($h['activa']): ?>
                                    <span class="badge-active">Activa</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Histórico</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Referencias -->
        <div class="stats-card">
            <div class="stats-header">
                <h5 class="stats-title">
                    <i class="fas fa-link"></i> Referencias de Tasas de Cambio
                </h5>
            </div>
            
            <p>Puedes consultar las tasas actuales en:</p>
            <ul>
                <li><a href="https://www.bcv.org.ve/" target="_blank">Banco Central de Venezuela (BCV)</a> - Tasa oficial del Bolívar</li>
                <li><a href="https://www.xe.com/" target="_blank">XE.com</a> - Conversor universal de monedas</li>
                <li><a href="https://www.exchangerate-api.com/" target="_blank">ExchangeRate-API</a> - Tasas de cambio en tiempo real</li>
            </ul>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Datos para el gráfico de uso de monedas
        const dataMonedas = {
            labels: [
                <?php 
                foreach($stats_monedas as $stat) {
                    echo '"' . $stat['moneda_pago'] . '",';
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach($stats_monedas as $stat) {
                        echo $stat['total_pagos'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#4CAF50',
                    '#2196F3',
                    '#ff9800',
                    '#f44336',
                    '#9C27B0',
                    '#00BCD4'
                ]
            }]
        };
        
        // Configuración del gráfico
        const configMonedas = {
            type: 'pie',
            data: dataMonedas,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Distribución de Pagos por Moneda'
                    }
                }
            }
        };
        
        // Crear el gráfico
        const chartMonedas = new Chart(
            document.getElementById('chartMonedas'),
            configMonedas
        );
    </script>
</body>
</html>