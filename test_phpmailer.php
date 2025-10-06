<?php
/**
 * ARCHIVO DE PRUEBA PARA PHPMAILER
 * Sistema de Pagos - Escuela del Sanador
 */

// Mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Prueba de Configuración PHPMailer</h2>";
echo "<hr>";

// Verificar que existen los archivos de PHPMailer
echo "<h3>1. Verificación de Archivos:</h3>";
$archivos_requeridos = [
    'includes/PHPMailer/PHPMailer.php' => 'PHPMailer.php',
    'includes/PHPMailer/SMTP.php' => 'SMTP.php',
    'includes/PHPMailer/Exception.php' => 'Exception.php',
    'includes/email_config.php' => 'Configuración Email'
];

$archivos_ok = true;
foreach ($archivos_requeridos as $ruta => $nombre) {
    if (file_exists($ruta)) {
        echo "✅ $nombre encontrado<br>";
    } else {
        echo "❌ $nombre NO encontrado en: $ruta<br>";
        $archivos_ok = false;
    }
}

if (!$archivos_ok) {
    echo "<br><div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
    echo "<strong>Error:</strong> Faltan archivos de PHPMailer.<br>";
    echo "Por favor, descarga PHPMailer de: <a href='https://github.com/PHPMailer/PHPMailer' target='_blank'>https://github.com/PHPMailer/PHPMailer</a><br>";
    echo "Y sube los archivos a la carpeta: public_html/includes/PHPMailer/";
    echo "</div>";
    exit;
}

echo "<hr>";
echo "<h3>2. Configuración SMTP:</h3>";

// Incluir configuración
require_once 'includes/email_config.php';

echo "Host SMTP: " . SMTP_HOST . "<br>";
echo "Puerto: " . SMTP_PORT . "<br>";
echo "Seguridad: " . SMTP_SECURE . "<br>";
echo "Usuario: " . SMTP_USERNAME . "<br>";
echo "Desde: " . SMTP_FROM_EMAIL . "<br>";
echo "Nombre: " . SMTP_FROM_NAME . "<br>";

echo "<hr>";
echo "<h3>3. Prueba de Envío:</h3>";

// Si se envió el formulario
if (isset($_POST['enviar'])) {
    $email_destino = $_POST['email'] ?? 'gtomasif@gmail.com';
    
    echo "Enviando email de prueba a: <strong>$email_destino</strong><br><br>";
    
    // Preparar mensaje de prueba
    $mensaje_prueba = '
        <h2 style="color: #4CAF50;">¡PHPMailer Funciona Correctamente!</h2>
        <p>Este es un mensaje de prueba del Sistema de Pagos de la Escuela del Sanador.</p>
        <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>Información de la Prueba:</h3>
            <ul>
                <li><strong>Fecha:</strong> ' . date('Y-m-d H:i:s') . '</li>
                <li><strong>Servidor:</strong> ' . $_SERVER['SERVER_NAME'] . '</li>
                <li><strong>SMTP Host:</strong> ' . SMTP_HOST . '</li>
                <li><strong>Puerto:</strong> ' . SMTP_PORT . '</li>
            </ul>
        </div>
        <p>Si recibes este mensaje, significa que la configuración de email está funcionando correctamente.</p>
    ';
    
    // Intentar enviar
    $resultado = enviarEmailPHPMailer(
        $email_destino,
        '🔔 Prueba de PHPMailer - Sistema de Pagos',
        $mensaje_prueba
    );
    
    if ($resultado) {
        echo "<div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4CAF50;'>";
        echo "✅ <strong>¡Éxito!</strong> Email enviado correctamente a $email_destino<br>";
        echo "Revisa tu bandeja de entrada (y la carpeta de SPAM si no lo ves).";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
        echo "❌ <strong>Error al enviar el email.</strong><br>";
        echo "Posibles causas:<br>";
        echo "• Credenciales SMTP incorrectas<br>";
        echo "• Puerto bloqueado<br>";
        echo "• Servidor SMTP incorrecto<br>";
        echo "</div>";
    }
} else {
    // Mostrar formulario
    ?>
    <form method="POST" action="">
        <div style="background: #f5f5f5; padding: 20px; border-radius: 5px;">
            <label for="email">Email de destino:</label><br>
            <input type="email" 
                   name="email" 
                   id="email" 
                   value="gtomasif@gmail.com" 
                   style="padding: 10px; width: 300px; margin: 10px 0;"
                   required><br>
            <button type="submit" 
                    name="enviar" 
                    style="background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Enviar Email de Prueba
            </button>
        </div>
    </form>
    <?php
}

echo "<hr>";
echo "<h3>4. Prueba Alternativa con mail() nativo:</h3>";

if (isset($_POST['enviar_nativo'])) {
    $para = $_POST['email_nativo'] ?? 'gtomasif@gmail.com';
    $asunto = "Prueba mail() nativo";
    $mensaje = "Si recibes este mensaje, la función mail() nativa funciona.";
    $headers = "From: contacto@bividelosangeles.com\r\n";
    
    if (mail($para, $asunto, $mensaje, $headers)) {
        echo "✅ mail() nativo: Email enviado<br>";
    } else {
        echo "❌ mail() nativo: Error al enviar<br>";
    }
} else {
    ?>
    <form method="POST" action="">
        <div style="background: #fff3e0; padding: 20px; border-radius: 5px;">
            <label for="email_nativo">Email de destino (mail nativo):</label><br>
            <input type="email" 
                   name="email_nativo" 
                   id="email_nativo" 
                   value="gtomasif@gmail.com" 
                   style="padding: 10px; width: 300px; margin: 10px 0;"
                   required><br>
            <button type="submit" 
                    name="enviar_nativo" 
                    style="background: #ff9800; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Probar mail() Nativo
            </button>
        </div>
    </form>
    <?php
}

echo "<hr>";
echo "<div style='margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;'>";
echo "<h4>Notas importantes:</h4>";
echo "• Si PHPMailer no funciona, prueba cambiar el puerto a 465 y SMTP_SECURE a 'ssl'<br>";
echo "• Algunos servidores de Hostinger usan: smtp.titan.email<br>";
echo "• Asegúrate de que el email y contraseña sean correctos<br>";
echo "• El email puede tardar unos minutos en llegar<br>";
echo "• SIEMPRE revisa la carpeta de SPAM<br>";
echo "</div>";
?>

<div style="margin-top: 30px;">
    <a href="index.php" style="background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Volver al Sistema
    </a>
</div>