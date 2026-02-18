<?php
// upload_handler.php - VERSIÓN PHP 7.4 COMPATIBLE con permisos de usuario
require_once 'config.php';

header('Content-Type: application/json');

// ===== CONFIGURACIÓN DE SESIÓN PARA PHP 7.4 =====
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'cookie_lifetime' => 0,
        'use_strict_mode' => true
    ]);
}

// ===== VERIFICACIÓN DE SESIÓN Y PERMISOS =====
// Verificar que el usuario está logueado
if (!isset($_SESSION['id_user'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'No autorizado - Debe iniciar sesión'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Definir usuarios autorizados para subir contenido (SOLO 131 y 29)
$usuarios_autorizados = [131, 29];

// Verificar si el usuario actual tiene permiso
if (!in_array($_SESSION['id_user'], $usuarios_autorizados)) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'No tienes permiso para subir contenido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log de acceso para auditoría (opcional)
error_log("Usuario ID " . $_SESSION['id_user'] . " con permiso de subida - Acceso concedido");

// ===== CONFIGURACIÓN DE SUBIDA PARA ARCHIVOS GRANDES =====
// Aumentar límites de PHP para manejar archivos grandes
ini_set('upload_max_filesize', '1024M');  // 1GB
ini_set('post_max_size', '1024M');        // 1GB
ini_set('memory_limit', '1024M');         // 1GB
ini_set('max_execution_time', '3600');    // 1 hora
ini_set('max_input_time', '3600');        // 1 hora

// Función mejorada de conexión con manejo de errores para PHP 7.4
function connectDB() {
    try {
        // Validar que las constantes existan
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            throw new Exception('Constantes de configuración no definidas');
        }
        
        $connInfo = [
            "Database" => DB_NAME,
            "UID" => DB_USER,
            "PWD" => DB_PASS,
            "CharacterSet" => "UTF-8",
            "ReturnDatesAsStrings" => true,
            "ConnectionPooling" => true,
            "Encrypt" => true,
            "TrustServerCertificate" => false,
            "LoginTimeout" => 30
        ];
        
        $conn = sqlsrv_connect(DB_HOST, $connInfo);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            $error_msg = is_array($errors) && isset($errors[0]['message']) 
                ? $errors[0]['message'] 
                : 'Error desconocido de conexión';
            
            error_log('Error SQLSRV connect: ' . $error_msg);
            return [
                'success' => false, 
                'error' => 'Error de conexión a base de datos'
            ];
        }
        
        return [
            'success' => true, 
            'conn' => $conn
        ];
        
    } catch (Exception $e) {
        error_log('Excepción en connectDB: ' . $e->getMessage());
        return [
            'success' => false, 
            'error' => 'Error interno del servidor'
        ];
    }
}

// Función para limpiar strings (evitar problemas con caracteres especiales)
function limpiarString($str) {
    if ($str === null || $str === '') return '';
    
    // Eliminar caracteres de control que pueden causar problemas en PHP 7.4
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    
    // Limitar longitud
    return mb_substr(trim($str), 0, 255, 'UTF-8');
}

// Función principal mejorada con manejo de archivos grandes
function handleUpload() {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'success' => false, 
            'error' => 'Método no permitido'
        ];
    }
    
    // Validar archivo con manejo de errores detallado
    if (!isset($_FILES['mediaFile'])) {
        return [
            'success' => false, 
            'error' => 'No se recibió ningún archivo'
        ];
    }
    
    $file = $_FILES['mediaFile'];
    
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión'
        ];
        
        $error_msg = isset($uploadErrors[$file['error']]) 
            ? $uploadErrors[$file['error']] 
            : 'Error desconocido al subir archivo (código: ' . $file['error'] . ')';
        
        return [
            'success' => false, 
            'error' => $error_msg
        ];
    }
    
    // Validar título
    $titulo = limpiarString($_POST['titulo'] ?? '');
    if (empty($titulo)) {
        return [
            'success' => false, 
            'error' => 'El título es requerido'
        ];
    }
    
    $descripcion = limpiarString($_POST['descripcion'] ?? '');
    
    // Validar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validos = ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'webm', 'mp3', 'wav', 'ogg', 'm4a', 'flac'];
    
    if (!in_array($extension, $validos, true)) {
        return [
            'success' => false, 
            'error' => 'Extensión no válida. Permitidas: ' . implode(', ', $validos)
        ];
    }
    
    // Validar tamaño (1GB máximo - AUMENTADO)
    $maxSize = 1024 * 1024 * 1024; // 1GB en bytes
    if ($file['size'] > $maxSize) {
        $sizeGB = round($file['size'] / (1024 * 1024 * 1024), 2);
        return [
            'success' => false, 
            'error' => "El archivo no debe exceder 1GB. Tamaño actual: {$sizeGB}GB"
        ];
    }
    
    // Validar tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $videoMimes = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska', 'video/x-ms-wmv', 'video/webm'];
    $audioMimes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/flac', 'audio/x-m4a'];
    
    // Determinar tipo
    $tipo = 'audio';
    if (in_array($mime_type, $videoMimes, true)) {
        $tipo = 'video';
    } elseif (in_array($mime_type, $audioMimes, true)) {
        $tipo = 'audio';
    } else {
        // Si no podemos determinar por MIME, usar la extensión
        $tipo = in_array($extension, ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'webm']) ? 'video' : 'audio';
    }
    
    // Generar GUID compatible con PHP 7.4
    $guid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $nombre_archivo = $guid . '.' . $extension;
    
    // 1. Subir a ngrok con manejo de errores mejorado y timeout extendido
    $ch = curl_init();
    if ($ch === false) {
        return [
            'success' => false, 
            'error' => 'Error al inicializar CURL'
        ];
    }
    
    $curl_file = new CURLFile($file['tmp_name'], $file['type'], $nombre_archivo);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => NGROK_URL . '/upload',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['file' => $curl_file],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3600,        // 1 hora para archivos grandes
        CURLOPT_CONNECTTIMEOUT => 120,    // 2 minutos para conectar
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'PHP 7.4 Upload Handler',
        CURLOPT_BUFFERSIZE => 131072,     // 128KB buffer para transferencia
        CURLOPT_PROGRESSFUNCTION => function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
            // Log de progreso cada 10% (opcional)
            static $lastProgress = 0;
            if ($upload_size > 0) {
                $progress = round($uploaded / $upload_size * 100);
                if ($progress - $lastProgress >= 10) {
                    error_log("Progreso de subida: {$progress}%");
                    $lastProgress = $progress;
                }
            }
        },
        CURLOPT_NOPROGRESS => false      // Habilitar función de progreso
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log('Error CURL: ' . $curl_error);
        return [
            'success' => false, 
            'error' => 'Error de conexión con el servidor: ' . $curl_error
        ];
    }
    
    if ($http_code !== 200) {
        error_log('HTTP Error: ' . $http_code . ' - Response: ' . $response);
        error_log('CURL Info: ' . print_r($curl_info, true));
        return [
            'success' => false, 
            'error' => "Error al subir archivo al servidor (HTTP {$http_code})"
        ];
    }
    
    // 2. Registrar en BD
    $db = connectDB();
    if (!$db['success']) {
        return $db;
    }
    
    $conn = $db['conn'];
    
    // Usar transacción para asegurar consistencia
    sqlsrv_begin_transaction($conn);
    
    try {
        $sql = "INSERT INTO " . DB_SCHEMA . "." . DB_TABLE . " 
                (nombre_archivo, titulo, descripcion, tipo, extension, tamanio, guid, vistas, likes, activo, fecha_subida, subido_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, GETDATE(), ?)";
        
        $params = [
            $nombre_archivo,
            $titulo,
            $descripcion,
            $tipo,
            $extension,
            $file['size'],
            $guid,
            $_SESSION['id_user'] // Registrar qué usuario subió el archivo
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_msg = is_array($errors) && isset($errors[0]['message']) 
                ? $errors[0]['message'] 
                : 'Error desconocido en BD';
            
            throw new Exception($error_msg);
        }
        
        sqlsrv_commit($conn);
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        // Log de éxito con información del usuario
        error_log("Usuario ID " . $_SESSION['id_user'] . " subió archivo: " . $nombre_archivo . " - GUID: " . $guid . " - Tamaño: " . round($file['size'] / (1024*1024), 2) . "MB");
        
        return [
            'success' => true,
            'message' => 'Archivo subido correctamente',
            'guid' => $guid,
            'titulo' => $titulo,
            'tipo' => $tipo,
            'extension' => $extension,
            'nombre_archivo' => $nombre_archivo,
            'tamaño_mb' => round($file['size'] / (1024 * 1024), 2),
            'url' => 'stream.php?guid=' . $guid
        ];
        
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        sqlsrv_close($conn);
        
        error_log('Error en BD: ' . $e->getMessage());
        
        return [
            'success' => false, 
            'error' => 'Error al guardar en base de datos'
        ];
    }
}

// ===== EJECUCIÓN PRINCIPAL =====
try {
    $resultado = handleUpload();
    
    // PHP 7.4 requiere especificar opciones JSON para caracteres Unicode
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log('Error fatal en upload_handler: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>
