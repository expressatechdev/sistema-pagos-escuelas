<?php
/**
 * CONFIGURACIÓN DE BASE DE DATOS
 * Sistema de Pagos - Escuela del Sanador
 * Subdominio: pagos.bividelosangeles.com
 */

// Configuración de errores (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ====================================
// CONFIGURACIÓN DE BASE DE DATOS - TUS DATOS DE HOSTINGER
// ====================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'u367875829_escuela_pagos');
define('DB_USER', 'u367875829_epagos');
define('DB_PASS', 'GustaBivi.1');  // RECUERDA CAMBIAR ESTA CONTRASEÑA DESPUÉS

// ====================================
// CONFIGURACIÓN DE LA APLICACIÓN
// ====================================
define('SITE_URL', 'https://pagos.bividelosangeles.com');
define('UPLOAD_DIR', dirname(__FILE__) . '/../uploads/comprobantes/');
define('UPLOAD_URL', 'https://pagos.bividelosangeles.com/uploads/comprobantes/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB máximo por archivo

// Zona horaria Venezuela
date_default_timezone_set('America/Caracas');

// ====================================
// FUNCIÓN DE CONEXIÓN A BASE DE DATOS
// ====================================
function conectarDB() {
    try {
        $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Verificar conexión
        if ($conexion->connect_error) {
            throw new Exception("Error de conexión: " . $conexion->connect_error);
        }
        
        // Establecer charset a UTF-8
        $conexion->set_charset("utf8mb4");
        
        return $conexion;
        
    } catch (Exception $e) {
        // En producción, no mostrar detalles del error
        die("Error al conectar con la base de datos. Por favor, contacte al administrador.");
    }
}

// ====================================
// FUNCIONES DE SEGURIDAD
// ====================================

/**
 * Limpiar datos de entrada
 */
function limpiarDato($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

/**
 * Validar email
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generar token único
 */
function generarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Encriptar contraseña
 */
function encriptarPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña
 */
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Subir archivo de comprobante
 */
function subirComprobante($archivo) {
    // Verificar si hay error
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['exito' => false, 'mensaje' => 'Error al subir el archivo'];
    }
    
    // Verificar tamaño
    if ($archivo['size'] > MAX_UPLOAD_SIZE) {
        return ['exito' => false, 'mensaje' => 'El archivo es muy grande (máximo 5MB)'];
    }
    
    // Verificar tipo de archivo
    $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipoArchivo = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($tipoArchivo, $tiposPermitidos)) {
        return ['exito' => false, 'mensaje' => 'Solo se permiten imágenes (JPG, PNG, GIF) o PDF'];
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = date('Ymd_His') . '_' . uniqid() . '.' . $extension;
    $rutaCompleta = UPLOAD_DIR . $nombreArchivo;
    
    // Crear directorio si no existe
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return ['exito' => true, 'archivo' => $nombreArchivo];
    } else {
        return ['exito' => false, 'mensaje' => 'Error al guardar el archivo'];
    }
}

/**
 * Enviar email - Usando PHPMailer si está disponible
 */
function enviarEmail($para, $asunto, $mensaje) {
    // Intentar usar PHPMailer si existe
    if (file_exists(dirname(__FILE__) . '/email_config.php')) {
        require_once dirname(__FILE__) . '/email_config.php';
        return enviarEmailPHPMailer($para, $asunto, $mensaje);
    }
    
    // Fallback a mail() nativo
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Sistema de Pagos <noreply@bividelosangeles.com>' . "\r\n";
    $headers .= 'Reply-To: gtomasif@gmail.com' . "\r\n";
    
    // Template HTML para el email
    $mensajeHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>$asunto</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #4CAF50; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0;'>Escuela del Sanador</h1>
            </div>
            <div style='background-color: #f4f4f4; padding: 20px; margin-top: 0;'>
                $mensaje
            </div>
            <div style='text-align: center; padding: 20px; color: #666; font-size: 12px;'>
                <p>Sistema de Pagos - Escuela del Sanador<br>
                <a href='https://pagos.bividelosangeles.com'>pagos.bividelosangeles.com</a></p>
            </div>
        </div>
    </body>
    </html>";
    
    return mail($para, $asunto, $mensajeHTML, $headers);
}

