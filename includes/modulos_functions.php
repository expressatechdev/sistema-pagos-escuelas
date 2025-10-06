<?php
/**
 * FUNCIONES DE GESTIÓN DE MÓDULOS
 * Escuela del Sanador - Sistema de Pagos
 * V2.0 - Lógica de Calendario Flexible y Contabilidad Secuencial
 */

// ================================================================
// FUNCIONES DE CALENDARIO Y MÓDULOS (Lógica de Negocio)
// ================================================================

/**
 * Determina el Módulo Actual del Calendario (Basado en la fecha de los módulos, NO en el mes)
 * @param mysqli $conexion
 * @param string $escuela
 * @return int
 */
function obtenerModuloActualCalendario($conexion, $escuela) {
    // La consulta busca el módulo con la fecha más reciente que no exceda el día de hoy.
    $sql = "SELECT COALESCE(MAX(m.numero_modulo), 1) as modulo_actual
            FROM modulos_escuela m
            INNER JOIN calendarios_escuelas c ON m.calendario_id = c.id
            WHERE c.escuela = ? AND c.activo = 1 AND m.activo = 1
            AND m.fecha_modulo <= CURDATE()";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $row = $resultado->fetch_assoc();
    return $row['modulo_actual'] ?? 1;
}

/**
 * Obtiene el estado de cuenta detallado y resumido para el participante.
 * Depende del procedimiento almacenado para calcular los totales.
 * @param mysqli $conexion
 * @param int $participante_id
 * @param string $escuela
 * @return array
 */
function obtenerEstadoCuentaCompleto($conexion, $participante_id, $escuela) {
    // NOTA: La llamada al procedimiento de cálculo se realiza ahora
    // en el evento de registro de pagos y en el módulo de inicialización,
    // NO aquí, ya que el cálculo es complejo. Asumimos que los datos están al día.
    
    // 1. Obtener resumen (estado_cuenta)
    $sql = "SELECT ec.*, i.precio_modulo
            FROM estado_cuenta ec
            INNER JOIN inscripciones i ON i.participante_id = ec.participante_id AND i.escuela = ec.escuela
            WHERE ec.participante_id = ? AND ec.escuela = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $resumen = $resultado->fetch_assoc();
    $stmt->close();
    
    // 2. Obtener detalle por módulos (estado_cuenta_modulos)
    // Se agregan m.escuela a la unión, asumiendo que modulos_escuela tiene un campo 'escuela' o se vincula por calendario_id
    $sql_modulos = "SELECT ecm.*, m.nombre_modulo, m.arcangeles, m.fecha_modulo
                    FROM estado_cuenta_modulos ecm
                    LEFT JOIN modulos_escuela m ON m.numero_modulo = ecm.numero_modulo AND m.escuela = ecm.escuela
                    WHERE ecm.participante_id = ? AND ecm.escuela = ?
                    ORDER BY ecm.numero_modulo ASC";
    
    $stmt = $conexion->prepare($sql_modulos);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $resultado_modulos = $stmt->get_result();
    
    $modulos = [];
    while ($row = $resultado_modulos->fetch_assoc()) {
        // Asegurar que el estado es PAGADO si se ha pagado más del precio original
        if ($row['total_pagado'] >= $row['precio_modulo'] && $row['estado'] !== 'PAGADO') {
             $row['estado'] = 'PAGADO';
        }
        $modulos[] = $row;
    }
    $stmt->close();
    
    return [
        'resumen' => $resumen,
        'modulos' => $modulos
    ];
}

/**
 * Formatea el estado de un módulo con colores e iconos para la UI (Requerimiento 7)
 * @param string $estado
 * @return array
 */
function formatearEstadoModulo($estado) {
    // Los estados son los que debe guardar el Procedimiento Almacenado
    $estados = [
        'INACTIVO' => ['texto' => 'Inscripción no activa', 'icono' => '🚫', 'color' => '#6c757d'],
        'NO_INICIADO' => ['texto' => 'No Iniciado', 'icono' => '⏳', 'color' => '#9E9E9E'],
        'ADEUDO' => ['texto' => 'Pendiente Total', 'icono' => '❌', 'color' => '#f44336'], // Deuda total
        'PARCIAL' => ['texto' => 'Pago Parcial', 'icono' => '⚠️', 'color' => '#ff9800'], // Deuda parcial
        'PAGADO' => ['texto' => 'Pagado', 'icono' => '✅', 'color' => '#4CAF50'],
        'VENCIDO' => ['texto' => 'Vencido', 'icono' => '🔴', 'color' => '#d32f2f'] // Se mantiene VENCIDO para claridad
    ];
    
    return $estados[$estado] ?? ['texto' => $estado, 'icono' => '●', 'color' => '#666'];
}

/**
 * Funciòn para inicializar los módulos de participantes que se inscriben tarde (Requerimiento 9)
 * Solo se inicializa desde el Módulo Actual del Calendario.
 * @param mysqli $conexion
 * @param int $participante_id
 * @param string $escuela
 * @param float $precio_modulo
 * @return bool
 */
function inicializarModulosInscripcionTardia($conexion, $participante_id, $escuela, $precio_modulo) {
    // 1. Obtener el módulo actual del calendario (el módulo de inicio para el nuevo participante)
    $modulo_inicio = obtenerModuloActualCalendario($conexion, $escuela);

    // 2. Insertar solo los módulos a partir del Módulo de Inicio
    $sql = "INSERT INTO estado_cuenta_modulos (participante_id, escuela, numero_modulo, precio_modulo, total_pagado, total_pendiente, estado)
            SELECT ?, ?, m.numero_modulo, ?, 0, ?, 'NO_INICIADO'
            FROM modulos_escuela m
            WHERE m.escuela = ?
              AND m.numero_modulo >= ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isdsi", $participante_id, $escuela, $precio_modulo, $precio_modulo, $escuela, $modulo_inicio);

    if ($stmt->execute()) {
        // Inicializar el resumen del estado de cuenta (ec)
        $sql_ec = "INSERT INTO estado_cuenta (participante_id, escuela, modulo_actual, total_adeudado) 
                   VALUES (?, ?, ?, 0) 
                   ON DUPLICATE KEY UPDATE modulo_actual = VALUES(modulo_actual)";
        $stmt_ec = $conexion->prepare($sql_ec);
        $stmt_ec->bind_param("isi", $participante_id, $escuela, $modulo_inicio);
        $stmt_ec->execute();
        
        return true;
    }

    return false;
}

// ================================================================
// FUNCIÓN OBSOLETA (sp_calcular_estado_cuenta) SE REMUEVE PARA USAR
// SOLO EL NUEVO PROCEDIMIENTO SQL.
// La función original inicializarEstadosCuentaExistentes TAMBIÉN es obsoleta,
// ya que el nuevo procedimiento de aplicación actualiza la lógica.
// ================================================================

?>