<?php
/**
 * TEST DE VERIFICAR TOKEN - VER ERRORES
 */

// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de verificar_token.php</h2><hr>";

// 1. Verificar que el archivo existe
echo "<h3>1. Verificar archivo:</h3>";
$file = __DIR__ . '/api/verificar_token.php';
if (file_exists($file)) {
    echo "✅ Archivo existe: $file<br>";
} else {
    echo "❌ Archivo NO existe: $file<br>";
    die("<p>Crea el archivo primero</p>");
}

// 2. Verificar sintaxis del archivo
echo "<hr><h3>2. Verificar sintaxis PHP:</h3>";
$output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
echo "<pre>$output</pre>";

// 3. Simular request POST
echo "<hr><h3>3. Simular verificación de token:</h3>";

$_SERVER['REQUEST_METHOD'] = 'POST';

// Simular datos POST
$testData = [
    'token' => '1234',
    'email' => 'gtomasifnew@gmail.com'
];

echo "<p>Datos de prueba:</p>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Capturar output
ob_start();

// Simular input
$_SERVER['REQUEST_METHOD'] = 'POST';
file_put_contents('php://input', json_encode($testData));

try {
    include 'api/verificar_token.php';
    $output = ob_get_clean();
    
    echo "<p><strong>Respuesta del servidor:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Intentar decodificar JSON
    $json = json_decode($output, true);
    if ($json) {
        echo "<p>✅ JSON válido:</p>";
        echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p>❌ NO es JSON válido. Error: " . json_last_error_msg() . "</p>";
    }
    
} catch (Exception $e) {
    $error = ob_get_clean();
    echo "<p>❌ Error al ejecutar:</p>";
    echo "<pre>" . htmlspecialchars($error) . "</pre>";
    echo "<p>Excepción: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>4. Probar directamente con CURL:</h3>";
echo "<p>Ejecuta esto en tu terminal:</p>";
echo "<pre>curl -X POST https://pagos.bividelosangeles.com/api/verificar_token.php \\
  -H 'Content-Type: application/json' \\
  -d '{\"token\":\"1234\",\"email\":\"gtomasifnew@gmail.com\"}'</pre>";

echo "<hr>";
echo "<h3>5. Ver errores de PHP:</h3>";

// Intentar leer el archivo directamente
$content = file_get_contents('api/verificar_token.php');
$lines = explode("\n", $content);

echo "<p>Primeras 10 líneas del archivo:</p>";
echo "<pre>";
for ($i = 0; $i < min(10, count($lines)); $i++) {
    echo ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
}
echo "</pre>";

// Verificar BOM
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    echo "<p>⚠️ ADVERTENCIA: El archivo tiene BOM (Byte Order Mark) al inicio</p>";
}

// Verificar espacios antes de <?php
if (preg_match('/^\s+<\?php/', $content)) {
    echo "<p>⚠️ ADVERTENCIA: Hay espacios o saltos de línea antes de &lt;?php</p>";
}

echo "<hr>";
echo "<a href='index_fixed.html' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Volver al Test</a>";
?>