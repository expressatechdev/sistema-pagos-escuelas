<?php
/**
 * CONFIGURACIÓN DE EMAIL CON PHPMAILER
 * Sistema de Pagos - Escuela del Sanador
 */

// Incluir PHPMailer
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Configuración SMTP
 * Configurado para el correo: contacto@bividelosangeles.com
 */
define('SMTP_HOST', 'smtp.hostinger.com');       // Servidor SMTP de Hostinger
define('SMTP_PORT', 587);                        // Puerto TLS
define('SMTP_SECURE', 'tls');                    // Seguridad TLS
define('SMTP_AUTH', true);                       // Autenticación SMTP
define('SMTP_USERNAME', 'contacto@bividelosangeles.com');  // Email completo
define('SMTP_PASSWORD', 'GustaBivi.1');          // Contraseña
define('SMTP_FROM_EMAIL', 'contacto@bividelosangeles.com'); // Email remitente
define('SMTP_FROM_NAME', 'Escuela del Sanador - Sistema de Pagos'); // Nombre remitente

/**
 * Función mejorada para enviar emails con PHPMailer
 */
function enviarEmailPHPMailer($para, $asunto, $mensaje_html, $cc = null, $adjunto = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Configuración de charset
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatarios
        if (is_array($para)) {
            foreach ($para as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($para);
        }
        
        // CC si existe
        if ($cc) {
            if (is_array($cc)) {
                foreach ($cc as $email_cc) {
                    $mail->addCC($email_cc);
                }
            } else {
                $mail->addCC($cc);
            }
        }
        
        // Adjunto si existe
        if ($adjunto && file_exists($adjunto)) {
            $mail->addAttachment($adjunto);
        }
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        
        // Template HTML mejorado
        $template = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $asunto . '</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #4CAF50, #2196F3); padding: 30px; text-align: center;">
                                    <h1 style="color: white; margin: 0; font-size: 28px;">Escuela del Sanador</h1>
                                    <p style="color: white; margin: 10px 0 0 0; font-size: 16px;">Sistema de Pagos</p>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    ' . $mensaje_html . '
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">
                                    <p style="color: #6c757d; font-size: 14px; margin: 0;">
                                        © ' . date('Y') . ' Escuela del Sanador. Todos los derechos reservados.
                                    </p>
                                    <p style="color: #6c757d; font-size: 12px; margin: 10px 0 0 0;">
                                        <a href="https://pagos.bividelosangeles.com" style="color: #4CAF50; text-decoration: none;">pagos.bividelosangeles.com</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $mail->Body = $template;
        
        // Versión de texto plano
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $mensaje_html));
        
        // Enviar
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log del error (opcional)
        error_log("Error enviando email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Función wrapper para mantener compatibilidad
 * COMENTADA porque ya existe en db_config.php
 */
// function enviarEmail($para, $asunto, $mensaje) {
//     return enviarEmailPHPMailer($para, $asunto, $mensaje);
// }

/**
 * Función para enviar emails múltiples (admin, productora, participante)
 */
function enviarEmailsNotificacion($datos_pago) {
    $emails_enviados = [];
    
    // Email al administrador
    $admin_email = 'gtomasif@gmail.com';
    $emails_enviados['admin'] = enviarEmailPHPMailer(
        $admin_email,
        $datos_pago['asunto_admin'],
        $datos_pago['mensaje_admin']
    );
    
    // Email a la productora (si existe)
    if (!empty($datos_pago['productora_email'])) {
        $emails_enviados['productora'] = enviarEmailPHPMailer(
            $datos_pago['productora_email'],
            $datos_pago['asunto_productora'],
            $datos_pago['mensaje_productora']
        );
    }
    
    // Email al participante
    if (!empty($datos_pago['participante_email'])) {
        $emails_enviados['participante'] = enviarEmailPHPMailer(
            $datos_pago['participante_email'],
            $datos_pago['asunto_participante'],
            $datos_pago['mensaje_participante']
        );
    }
    
    return $emails_enviados;
}

/**
 * Función de prueba
 */
function probarEmail() {
    $test = enviarEmailPHPMailer(
        'gtomasif@gmail.com',
        'Prueba de PHPMailer',
        '<h2>¡Funciona!</h2><p>PHPMailer está configurado correctamente.</p>'
    );
    
    if ($test) {
        echo "✅ Email de prueba enviado correctamente";
    } else {
        echo "❌ Error al enviar email de prueba";
    }
}

// Descomenta para probar:
// probarEmail();
?>