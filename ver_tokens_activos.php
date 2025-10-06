<?php
require_once 'includes/db_config.php';

$conexion = conectarDB();

echo "<h2>🔍 Tokens Activos en Base de Datos</h2>";
echo "<hr>";

$sql = "SELECT 
        id,
        email, 
        token, 
        expiracion,
        usado,
        fecha_creacion,
        CASE 
            WHEN expiracion < NOW() THEN 'EXPIRADO'
            WHEN usado = 1 THEN 'USADO'
            ELSE 'VÁLIDO'
        END as estado,
        TIMESTAMPDIFF(SECOND, NOW(), expiracion) as segundos_restantes
        FROM tokens_verificacion 
        WHERE email = 'gtomasifnew@gmail.com'
        ORDER BY id DESC 
        LIMIT 10";

$resultado = $conexion->query($sql);

if ($resultado->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #4CAF50; color: white;'>
            <th>ID</th>
            <th>Email</th>
            <th>TOKEN</th>
            <th>Expiración</th>
            <th>Usado</th>
            <th>Estado</th>
            <th>Tiempo Restante</th>
            <th>Creación</th>
          </tr>";
    
    while ($row = $resultado->fetch_assoc()) {
        $bg = '';
        if ($row['estado'] == 'VÁLIDO') {
            $bg = 'background: #d4edda;';
        } elseif ($row['estado'] == 'EXPIRADO') {
            $bg = 'background: #f8d7da;';
        } else {
            $bg = 'background: #e2e3e5;';
        }
        
        $tiempo = $row['segundos_restantes'];
        $tiempo_texto = $tiempo > 0 ? gmdate("i:s", $tiempo) . " min" : "Expirado";
        
        echo "<tr style='$bg'>
                <td>{$row['id']}</td>
                <td>{$row['email']}</td>
                <td><strong style='font-size: 20px; color: #333;'>{$row['token']}</strong></td>
                <td>{$row['expiracion']}</td>
                <td>" . ($row['usado'] ? '✅ Sí' : '❌ No') . "</td>
                <td><strong>{$row['estado']}</strong></td>
                <td>$tiempo_texto</td>
                <td>{$row['fecha_creacion']}</td>
              </tr>";
    }
    
    echo "</table>";
    
    // Buscar el token válido actual
    $sql_valido = "SELECT token FROM tokens_verificacion 
                   WHERE email = 'gtomasifnew@gmail.com' 
                   AND usado = 0 
                   AND expiracion > NOW() 
                   ORDER BY id DESC LIMIT 1";
    $result_valido = $conexion->query($sql_valido);
    
    if ($result_valido->num_rows > 0) {
        $token_valido = $result_valido->fetch_assoc()['token'];
        echo "<div style='background: #d4edda; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center;'>";
        echo "<h3>✅ TOKEN VÁLIDO ACTUAL:</h3>";
        echo "<p style='font-size: 48px; font-weight: bold; letter-spacing: 10px; color: #333;'>$token_valido</p>";
        echo "<p>Usa este código en el formulario</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
        echo "<p>⚠️ No hay tokens válidos activos. Solicita uno nuevo en el formulario.</p>";
        echo "</div>";
    }
    
} else {
    echo "<p>No hay tokens registrados para este email.</p>";
}

$conexion->close();

echo "<hr>";
echo "<a href='index_fixed.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Volver al Portal</a>";
?>