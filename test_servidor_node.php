<?php
// test_servidor_node.php - Verificar servidor Node.js
require_once 'config.php';

function testearServidorNode() {
    $resultados = [];
    
    // Test 1: Verificar que el servidor responde
    $ch = curl_init(NGROK_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $resultados['servidor'] = $http_code;
    
    // Test 2: Verificar endpoint de subida
    $ch = curl_init(NGROK_URL . '/upload');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $http_code_upload = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $resultados['upload'] = $http_code_upload;
    
    return $resultados;
}

// Obtener un archivo de la BD para probar
function getArchivoPrueba() {
    $connInfo = [
        "Database" => DB_NAME,
        "UID" => DB_USER,
        "PWD" => DB_PASS,
        "CharacterSet" => "UTF-8"
    ];
    
    $conn = sqlsrv_connect(DB_HOST, $connInfo);
    if (!$conn) return null;
    
    $sql = "SELECT TOP 1 nombre_archivo, guid FROM " . DB_SCHEMA . "." . DB_TABLE . " WHERE activo = 1 ORDER BY fecha_subida DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $archivo = null;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $archivo = $row;
    }
    sqlsrv_close($conn);
    return $archivo;
}

$test = testearServidorNode();
$archivo = getArchivoPrueba();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Servidor Node.js</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .success { color: green; background: #eeffee; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #ffeeee; padding: 10px; border-radius: 5px; }
        .warning { color: orange; background: #fff3e0; padding: 10px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        .btn { 
            background: #009A3F; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test del Servidor Node.js</h1>
        
        <h2>Configuración:</h2>
        <pre>
NGROK_URL: <?php echo NGROK_URL; ?>
        </pre>
        
        <h2>Tests de Conexión:</h2>
        
        <div>
            <strong>Servidor principal:</strong>
            <?php if ($test['servidor'] >= 200 && $test['servidor'] < 300): ?>
                <span class="success">✅ OK (HTTP <?php echo $test['servidor']; ?>)</span>
            <?php else: ?>
                <span class="error">❌ Error (HTTP <?php echo $test['servidor']; ?>)</span>
            <?php endif; ?>
        </div>
        
        <div>
            <strong>Endpoint /upload:</strong>
            <?php if ($test['upload'] >= 200 && $test['upload'] < 300): ?>
                <span class="success">✅ OK (HTTP <?php echo $test['upload']; ?>)</span>
            <?php else: ?>
                <span class="error">❌ Error (HTTP <?php echo $test['upload']; ?>)</span>
            <?php endif; ?>
        </div>
        
        <?php if ($archivo): ?>
            <h2>Prueba con archivo existente:</h2>
            <p><strong>Archivo:</strong> <?php echo $archivo['nombre_archivo']; ?></p>
            <p><strong>GUID:</strong> <?php echo $archivo['guid']; ?></p>
            
            <h3>Verificar archivo en ngrok:</h3>
            <div id="resultadoArchivo"></div>
            
            <button class="btn" onclick="verificarArchivo('<?php echo $archivo['nombre_archivo']; ?>')">
                🔍 Verificar archivo
            </button>
            
            <button class="btn" onclick="verificarStream('<?php echo $archivo['guid']; ?>')">
                🎵 Verificar stream
            </button>
            
            <button class="btn" onclick="listarArchivos()">
                📋 Listar archivos en servidor
            </button>
            
            <div id="debugInfo" style="margin-top:20px;"></div>
        <?php endif; ?>
        
        <h2>Instrucciones para el servidor Node.js:</h2>
        <div class="warning">
            <p>El servidor Node.js debe:</p>
            <ol>
                <li>Estar corriendo en: <strong>http://localhost:3000</strong></li>
                <li>Tener ngrok corriendo: <strong>ngrok http 3000</strong></li>
                <li>Guardar archivos en: <strong>D:/MediaServer/audios/</strong></li>
                <li>Tener el endpoint <strong>/upload</strong> que acepte archivos</li>
                <li>Tener el endpoint <strong>/files</strong> que liste archivos</li>
                <li>Servir archivos estáticos desde la carpeta de audios</li>
            </ol>
        </div>
        
        <h2>Código necesario para server.js:</h2>
        <pre>
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const app = express();

// Configurar carpeta de destino
const AUDIOS_PATH = 'D:/MediaServer/audios/';

// Configurar multer para subida de archivos
const storage = multer.diskStorage({
    destination: function (req, file, cb) {
        cb(null, AUDIOS_PATH);
    },
    filename: function (req, file, cb) {
        cb(null, file.originalname);
    }
});
const upload = multer({ storage: storage });

// Servir archivos estáticos
app.use(express.static(AUDIOS_PATH));

// Endpoint para subir archivos
app.post('/upload', upload.single('file'), (req, res) => {
    res.json({ 
        success: true, 
        filename: req.file.filename,
        originalname: req.file.originalname,
        size: req.file.size,
        path: req.file.path
    });
});

// Endpoint para listar archivos
app.get('/files', (req, res) => {
    fs.readdir(AUDIOS_PATH, (err, files) => {
        if (err) {
            res.status(500).json({ error: err.message });
        } else {
            res.json({ files });
        }
    });
});

app.listen(3000, () => {
    console.log('Servidor corriendo en puerto 3000');
    console.log('Carpeta de audios:', AUDIOS_PATH);
});
        </pre>
    </div>

    <script>
    function verificarArchivo(nombreArchivo) {
        const url = '<?php echo NGROK_URL; ?>/' + nombreArchivo;
        const div = document.getElementById('resultadoArchivo');
        const debug = document.getElementById('debugInfo');
        
        div.innerHTML = 'Verificando...';
        
        fetch(url, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    div.innerHTML = `<span class="success">✅ Archivo accesible (HTTP ${response.status})</span>`;
                } else {
                    div.innerHTML = `<span class="error">❌ Archivo no accesible (HTTP ${response.status})</span>`;
                }
                debug.innerHTML = `<pre>URL: ${url}\nStatus: ${response.status}\nHeaders: ${JSON.stringify([...response.headers], null, 2)}</pre>`;
            })
            .catch(error => {
                div.innerHTML = `<span class="error">❌ Error: ${error.message}</span>`;
                debug.innerHTML = `<pre>Error: ${error.message}</pre>`;
            });
    }

    function verificarStream(guid) {
        const url = 'stream.php?guid=' + guid;
        const div = document.getElementById('resultadoArchivo');
        const debug = document.getElementById('debugInfo');
        
        div.innerHTML = 'Verificando stream...';
        
        fetch(url, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    div.innerHTML = `<span class="success">✅ Stream funciona (HTTP ${response.status})</span>`;
                } else {
                    div.innerHTML = `<span class="error">❌ Stream falla (HTTP ${response.status})</span>`;
                }
                debug.innerHTML = `<pre>URL: ${url}\nStatus: ${response.status}</pre>`;
            })
            .catch(error => {
                div.innerHTML = `<span class="error">❌ Error: ${error.message}</span>`;
                debug.innerHTML = `<pre>Error: ${error.message}</pre>`;
            });
    }

    function listarArchivos() {
        const url = '<?php echo NGROK_URL; ?>/files';
        const debug = document.getElementById('debugInfo');
        
        debug.innerHTML = 'Listando archivos...';
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                debug.innerHTML = `<pre>Archivos en servidor:\n${JSON.stringify(data, null, 2)}</pre>`;
            })
            .catch(error => {
                debug.innerHTML = `<pre>Error al listar: ${error.message}</pre>`;
            });
    }
    </script>
</body>
</html>