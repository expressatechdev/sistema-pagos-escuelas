<?php
session_start();

// IMPORTANTE: Establecer zona horaria ANTES de cualquier cálculo de fecha
date_default_timezone_set('America/Caracas');

require_once dirname(__DIR__) . '/includes/db_config.php';
require_once dirname(__DIR__) . '/includes/email_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
$reenviar = $input['reenviar'] ?? false;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit();
}

if (!$reenviar) {
    if (isset($_SESSION['token_attempts'][$email])) {
        $lastAttempt = $_SESSION['token_attempts'][$email]['time'];
        $attempts = $_SESSION['token_attempts'][$email]['count'];
        
        if (time() - $lastAttempt < 60 && $attempts >= 3) {
            echo json_encode([
                'success' => false, 
                'message' => 'Demasiados intentos. Espere 1 minuto.'
            ]);
            exit();
        }
    }
}

try {
    $conexion = conectarDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

$sql = "SELECT id, nombre, apellido, activo FROM participantes WHERE email = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Email no registrado. Contacte a su productora.'
    ]);
    $conexion->close();
    exit();
}

$participante = $resultado->fetch_assoc();

if (!$participante['activo']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Su cuenta está inactiva. Contacte a su productora.'
    ]);
    $conexion->close();
    exit();
}

// Generar token
$token = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

// IMPORTANTE: Usar NOW() de MySQL para evitar problemas de zona horaria
$sql = "INSERT INTO tokens_verificacion (email, token, expiracion, usado) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
        ON DUPLICATE KEY UPDATE 
        token = VALUES(token), 
        expiracion = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
        usado = 0";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $email, $token);
$stmt->execute();

// Guardar en sesión
$_SESSION['verification_token'] = [
    'email' => $email,
    'token' => $token,
    'expiration' => time() + 300,
    'participant_id' => $participante['id'],
    'nombre' => $participante['nombre'],
    'apellido' => $participante['apellido']
];

if (!isset($_SESSION['token_attempts'][$email])) {
    $_SESSION['token_attempts'][$email] = ['count' => 0, 'time' => 0];
}
$_SESSION['token_attempts'][$email]['count']++;
$_SESSION['token_attempts'][$email]['time'] = time();

// Preparar email
$nombreCompleto = $participante['nombre'] . ' ' . $participante['apellido'];
$asunto = "🔐 Tu código de verificación - Escuela del Sanador";

$mensaje = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #4CAF50, #2196F3); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>✨ Escuela del Sanador</h1>
            <p style='color: white; margin: 10px 0 0 0;'>Sistema de Pagos</p>
        </div>
        
        <div style='background: white; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;'>
            <h2 style='color: #333; margin-bottom: 20px;'>¡Hola, $nombreCompleto!</h2>
            
            <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                Tu código de verificación es:
            </p>
            
            <div style='background: #f5f5f5; border: 2px dashed #4CAF50; padding: 20px; text-align: center; margin: 30px 0; border-radius: 10px;'>
                <span style='font-size: 42px; font-weight: bold; color: #333; letter-spacing: 12px;'>
                    $token
                </span>
            </div>
            
            <p style='color: #666; font-size: 14px; line-height: 1.6;'>
                <strong>⏱️ Este código expira en 5 minutos</strong><br>
                No compartas este código con nadie.
            </p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #999; font-size: 12px; text-align: center;'>
                    Si no solicitaste este código, ignora este mensaje.<br>
                    © " . date('Y') . " Escuela del Sanador
                </p>
            </div>
        </div>
    </div>
";

$email_enviado = enviarEmailPHPMailer($email, $asunto, $mensaje);

$conexion->close();

echo json_encode([
    'success' => true, 
    'message' => 'Código enviado a tu email',
    'debug_info' => [
        'email_enviado' => $email_enviado,
        'token_generado' => $token // ELIMINAR EN PRODUCCIÓN
    ]
]);
?>