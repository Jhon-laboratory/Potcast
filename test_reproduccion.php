<?php
// test_reproduccion.php - Prueba simple de reproducción
require_once 'config.php';

// Obtener lista de archivos de la BD
function getArchivosPrueba() {
    $connInfo = [
        "Database" => DB_NAME,
        "UID" => DB_USER,
        "PWD" => DB_PASS,
        "CharacterSet" => "UTF-8"
    ];
    
    $conn = sqlsrv_connect(DB_HOST, $connInfo);
    if (!$conn) {
        die("Error de conexión");
    }
    
    $sql = "SELECT TOP 5 
                titulo, 
                guid, 
                tipo, 
                extension 
            FROM " . DB_SCHEMA . "." . DB_TABLE . " 
            WHERE activo = 1 
            ORDER BY fecha_subida DESC";
    
    $stmt = sqlsrv_query($conn, $sql);
    $archivos = [];
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $archivos[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    sqlsrv_close($conn);
    return $archivos;
}

$archivos = getArchivosPrueba();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test de Reproducción Simple</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .archivo { 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px;
            background: #f9f9f9;
        }
        .btn-reproducir {
            background: #009A3F;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-reproducir:hover { background: #007a32; }
        .guid { font-family: monospace; background: #eee; padding: 5px; border-radius: 3px; }
        .reproductor { margin-top: 20px; padding: 20px; background: #f5f5f5; border-radius: 5px; }
        audio, video { width: 100%; margin-top: 10px; }
        .error { color: red; padding: 10px; background: #ffeeee; border-radius: 5px; }
        .success { color: green; padding: 10px; background: #eeffee; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎵 Test de Reproducción Simple</h1>
        
        <?php if (empty($archivos)): ?>
            <div class="error">No hay archivos en la base de datos</div>
        <?php else: ?>
            <h3>Archivos disponibles:</h3>
            <?php foreach ($archivos as $archivo): ?>
                <div class="archivo">
                    <strong><?php echo htmlspecialchars($archivo['titulo']); ?></strong><br>
                    Tipo: <?php echo $archivo['tipo']; ?> (<?php echo $archivo['extension']; ?>)<br>
                    GUID: <span class="guid"><?php echo $archivo['guid']; ?></span><br>
                    <button class="btn-reproducir" onclick="reproducir('<?php echo $archivo['guid']; ?>', '<?php echo $archivo['tipo']; ?>')">
                        ▶ Reproducir
                    </button>
                    <button class="btn-reproducir" onclick="verificarURL('<?php echo $archivo['guid']; ?>')">
                        🔍 Verificar URL
                    </button>
                </div>
            <?php endforeach; ?>
            
            <div class="reproductor">
                <h3>Reproductor:</h3>
                <div id="reproductor-container">
                    <p class="info">Selecciona un archivo para reproducir</p>
                </div>
            </div>
            
            <div id="info" class="success" style="display:none;"></div>
            <div id="error" class="error" style="display:none;"></div>
        <?php endif; ?>
    </div>

    <script>
    function verificarURL(guid) {
        const url = `stream.php?guid=${guid}`;
        console.log('Verificando URL:', url);
        
        fetch(url, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    mostrarInfo(`✅ URL OK: ${url} (Status: ${response.status})`);
                } else {
                    mostrarError(`❌ URL Error: ${url} (Status: ${response.status})`);
                }
            })
            .catch(error => {
                mostrarError(`❌ Error: ${error.message}`);
            });
    }

    function reproducir(guid, tipo) {
        console.log('Intentando reproducir:', { guid, tipo });
        
        const url = `stream.php?guid=${guid}`;
        const container = document.getElementById('reproductor-container');
        
        if (tipo === 'video') {
            container.innerHTML = `
                <h4>Reproduciendo Video</h4>
                <video controls autoplay style="width:100%">
                    <source src="${url}" type="video/mp4">
                    Tu navegador no soporta video
                </video>
                <p><small>URL: ${url}</small></p>
            `;
        } else {
            container.innerHTML = `
                <h4>Reproduciendo Audio</h4>
                <audio controls autoplay style="width:100%">
                    <source src="${url}" type="audio/mpeg">
                    Tu navegador no soporta audio
                </audio>
                <p><small>URL: ${url}</small></p>
            `;
        }
        
        // Verificar si carga
        setTimeout(() => {
            const media = document.querySelector(tipo === 'video' ? 'video' : 'audio');
            if (media) {
                media.onerror = function(e) {
                    console.error('Error de reproducción:', e);
                    mostrarError('Error al reproducir: ' + (media.error ? media.error.message : 'desconocido'));
                };
                
                media.onloadeddata = function() {
                    console.log('✅ Media cargado correctamente');
                    mostrarInfo('✅ Reproduciendo correctamente');
                };
            }
        }, 500);
    }

    function mostrarInfo(msg) {
        const info = document.getElementById('info');
        info.style.display = 'block';
        info.innerHTML = msg;
        setTimeout(() => { info.style.display = 'none'; }, 3000);
    }

    function mostrarError(msg) {
        const error = document.getElementById('error');
        error.style.display = 'block';
        error.innerHTML = '❌ ' + msg;
        setTimeout(() => { error.style.display = 'none'; }, 3000);
    }
    </script>
</body>
</html>

https://softer-dateless-karis.ngrok-free.dev/6cb1bc2b-f00a-49e7-a12b-1e5c01744832.mp3