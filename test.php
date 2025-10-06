<?php
// Archivo de prueba para verificar que las sesiones funcionan
session_start();

echo "<h2>Prueba de Sistema</h2>";
echo "<hr>";

// Probar sesiones
echo "<h3>1. Prueba de Sesiones:</h3>";
$_SESSION['test'] = 'Las sesiones funcionan!';
echo "Sesión creada: " . $_SESSION['test'] . "<br>";
echo "ID de sesión: " . session_id() . "<br>";

// Probar conexión a base de datos
echo "<hr>";
echo "<h3>2. Prueba de Base de Datos:</h3>";
require_once 'includes/db_config.php';

try {
    $conexion = conectarDB();
    echo "✅ Conexión a base de datos: OK<br>";
    
    // Contar participantes
    $result = $conexion->query("SELECT COUNT(*) as total FROM participantes");
    $row = $result->fetch_assoc();
    echo "Total de participantes en la BD: " . $row['total'] . "<br>";
    
    // Buscar el participante de prueba
    $email_prueba = 'prueba@gmail.com';
    $sql = "SELECT * FROM participantes WHERE email = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email_prueba);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        $participante = $resultado->fetch_assoc();
        echo "✅ Participante de prueba encontrado: " . $participante['nombre'] . " " . $participante['apellido'] . "<br>";
    } else {
        echo "❌ Participante de prueba NO encontrado<br>";
    }
    
    $conexion->close();
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}

// Verificar estructura de archivos
echo "<hr>";
echo "<h3>3. Verificación de Archivos:</h3>";
$archivos = [
    'index.php' => file_exists('index.php'),
    'verificar.php' => file_exists('verificar.php'),
    'includes/db_config.php' => file_exists('includes/db_config.php')
];

foreach ($archivos as $archivo => $existe) {
    echo $archivo . ": " . ($existe ? "✅ Existe" : "❌ No existe") . "<br>";
}

// Verificar variables de sesión actuales
echo "<hr>";
echo "<h3>4. Variables de Sesión Actuales:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Probar redirección
echo "<hr>";
echo "<h3>5. Prueba de Flujo:</h3>";
echo '<form method="POST" action="test.php">';
echo '<input type="email" name="email" placeholder="Ingresa un email" value="prueba@gmail.com">';
echo '<button type="submit">Probar Envío</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];
    $_SESSION['email_participante'] = $email;
    echo "<br>✅ Email guardado en sesión: " . $email;
    echo '<br><a href="verificar.php" style="padding: 10px; background: green; color: white; text-decoration: none; display: inline-block; margin-top: 10px;">Ir a verificar.php</a>';
}

echo "<hr>";
echo '<a href="index.php" style="padding: 10px; background: blue; color: white; text-decoration: none; display: inline-block;">Volver al Index</a>';
?>