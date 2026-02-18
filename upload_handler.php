<?php
// upload_handler.php - VERSIÓN PHP 7.4 COMPATIBLE
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

// Verificar sesión (comentado para pruebas)
/*
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'No autorizado'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
*/
// ==============================================

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
            // PHP 7.4 compatible - verificar si errors es array
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

// Función principal mejorada
function handleUpload() {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'success' => false, 
            'error' => 'Método no permitido'
        ];
    }
    
    // Validar archivo con manejo de errores detallado para PHP 7.4
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
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión'
        ];
        
        $error_msg = isset($uploadErrors[$file['error']]) 
            ? $uploadErrors[$file['error']] 
            : 'Error desconocido al subir archivo';
        
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
    
    // Validar tamaño (500MB máximo)
    $maxSize = 500 * 1024 * 1024; // 500MB en bytes
    if ($file['size'] > $maxSize) {
        return [
            'success' => false, 
            'error' => 'El archivo no debe exceder 500MB'
        ];
    }
    
    // Validar tipo MIME real (solo como verificación adicional)
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
    
    // 1. Subir a ngrok con manejo de errores mejorado
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
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'PHP 7.4 Upload Handler'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log('Error CURL: ' . $curl_error);
        return [
            'success' => false, 
            'error' => 'Error de conexión con el servidor'
        ];
    }
    
    if ($http_code !== 200) {
        error_log('HTTP Error: ' . $http_code . ' - Response: ' . $response);
        return [
            'success' => false, 
            'error' => 'Error al subir archivo al servidor'
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
                (nombre_archivo, titulo, descripcion, tipo, extension, tamanio, guid, vistas, likes, activo, fecha_subida)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, GETDATE())";
        
        $params = [
            $nombre_archivo,
            $titulo,
            $descripcion,
            $tipo,
            $extension,
            $file['size'],
            $guid
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
        
        // Log de éxito
        error_log("Archivo subido exitosamente: " . $nombre_archivo . " - GUID: " . $guid);
        
        return [
            'success' => true,
            'message' => 'Archivo subido correctamente',
            'guid' => $guid,
            'titulo' => $titulo,
            'tipo' => $tipo,
            'extension' => $extension,
            'nombre_archivo' => $nombre_archivo,
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