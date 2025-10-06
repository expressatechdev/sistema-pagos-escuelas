<?php
session_start();
require_once dirname(__DIR__) . '/includes/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (empty($token) || strlen($token) !== 4) {
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit();
}

try {
    $conexion = conectarDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

$sql = "SELECT * FROM tokens_verificacion 
        WHERE email = ? AND token = ? AND usado = 0 AND expiracion > NOW()
        ORDER BY id DESC LIMIT 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Código incorrecto o expirado']);
    $conexion->close();
    exit();
}

$sql_participante = "SELECT id, nombre, apellido, email, activo 
                     FROM participantes 
                     WHERE email = ?";
$stmt_part = $conexion->prepare($sql_participante);
$stmt_part->bind_param("s", $email);
$stmt_part->execute();
$resultado_part = $stmt_part->get_result();

if ($resultado_part->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Participante no encontrado']);
    $conexion->close();
    exit();
}

$participante = $resultado_part->fetch_assoc();

if (!$participante['activo']) {
    echo json_encode(['success' => false, 'message' => 'Cuenta inactiva']);
    $conexion->close();
    exit();
}

$sql_update = "UPDATE tokens_verificacion SET usado = 1 WHERE email = ? AND token = ?";
$stmt_update = $conexion->prepare($sql_update);
$stmt_update->bind_param("ss", $email, $token);
$stmt_update->execute();

$_SESSION['participante_autenticado'] = true;
$_SESSION['participante_id'] = $participante['id'];
$_SESSION['participante_nombre'] = $participante['nombre'] . ' ' . $participante['apellido'];
$_SESSION['participante_email'] = $participante['email'];
$_SESSION['token_verificado_tiempo'] = time();

$sql_acceso = "UPDATE participantes SET ultimo_acceso = NOW() WHERE id = ?";
$stmt_acceso = $conexion->prepare($sql_acceso);
$stmt_acceso->bind_param("i", $participante['id']);
$stmt_acceso->execute();

$conexion->close();

echo json_encode([
    'success' => true, 
    'message' => 'Verificación exitosa',
    'redirect' => 'verificar.php'
]);
?>