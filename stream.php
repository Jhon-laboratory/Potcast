<?php
// stream.php - Sirve archivos usando GUID - VERSIÓN CORREGIDA
require_once 'config.php';

// ===== CONFIGURACIÓN DE ERRORES =====
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== VALIDACIÓN DE GUID =====
$guid = $_GET['guid'] ?? '';

if (empty($guid)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    die('Error: GUID no especificado');
}

// Log para debug
error_log("Stream.php - GUID recibido: " . $guid);

// ===== FUNCIÓN PARA BUSCAR ARCHIVO =====
function getFileNameByGuid($guid) {
    try {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            error_log('Error: Constantes de configuración no definidas');
            return null;
        }
        
        $connInfo = [
            "Database" => DB_NAME,
            "UID" => DB_USER,
            "PWD" => DB_PASS,
            "CharacterSet" => "UTF-8",
            "ConnectionPooling" => true,
            "Encrypt" => true,
            "TrustServerCertificate" => false,
            "LoginTimeout" => 5
        ];
        
        $conn = sqlsrv_connect(DB_HOST, $connInfo);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            $error_msg = is_array($errors) && isset($errors[0]['message']) 
                ? $errors[0]['message'] 
                : 'Error desconocido';
            error_log('Error SQLSRV connect en stream.php: ' . $error_msg);
            return null;
        }
        
        // Buscar por GUID (como string)
        $sql = "SELECT nombre_archivo 
                FROM " . DB_SCHEMA . "." . DB_TABLE . " 
                WHERE guid = ? AND activo = 1";
        
        $params = [$guid];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log('Error en consulta stream.php: ' . print_r($errors, true));
            sqlsrv_close($conn);
            return null;
        }
        
        $nombre = null;
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $nombre = $row['nombre_archivo'];
            error_log("Archivo encontrado: " . $nombre);
            
            // Incrementar vistas
            $sqlUpdate = "UPDATE " . DB_SCHEMA . "." . DB_TABLE . " 
                         SET vistas = vistas + 1, 
                             ultima_vista = GETDATE() 
                         WHERE guid = ?";
            
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$guid]);
            if ($stmtUpdate) {
                sqlsrv_free_stmt($stmtUpdate);
            }
        } else {
            error_log("No se encontró archivo para GUID: " . $guid);
        }
        
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        return $nombre;
        
    } catch (Exception $e) {
        error_log('Excepción en getFileNameByGuid: ' . $e->getMessage());
        return null;
    }
}

// ===== OBTENER NOMBRE DEL ARCHIVO =====
$nombre_archivo = getFileNameByGuid($guid);

if (!$nombre_archivo) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    die('Error: Archivo no encontrado para GUID: ' . htmlspecialchars($guid));
}

// ===== CONSTRUIR URL Y REDIRIGIR =====
$ngrok_base = rtrim(NGROK_URL, '/');
$file_url = $ngrok_base . '/' . ltrim($nombre_archivo, '/');

error_log("Redirigiendo a: " . $file_url);

// Redirigir al archivo en ngrok
header('Location: ' . $file_url);
exit;
?>