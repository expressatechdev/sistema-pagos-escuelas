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

// 1. Obtener todos los calendarios activos
$sql_calendarios = "SELECT id, escuela FROM calendarios_escuelas WHERE activo = 1 ORDER BY escuela";
$resultado_calendarios = $conexion->query($sql_calendarios);
$lista_calendarios = [];
while ($row = $resultado_calendarios->fetch_assoc()) {
    $lista_calendarios[] = $row;
}

// 2. Determinar qué calendario cargar (usar GET para selección, o el primero por defecto)
$calendario_id_a_cargar = intval($_REQUEST['calendario_id'] ?? 0);
$calendario_seleccionado = null;

if ($calendario_id_a_cargar === 0 && !empty($lista_calendarios)) {
    // Si no se especifica, carga el primero de la lista
    $calendario_id_a_cargar = $lista_calendarios[0]['id'];
}

// 3. Cargar datos del calendario seleccionado
if ($calendario_id_a_cargar > 0) {
    foreach ($lista_calendarios as $cal) {
        if ($cal['id'] === $calendario_id_a_cargar) {
            $calendario_seleccionado = $cal;
            break;
        }
    }
}

// Inicialización de variables de contexto
$escuela_actual = $calendario_seleccionado['escuela'] ?? '';
$calendario_id = $calendario_id_a_cargar;


// Procesar actualización de módulos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $calendario_seleccionado) {
    $modulos = $_POST['modulos'] ?? [];
    
    // Asegurar que estamos guardando para el calendario correcto, no para otro accidentalmente
    if (intval($_POST['calendario_id_post']) !== $calendario_id) {
         $mensaje = "Error de seguridad: ID de calendario incorrecto en el formulario.";
         $tipo_mensaje = 'danger';
    } else {
        $conexion->begin_transaction();
        $exito = true;

        try {
            foreach ($modulos as $num_modulo => $datos) {
                $fecha_modulo = limpiarDato($datos['fecha_modulo']);
                $nombre_modulo = limpiarDato($datos['nombre_modulo']);
                $arcangeles = limpiarDato($datos['arcangeles']);
                
                // Insertar o Actualizar módulo
                // Usamos INSERT IGNORE si el índice ya existe para evitar errores, pero ON DUPLICATE KEY es más robusto.
                $sql = "INSERT INTO modulos_escuela (calendario_id, escuela, numero_modulo, fecha_modulo, nombre_modulo, arcangeles, activo)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            fecha_modulo = VALUES(fecha_modulo),
                            nombre_modulo = VALUES(nombre_modulo),
                            arcangeles = VALUES(arcangeles),
                            activo = 1";
                
                $stmt = $conexion->prepare($sql);
                // Nota: Usamos $escuela_actual aquí.
                $stmt->bind_param("iissss", $calendario_id, $escuela_actual, $num_modulo, $fecha_modulo, $nombre_modulo, $arcangeles);
                
                if (!$stmt->execute()) {
                    $exito = false;
                    throw new Exception("Error al actualizar Módulo $num_modulo: " . $stmt->error);
                }
            }

            if ($exito) {
                $conexion->commit();
                $mensaje = "Calendario de Módulos actualizado exitosamente para $escuela_actual.";
                $tipo_mensaje = 'success';
                
                registrarActividad($conexion, 'admin', $_SESSION['usuario_id'], 
                    'ACTUALIZAR_CALENDARIO', "Calendario ID: $calendario_id ($escuela_actual) actualizado.");
            }

        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "Error al guardar el calendario: " . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener los módulos actuales para la escuela seleccionada
$modulos_db = [];
if ($calendario_seleccionado) {
    $sql_modulos = "SELECT numero_modulo, fecha_modulo, nombre_modulo, arcangeles
                    FROM modulos_escuela
                    WHERE calendario_id = ?
                    ORDER BY numero_modulo ASC";
    $stmt_modulos = $conexion->prepare($sql_modulos);
    $stmt_modulos->bind_param("i", $calendario_id);
    $stmt_modulos->execute();
    $resultado_modulos = $stmt_modulos->get_result();

    while ($row = $resultado_modulos->fetch_assoc()) {
        $modulos_db[$row['numero_modulo']] = $row;
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Calendario - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --dark-color: #2c3e50;
            --light-bg: #f8f9fa;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--light-bg); padding: 20px; }
        .form-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto; }
        .form-header { color: var(--dark-color); border-bottom: 2px solid var(--light-bg); margin-bottom: 20px; padding-bottom: 10px; }
        .module-row { border-left: 5px solid var(--primary-color); background: var(--light-bg); padding: 15px; margin-bottom: 15px; border-radius: 8px; }
        .module-row h4 { color: var(--dark-color); margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-card">
        <div class="form-header">
            <h2><i class="fas fa-calendar-alt"></i> Gestión de Calendario</h2>
            <p>Define las fechas exactas, el nombre y los Arcángeles de los 9 módulos.</p>
        </div>

        <div class="mb-4">
            <label for="select_calendario" class="form-label">Seleccionar Escuela/Calendario</label>
            <select id="select_calendario" class="form-select" onchange="window.location.href='gestionar_calendario.php?calendario_id=' + this.value">
                <?php if (empty($lista_calendarios)): ?>
                    <option value="">No hay calendarios activos</option>
                <?php else: ?>
                    <?php foreach ($lista_calendarios as $cal): ?>
                        <option value="<?php echo $cal['id']; ?>" 
                                <?php echo $cal['id'] === $calendario_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cal['escuela'] . ' (ID: ' . $cal['id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($calendario_seleccionado): ?>
            <h3 class="mb-3 text-primary"><?php echo htmlspecialchars($escuela_actual); ?></h3>
        <form method="POST" action="">
            <input type="hidden" name="calendario_id_post" value="<?php echo $calendario_id; ?>">

            <?php for ($i = 1; $i <= 9; $i++): 
                $datos = $modulos_db[$i] ?? [
                    'fecha_modulo' => '', 
                    'nombre_modulo' => 'Módulo ' . $i, 
                    'arcangeles' => ''
                ];
            ?>
            <div class="module-row">
                <h4>Módulo <?php echo $i; ?></h4>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha de Ejecución/Vencimiento</label>
                        <input type="date" 
                               name="modulos[<?php echo $i; ?>][fecha_modulo]" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($datos['fecha_modulo']); ?>"
                               required>
                        <small class="text-muted">Día exacto en que se ejecuta y vence el pago.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nombre del Módulo</label>
                        <input type="text" 
                               name="modulos[<?php echo $i; ?>][nombre_modulo]" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($datos['nombre_modulo']); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Arcángeles / Comentario</label>
                        <input type="text" 
                               name="modulos[<?php echo $i; ?>][arcangeles]" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($datos['arcangeles']); ?>">
                    </div>
                </div>
            </div>
            <?php endfor; ?>
            
            <div class="d-flex justify-content-end mt-4">
                <a href="dashboard_admin.php" class="btn btn-secondary me-2">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Calendario
                </button>
            </div>
        </form>
        <?php else: ?>
             <div class="alert alert-warning text-center">
                 <i class="fas fa-exclamation-triangle"></i> No se encontró un calendario activo para gestionar.
             </div>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="dashboard_admin.php" class="btn btn-link">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>