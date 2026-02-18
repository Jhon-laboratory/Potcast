<?php
// stream_ngrok.php - Servidor de streaming LOCAL (usa archivos del disco)
session_start();
ini_set('display_errors', 0);

// Configuración LOCAL (no SFTP)
$media_path = 'D:/MediaServer/audios';

// Obtener nombre de archivo
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($filename)) {
    http_response_code(400);
    echo 'Nombre de archivo no especificado';
    exit;
}

// Validar seguridad
if (preg_match('/\.\./', $filename) || !preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
    http_response_code(403);
    echo 'Nombre de archivo no válido';
    exit;
}

// Validar extensión
$allowed_extensions = ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'webm', 'mp3', 'wav', 'ogg', 'm4a', 'flac'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowed_extensions)) {
    http_response_code(403);
    echo 'Tipo de archivo no permitido';
    exit;
}

$file_path = $media_path . DIRECTORY_SEPARATOR . $filename;

// Verificar existencia
if (!file_exists($file_path)) {
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

$file_size = filesize($file_path);
$last_modified = filemtime($file_path);

// MIME types (igual que stream.php)
$mime_types = [
    'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime',
    'mkv' => 'video/x-matroska', 'wmv' => 'video/x-ms-wmv', 'webm' => 'video/webm',
    'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4', 'flac' => 'audio/flac'
];
$content_type = $mime_types[$extension] ?? 'application/octet-stream';

// Headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . $file_size);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');

// Range requests (soporte igual que stream.php)
$range = $_SERVER['HTTP_RANGE'] ?? '';

if (!$range) {
    header('HTTP/1.1 200 OK');
    readfile($file_path);
} else {
    list($size_unit, $range_orig) = explode('=', $range, 2);
    
    if ($size_unit != 'bytes') {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        exit;
    }
    
    list($range_start, $range_end) = explode('-', $range_orig, 2);
    
    $range_start = intval($range_start);
    $range_end = $range_end === '' ? $file_size - 1 : intval($range_end);
    
    if ($range_start > $range_end || $range_start >= $file_size || $range_end >= $file_size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes */$file_size");
        exit;
    }
    
    $length = $range_end - $range_start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $range_start-$range_end/$file_size");
    header('Content-Length: ' . $length);
    
    $fp = fopen($file_path, 'rb');
    fseek($fp, $range_start);
    
    $chunk_size = 8192;
    $remaining = $length;
    
    while ($remaining > 0 && !feof($fp)) {
        $read_size = min($chunk_size, $remaining);
        $buffer = fread($fp, $read_size);
        echo $buffer;
        flush();
        $remaining -= strlen($buffer);
    }
    
    fclose($fp);
}