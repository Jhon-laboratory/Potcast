<?php
// verificar_server.php - Verificar código del servidor
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificar Servidor Node.js</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .error { color: red; background: #ffeeee; padding: 10px; border-radius: 5px; }
        .success { color: green; background: #eeffee; padding: 10px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        .btn { background: #009A3F; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Verificar Servidor Node.js</h1>
        
        <h2>Probar diferentes endpoints:</h2>
        
        <button class="btn" onclick="testEndpoint('/')">Probar raíz (/)</button>
        <button class="btn" onclick="testEndpoint('/upload')">Probar /upload</button>
        <button class="btn" onclick="testEndpoint('/files')">Probar /files</button>
        <button class="btn" onclick="testEndpoint('/api/files')">Probar /api/files</button>
        <button class="btn" onclick="testEndpoint('/media')">Probar /media</button>
        
        <div id="resultado" style="margin-top:20px;"></div>
        
        <h2>Instrucciones para corregir server.js:</h2>
        
        <div class="error">
            <strong>⚠️ Problema detectado:</strong> El endpoint /upload no existe (HTTP 404)
        </div>
        
        <h3>📝 Código CORRECTO para server.js:</h3>
        <pre>
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const app = express();

// Configuración
const AUDIOS_PATH = 'D:/MediaServer/audios/';
const PORT = 3000;

// Middleware para evitar la página de advertencia de ngrok
app.use((req, res, next) => {
    res.setHeader('ngrok-skip-browser-warning', 'true');
    next();
});

// Servir archivos estáticos
app.use(express.static(AUDIOS_PATH));

// Configurar multer para subida
const storage = multer.diskStorage({
    destination: (req, file, cb) => cb(null, AUDIOS_PATH),
    filename: (req, file, cb) => cb(null, file.originalname)
});
const upload = multer({ storage });

// Endpoint raíz
app.get('/', (req, res) => {
    res.json({ 
        message: 'Servidor multimedia funcionando',
        status: 'ok',
        time: new Date().toISOString()
    });
});

// Endpoint de subida (CORREGIDO)
app.post('/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No se recibió archivo' });
    }
    
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
            return res.status(500).json({ error: err.message });
        }
        res.json({ files });
    });
});

// Endpoint para verificar archivo específico
app.get('/check/:filename', (req, res) => {
    const filepath = path.join(AUDIOS_PATH, req.params.filename);
    fs.access(filepath, fs.constants.F_OK, (err) => {
        if (err) {
            res.status(404).json({ exists: false });
        } else {
            res.json({ exists: true, filename: req.params.filename });
        }
    });
});

app.listen(PORT, () => {
    console.log(`✅ Servidor corriendo en http://localhost:${PORT}`);
    console.log(`📁 Carpeta de archivos: ${AUDIOS_PATH}`);
    console.log(`🌐 ngrok URL: https://softer-dateless-karis.ngrok-free.dev`);
});
        </pre>
        
        <h3>🔄 Pasos para actualizar:</h3>
        <ol>
            <li>Detén el servidor Node.js actual (Ctrl+C en la terminal)</li>
            <li>Reemplaza tu server.js con el código de arriba</li>
            <li>Reinicia el servidor: <code>node server.js</code></li>
            <li>Reinicia ngrok: <code>ngrok http 3000</code></li>
            <li>Prueba nuevamente</li>
        </ol>
        
        <h3>📊 Verificar después de actualizar:</h3>
        <button class="btn" onclick="verificarTodo()">Verificar todo</button>
    </div>

    <script>
    const NGROK_URL = 'https://softer-dateless-karis.ngrok-free.dev';
    
    async function testEndpoint(endpoint) {
        const resultado = document.getElementById('resultado');
        resultado.innerHTML = '⏳ Probando ' + endpoint + '...';
        
        try {
            const response = await fetch(NGROK_URL + endpoint, {
                method: endpoint === '/upload' ? 'OPTIONS' : 'GET',
                headers: {
                    'ngrok-skip-browser-warning': 'true'
                }
            });
            
            resultado.innerHTML = `
                <div class="${response.ok ? 'success' : 'error'}">
                    <strong>${endpoint}:</strong> HTTP ${response.status} ${response.statusText}<br>
                    ${response.ok ? '✅ OK' : '❌ Error'}
                </div>
            `;
        } catch (error) {
            resultado.innerHTML = `
                <div class="error">
                    <strong>${endpoint}:</strong> Error: ${error.message}
                </div>
            `;
        }
    }
    
    async function verificarTodo() {
        const resultado = document.getElementById('resultado');
        resultado.innerHTML = '⏳ Verificando...';
        
        const tests = ['/', '/upload', '/files', '/api/files', '/media'];
        let html = '<h3>Resultados:</h3>';
        
        for (const endpoint of tests) {
            try {
                const response = await fetch(NGROK_URL + endpoint, {
                    method: endpoint === '/upload' ? 'OPTIONS' : 'GET',
                    headers: { 'ngrok-skip-browser-warning': 'true' }
                });
                
                const status = response.ok ? '✅' : '❌';
                html += `<div>${status} ${endpoint}: HTTP ${response.status}</div>`;
            } catch (error) {
                html += `<div>❌ ${endpoint}: Error - ${error.message}</div>`;
            }
        }
        
        resultado.innerHTML = html;
    }
    </script>
</body>
</html>