/**
 * Registrar actividad en log
 */
function registrarActividad($conexion, $tipo_usuario, $usuario_id, $accion, $descripcion = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO log_actividades (usuario_tipo, usuario_id, accion, descripcion, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sissss", $tipo_usuario, $usuario_id, $accion, $descripcion, $ip, $user_agent);
    $stmt->execute();
}

/**
 * Obtener tasa de cambio actual
 */
function obtenerTasaCambio($conexion, $moneda) {
    $sql = "SELECT tasa FROM tasas_cambio WHERE moneda = ? AND activa = 1 ORDER BY fecha_actualizacion DESC LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $moneda);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($row = $resultado->fetch_assoc()) {
        return $row['tasa'];
    }
    return null;
}

/**
 * Formatear moneda
 */
function formatearMoneda($cantidad, $moneda = 'USD') {
    switch($moneda) {
        case 'USD':
            return '$' . number_format($cantidad, 2, '.', ',');
        case 'BS':
            return 'Bs. ' . number_format($cantidad, 2, ',', '.');
        case 'EUR':
            return '€' . number_format($cantidad, 2, ',', '.');
        default:
            return number_format($cantidad, 2, '.', ',');
    }
}

/**
 * Obtener estado de cuenta del participante
 */
function obtenerEstadoCuenta($conexion, $participante_id, $escuela) {
    $sql = "SELECT ec.*, i.precio_modulo 
            FROM estado_cuenta ec 
            INNER JOIN inscripciones i ON i.participante_id = ec.participante_id AND i.escuela = ec.escuela
            WHERE ec.participante_id = ? AND ec.escuela = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $participante_id, $escuela);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($row = $resultado->fetch_assoc()) {
        return $row;
    }
    
    // Si no existe estado de cuenta, crear uno nuevo
    $sql_insert = "INSERT INTO estado_cuenta (participante_id, escuela) VALUES (?, ?)";
    $stmt_insert = $conexion->prepare($sql_insert);
    $stmt_insert->bind_param("is", $participante_id, $escuela);
    $stmt_insert->execute();
    
    // Obtener precio del módulo
    $sql_precio = "SELECT precio_modulo FROM inscripciones WHERE participante_id = ? AND escuela = ?";
    $stmt_precio = $conexion->prepare($sql_precio);
    $stmt_precio->bind_param("is", $participante_id, $escuela);
    $stmt_precio->execute();
    $resultado_precio = $stmt_precio->get_result();
    $precio_row = $resultado_precio->fetch_assoc();
    
    return [
        'participante_id' => $participante_id,
        'escuela' => $escuela,
        'modulo_actual' => 1,
        'saldo_favor' => 0,
        'total_pagado' => 0,
        'total_adeudado' => $precio_row['precio_modulo'] ?? 0,
        'precio_modulo' => $precio_row['precio_modulo'] ?? 0
    ];
}

/**
 * Iniciar sesión segura
 */
function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Regenerar ID de sesión periódicamente
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

/**
 * Verificar si usuario está autenticado
 */
function estaAutenticado($tipo = null) {
    iniciarSesionSegura();
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tipo_usuario'])) {
        return false;
    }
    
    if ($tipo && $_SESSION['tipo_usuario'] !== $tipo) {
        return false;
    }
    
    return true;
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    iniciarSesionSegura();
    $_SESSION = array();
    session_destroy();
    header("Location: " . SITE_URL);
    exit();
}

// ====================================
// MENSAJES DE NOTIFICACIÓN PARA LA UI
// ====================================
function mostrarMensaje($tipo, $mensaje) {
    $clases = [
        'exito' => 'alert-success',
        'error' => 'alert-danger',
        'advertencia' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $clase = $clases[$tipo] ?? 'alert-info';
    
    return "
    <div class='alert $clase alert-dismissible fade show' role='alert'>
        $mensaje
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

// ====================================
// TEST DE CONEXIÓN
// ====================================
// Descomenta esta línea para probar la conexión:
// $test = conectarDB();
// if($test) echo "Conexión exitosa!";

?>