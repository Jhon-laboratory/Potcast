<?php
// debug_stream.php - Debug para ver qué GUID llega
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Stream</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Debug de Stream</h1>";

// Verificar GUID
$guid = $_GET['guid'] ?? '';

echo "<h2>GUID recibido:</h2>";
if (empty($guid)) {
    echo "<p class='error'>❌ GUID vacío</p>";
} else {
    echo "<p><strong>Valor:</strong> " . htmlspecialchars($guid) . "</p>";
    echo "<p><strong>Longitud:</strong> " . strlen($guid) . " caracteres</p>";
    echo "<p><strong>Formato GUID:</strong> " . (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid) ? '✅ Válido' : '❌ Inválido') . "</p>";
}

// Conectar a BD y buscar
echo "<h2>Búsqueda en Base de Datos:</h2>";

try {
    $connInfo = [
        "Database" => DB_NAME,
        "UID" => DB_USER,
        "PWD" => DB_PASS,
        "CharacterSet" => "UTF-8"
    ];
    
    $conn = sqlsrv_connect(DB_HOST, $connInfo);
    
    if (!$conn) {
        $errors = sqlsrv_errors();
        echo "<p class='error'>❌ Error de conexión: " . ($errors[0]['message'] ?? 'Desconocido') . "</p>";
    } else {
        echo "<p class='success'>✅ Conexión a BD exitosa</p>";
        
        // Buscar por GUID exacto
        $sql = "SELECT nombre_archivo, titulo, tipo, extension 
                FROM " . DB_SCHEMA . "." . DB_TABLE . " 
                WHERE guid = ? AND activo = 1";
        
        $params = [$guid];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            echo "<p class='error'>❌ Error en consulta: " . ($errors[0]['message'] ?? 'Desconocido') . "</p>";
        } else {
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                echo "<p class='success'>✅ Archivo encontrado:</p>";
                echo "<pre>";
                echo "Nombre archivo: " . $row['nombre_archivo'] . "\n";
                echo "Título: " . $row['titulo'] . "\n";
                echo "Tipo: " . $row['tipo'] . "\n";
                echo "Extensión: " . $row['extension'] . "\n";
                echo "URL completa: " . NGROK_URL . "/" . $row['nombre_archivo'] . "\n";
                echo "</pre>";
                
                // Probar acceso al archivo
                echo "<h3>Prueba de acceso al archivo:</h3>";
                $file_url = NGROK_URL . "/" . $row['nombre_archivo'];
                
                $ch = curl_init($file_url);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code >= 200 && $http_code < 300) {
                    echo "<p class='success'>✅ Archivo accesible (HTTP $http_code)</p>";
                    echo "<p><a href='$file_url' target='_blank'>Abrir archivo directamente</a></p>";
                } else {
                    echo "<p class='error'>❌ Archivo no accesible (HTTP $http_code)</p>";
                }
                
            } else {
                echo "<p class='error'>❌ No se encontró archivo con GUID: " . htmlspecialchars($guid) . "</p>";
                
                // Mostrar algunos GUIDs de ejemplo
                $sql2 = "SELECT TOP 5 guid, titulo FROM " . DB_SCHEMA . "." . DB_TABLE . " WHERE activo = 1";
                $stmt2 = sqlsrv_query($conn, $sql2);
                if ($stmt2) {
                    echo "<p>GUIDs disponibles en BD:</p><ul>";
                    while ($row2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
                        echo "<li><strong>" . $row2['guid'] . "</strong> - " . htmlspecialchars($row2['titulo']) . "</li>";
                    }
                    echo "</ul>";
                    sqlsrv_free_stmt($stmt2);
                }
            }
            sqlsrv_free_stmt($stmt);
        }
        sqlsrv_close($conn);
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Excepción: " . $e->getMessage() . "</p>";
}

echo "<h2>Verificar qué GUID envía main.php:</h2>";
echo "<p>Agrega este código al onclick de una tarjeta en main.php:</p>";
echo "<pre>
onclick=\"console.log('GUID:', '<?php echo \$media['guid']; ?>'); debugStream('<?php echo \$media['guid']; ?>')\"

Y esta función en el JavaScript:
function debugStream(guid) {
    window.open('debug_stream.php?guid=' + guid, '_blank');
}
</pre>";

echo "</div></body></html>";
?>