<?php
/**
 * PRUEBA DIRECTA DE LA API DE TOKENS
 * Sube este archivo a la RAÍZ de tu proyecto
 * Accede a: https://pagos.bividelosangeles.com/test_api_token.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test API Token</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🧪 Prueba de API de Tokens</h1>
    <hr>
    
    <h3>1. Verificar que la API existe:</h3>
    <?php
    $api_path = __DIR__ . '/api/enviar_token.php';
    if (file_exists($api_path)) {
        echo "<div class='result success'>✅ API encontrada en: $api_path</div>";
    } else {
        echo "<div class='result error'>❌ API NO encontrada. Ruta esperada: $api_path</div>";
        echo "<p>Verifica que el archivo exista en la carpeta /api/</p>";
        exit;
    }
    ?>
    
    <h3>2. Verificar tabla tokens_verificacion:</h3>
    <?php
    require_once 'includes/db_config.php';
    $conexion = conectarDB();
    
    $result = $conexion->query("SHOW TABLES LIKE 'tokens_verificacion'");
    if ($result->num_rows > 0) {
        echo "<div class='result success'>✅ Tabla tokens_verificacion existe</div>";
        
        // Mostrar estructura
        $estructura = $conexion->query("DESCRIBE tokens_verificacion");
        echo "<details><summary>Ver estructura de la tabla</summary><pre>";
        while ($row = $estructura->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "</pre></details>";
    } else {
        echo "<div class='result error'>❌ Tabla tokens_verificacion NO existe</div>";
        echo "<p>Ejecuta este SQL en phpMyAdmin:</p>";
        echo "<pre>CREATE TABLE tokens_verificacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(10) NOT NULL,
    expiracion DATETIME NOT NULL,
    intentos INT DEFAULT 0,
    usado TINYINT(1) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);</pre>";
    }
    ?>
    
    <h3>3. Verificar PHPMailer:</h3>
    <?php
    $phpmailer_files = [
        'includes/email_config.php',
        'includes/PHPMailer/PHPMailer.php',
        'includes/PHPMailer/SMTP.php',
        'includes/PHPMailer/Exception.php'
    ];
    
    $all_ok = true;
    foreach ($phpmailer_files as $file) {
        if (file_exists($file)) {
            echo "✅ $file<br>";
        } else {
            echo "❌ $file <strong>NO encontrado</strong><br>";
            $all_ok = false;
        }
    }
    
    if ($all_ok) {
        echo "<div class='result success'>✅ Todos los archivos de PHPMailer están presentes</div>";
    }
    ?>
    
    <hr>
    <h3>4. Probar envío de token:</h3>
    
    <form id="testForm">
        <label>Email a probar (debe existir en participantes):</label>
        <input type="email" id="email" value="prueba@gmail.com" required>
        <button type="submit">🚀 Enviar Token de Prueba</button>
    </form>
    
    <div id="resultado"></div>
    
    <hr>
    <h3>5. Registros en base de datos:</h3>
    <?php
    // Mostrar últimos tokens generados
    $tokens = $conexion->query("SELECT * FROM tokens_verificacion ORDER BY fecha_creacion DESC LIMIT 5");
    
    if ($tokens && $tokens->num_rows > 0) {
        echo "<table border='1' cellpadding='10' style='width:100%; border-collapse: collapse;'>";
        echo "<tr><th>Email</th><th>Token</th><th>Expiración</th><th>Usado</th><th>Fecha Creación</th></tr>";
        while ($row = $tokens->fetch_assoc()) {
            $expirado = strtotime($row['expiracion']) < time() ? '⏰ Expirado' : '✅ Válido';
            echo "<tr>
                    <td>{$row['email']}</td>
                    <td><strong>{$row['token']}</strong></td>
                    <td>{$row['expiracion']}<br><small>$expirado</small></td>
                    <td>" . ($row['usado'] ? '✅ Usado' : '❌ No usado') . "</td>
                    <td>{$row['fecha_creacion']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='result info'>ℹ️ No hay tokens registrados aún</div>";
    }
    
    $conexion->close();
    ?>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const resultDiv = document.getElementById('resultado');
            
            resultDiv.innerHTML = '<div class="result info">⏳ Enviando solicitud...</div>';
            
            try {
                const response = await fetch('api/enviar_token.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email: email })
                });
                
                const data = await response.json();
                
                console.log('Respuesta completa:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="result success">
                            <h4>✅ ${data.message}</h4>
                            ${data.debug_token ? `<p><strong>Token generado:</strong> ${data.debug_token}</p>` : ''}
                            ${data.warning ? `<p>⚠️ ${data.warning}</p>` : ''}
                            <p>Revisa tu email (y la carpeta SPAM)</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h4>❌ Error</h4>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
                // Mostrar respuesta completa
                resultDiv.innerHTML += `
                    <details style="margin-top: 10px;">
                        <summary>Ver respuesta completa del servidor</summary>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </details>
                `;
                
                // Recargar después de 3 segundos para ver tabla actualizada
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h4>❌ Error de conexión</h4>
                        <p>${error.message}</p>
                        <p>Verifica que la ruta de la API sea correcta</p>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>