<?php
// config.php - Configuración centralizada
session_start();

// URLs
define('NGROK_URL', 'https://softer-dateless-karis.ngrok-free.dev');
define('SITE_URL', 'http://localhost/proyecto');

// Rutas
define('MEDIA_PATH', 'D:/MediaServer/audios/');

// Base de datos
define('DB_HOST', 'Jorgeserver.database.windows.net');
define('DB_NAME', 'DPL');
define('DB_USER', 'Jmmc');
define('DB_PASS', 'ChaosSoldier01');
define('DB_SCHEMA', 'externos');
define('DB_TABLE', 'medios');  // La nueva tabla