<?php
/**
 * FUNCIONES DE GESTIÓN DE MÓDULOS
 * Escuela del Sanador - Sistema de Pagos
 */

// ================================================================
// FUNCIONES DE CALENDARIO Y MÓDULOS
// ================================================================

function obtenerCalendarioEscuela($conexion, $escuela) {
    $sql = "SELECT * FROM calendarios_escuelas WHERE escuela = ? AND activo = 1 LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    return $resultado->fetch_assoc();
}

function obtenerModulosEscuela($conexion, $escuela) {
    $sql = "SELECT m.* 
            FROM modulos_escuela m
            INNER JOIN calendarios_escuelas c ON m.calendario_id = c.id
            WHERE c.escuela = ? AND c.activo = 1 AND m.activo = 1
            ORDER BY m.numero_modulo ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $modulos = [];
    while ($row = $resultado->fetch_assoc()) {
        $modulos[] = $row;
    }
    return $modulos;
}

function obtenerModuloActualCalendario($conexion, $escuela) {
    $sql = "SELECT COALESCE(MAX(numero_modulo), 1) as modulo_actual
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

function obtenerEstadoCuentaCompleto($conexion, $participante_id, $escuela) {
    // Primero calcular estado
    $sql_call = "CALL sp_calcular_estado_cuenta(?, ?)";
    $stmt = $conexion->prepare($sql_call);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $stmt->close();
    
    // Obtener resumen
    $sql = "SELECT ec.*, i.precio_modulo
            FROM estado_cuenta ec
            INNER JOIN inscripciones i ON i.participante_id = ec.participante_id AND i.escuela = ec.escuela
            WHERE ec.participante_id = ? AND ec.escuela = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $resumen = $resultado->fetch_assoc();
    
    // Obtener detalle por módulos
    $sql_modulos = "SELECT ecm.*, m.nombre_modulo, m.arcangeles, m.fecha_modulo
                    FROM estado_cuenta_modulos ecm
                    LEFT JOIN modulos_escuela m ON m.numero_modulo = ecm.numero_modulo
                    LEFT JOIN calendarios_escuelas c ON c.escuela = ecm.escuela AND m.calendario_id = c.id
                    WHERE ecm.participante_id = ? AND ecm.escuela = ?
                    ORDER BY ecm.numero_modulo ASC";
    
    $stmt = $conexion->prepare($sql_modulos);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $resultado_modulos = $stmt->get_result();
    
    $modulos = [];
    while ($row = $resultado_modulos->fetch_assoc()) {
        $modulos[] = $row;
    }
    
    return [
        'resumen' => $resumen,
        'modulos' => $modulos
    ];
}

function formatearEstadoModulo($estado) {
    $estados = [
        'NO_INICIADO' => ['texto' => 'No Iniciado', 'icono' => '⏳', 'color' => '#9E9E9E'],
        'PENDIENTE' => ['texto' => 'Pendiente', 'icono' => '❌', 'color' => '#f44336'],
        'PARCIAL' => ['texto' => 'Parcial', 'icono' => '⚠️', 'color' => '#ff9800'],
        'PAGADO' => ['texto' => 'Pagado', 'icono' => '✅', 'color' => '#4CAF50'],
        'VENCIDO' => ['texto' => 'Vencido', 'icono' => '🔴', 'color' => '#d32f2f']
    ];
    
    return $estados[$estado] ?? ['texto' => $estado, 'icono' => '●', 'color' => '#666'];
}

function inicializarEstadosCuentaExistentes($conexion) {
    $sql = "SELECT DISTINCT p.id, i.escuela
            FROM participantes p
            INNER JOIN inscripciones i ON p.id = i.participante_id
            WHERE p.activo = 1 AND i.activa = 1";
    
    $resultado = $conexion->query($sql);
    $contador = 0;
    
    while ($row = $resultado->fetch_assoc()) {
        $sql_call = "CALL sp_calcular_estado_cuenta(?, ?)";
        $stmt = $conexion->prepare($sql_call);
        $stmt->bind_param("is", $row['id'], $row['escuela']);
        $stmt->execute();
        $stmt->close();
        $contador++;
    }
    
    return $contador;
}

?>