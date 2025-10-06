<?php
/**
 * Cerrar Sesión PARTICIPANTES - Sistema de Pagos
 * Este archivo es SOLO para participantes
 * Ubicación: /logout_participante.php
 */

session_start();

// Si hay una sesión de PARTICIPANTE activa, registrar el cierre
if (isset($_SESSION['participante_id']) && isset($_SESSION['participante_autenticado'])) {
    require_once 'includes/db_config.php';
    
    $conexion = conectarDB();
    
    // Registrar actividad de cierre de sesión
    registrarActividad(
        $conexion, 
        'participante', 
        $_SESSION['participante_id'], 
        'LOGOUT', 
        'Cierre de sesión participante'
    );
    
    $conexion->close();
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al inicio de PARTICIPANTES
header("Location: index.php?msg=logout");
exit();
?>