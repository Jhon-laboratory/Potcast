// server.js - Servidor Express para ngrok
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const app = express();

const MEDIA_PATH = 'D:/MediaServer/audios';

// Asegurar que el directorio existe
if (!fs.existsSync(MEDIA_PATH)) {
    fs.mkdirSync(MEDIA_PATH, { recursive: true });
}

// Configuración de multer para subida de archivos
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, MEDIA_PATH);
    },
    filename: (req, file, cb) => {
        // Preservar nombre original pero con timestamp para evitar colisiones
        const timestamp = Date.now();
        const originalName = file.originalname;
        cb(null, `${timestamp}_${originalName}`);
    }
});

const upload = multer({ 
    storage,
    limits: { fileSize: 500 * 1024 * 1024 } // 500MB
});

// Servir archivos estáticos
app.use(express.static(MEDIA_PATH));

// Endpoint de subida
app.post('/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No file uploaded' });
    }
    
    res.json({
        success: true,
        filename: req.file.filename,
        originalname: req.file.originalname,
        size: req.file.size,
        path: req.file.path,
        url: `https://${req.get('host')}/${req.file.filename}`
    });
});

// Endpoint para listar archivos (opcional)
app.get('/files', (req, res) => {
    fs.readdir(MEDIA_PATH, (err, files) => {
        if (err) {
            return res.status(500).json({ error: err.message });
        }
        
        const fileInfos = files.map(file => {
            const stat = fs.statSync(path.join(MEDIA_PATH, file));
            return {
                name: file,
                size: stat.size,
                modified: stat.mtime,
                url: `https://${req.get('host')}/${file}`
            };
        });
        
        res.json(fileInfos);
    });
});

app.listen(3000, () => {
    console.log('Media Gateway running on port 3000');
    console.log(`Serving files from: ${MEDIA_PATH}`);
});