<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Test Verificar Token - Diagnóstico</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 20px auto; padding: 20px; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h2>🔍 Diagnóstico de verificar_token.php</h2>
    <hr>

    <h3>1. Contenido del archivo:</h3>
    <?php
    $file = 'api/verificar_token.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        echo "<div class='success'>✅ Archivo encontrado con " . count($lines) . " líneas</div>";
        
        // Mostrar primeras 20 líneas
        echo "<p><strong>Primeras 20 líneas:</strong></p>";
        echo "<pre>";
        for ($i = 0; $i < min(20, count($lines)); $i++) {
            $line_num = $i + 1;
            echo str_pad($line_num, 3, ' ', STR_PAD_LEFT) . ": " . htmlspecialchars($lines[$i]) . "\n";
        }
        echo "</pre>";
        
        // Verificar problemas comunes
        echo "<h3>2. Verificación de problemas:</h3>";
        
        // BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            echo "<div class='error'>❌ El archivo tiene BOM (Byte Order Mark). Esto causa errores.</div>";
        } else {
            echo "<div class='success'>✅ Sin BOM</div>";
        }
        
        // Espacios antes de <?php
        if (preg_match('/^\s+/', $content)) {
            echo "<div class='error'>❌ Hay espacios o saltos de línea antes de &lt;?php</div>";
        } else {
            echo "<div class='success'>✅ No hay espacios antes de &lt;?php</div>";
        }
        
        // Verificar si empieza con <?php
        if (!preg_match('/^<\?php/', $content)) {
            echo "<div class='error'>❌ El archivo NO empieza con &lt;?php</div>";
            echo "<p>Los primeros 50 caracteres:</p>";
            echo "<pre>" . htmlspecialchars(substr($content, 0, 50)) . "</pre>";
        } else {
            echo "<div class='success'>✅ Empieza correctamente con &lt;?php</div>";
        }
        
    } else {
        echo "<div class='error'>❌ Archivo NO encontrado: $file</div>";
    }
    ?>

    <h3>3. Probar ejecución directa:</h3>
    <div class='info'>
        <p>Vamos a ejecutar el archivo simulando una petición POST</p>
    </div>

    <?php
    // Simular request POST
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Crear archivo temporal con datos JSON
    $tempInput = tmpfile();
    $tempPath = stream_get_meta_data($tempInput)['uri'];
    fwrite($tempInput, json_encode([
        'token' => '1234',
        'email' => 'gtomasifnew@gmail.com'
    ]));
    fseek($tempInput, 0);
    
    echo "<p><strong>Datos enviados:</strong></p>";
    echo "<pre>" . json_encode(['token' => '1234', 'email' => 'gtomasifnew@gmail.com'], JSON_PRETTY_PRINT) . "</pre>";
    
    // Capturar output
    ob_start();
    
    // Guardar $_SERVER original
    $originalServer = $_SERVER;
    
    try {
        // Redirigir php://input al archivo temporal
        include 'api/verificar_token.php';
        
        $output = ob_get_clean();
        
        echo "<p><strong>Respuesta del servidor:</strong></p>";
        echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        echo "</div>";
        
        // Intentar decodificar como JSON
        $decoded = json_decode($output, true);
        if ($decoded !== null) {
            echo "<div class='success'>✅ Respuesta JSON válida:</div>";
            echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='error'>❌ La respuesta NO es JSON válido</div>";
            echo "<p>Error JSON: " . json_last_error_msg() . "</p>";
            
            // Mostrar caracteres no imprimibles
            echo "<p>Primeros 100 bytes (incluyendo caracteres ocultos):</p>";
            echo "<pre>";
            for ($i = 0; $i < min(100, strlen($output)); $i++) {
                $char = $output[$i];
                $ord = ord($char);
                if ($ord < 32 || $ord > 126) {
                    echo "[" . $ord . "]";
                } else {
                    echo htmlspecialchars($char);
                }
            }
            echo "</pre>";
        }
        
    } catch (Throwable $e) {
        $error = ob_get_clean();
        echo "<div class='error'>";
        echo "<p><strong>❌ Error al ejecutar:</strong></p>";
        echo "<pre>" . htmlspecialchars($error) . "</pre>";
        echo "<p><strong>Excepción:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
        echo "</div>";
    }
    
    // Restaurar $_SERVER
    $_SERVER = $originalServer;
    fclose($tempInput);
    ?>

    <hr>
    <h3>4. Solución sugerida:</h3>
    <div class='info'>
        <p><strong>Si ves errores arriba, haz esto:</strong></p>
        <ol>
            <li>Borra completamente el archivo <code>api/verificar_token.php</code></li>
            <li>Crea uno nuevo desde cero</li>
            <li>Copia el código limpio (sin espacios antes de &lt;?php)</li>
            <li>Guarda como UTF-8 sin BOM</li>
        </ol>
        <p>O usa el archivo simple que te di antes en el artifact "verificar_token_simple"</p>
    </div>

    <hr>
    <a href="index_fixed.html" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Volver a Index
    </a>
</body>
</html>