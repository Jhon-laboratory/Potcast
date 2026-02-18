<?php
// main.php - Portal Multimedia con nueva tabla medios - PHP 7.4 Compatible
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
// ==============================================

// Verificar sesión (comentado para pruebas)
/*
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
*/

// ===== CONFIGURACIÓN =====
define('DB_HOST', 'Jorgeserver.database.windows.net');
define('DB_NAME', 'DPL');
define('DB_USER', 'Jmmc');
define('DB_PASS', 'ChaosSoldier01');
define('DB_SCHEMA', 'externos');
define('DB_TABLE', 'medios');
define('NGROK_URL', 'https://softer-dateless-karis.ngrok-free.dev');
// ========================

// Función para limpiar strings
function limpiarString($str) {
    if ($str === null || $str === '') return '';
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
    return mb_substr(trim($str), 0, 1000, 'UTF-8');
}

// Conectar a SQL Server con manejo de errores mejorado
function connectSQLServer() {
    try {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            throw new Exception('Constantes de configuración no definidas');
        }
        
        $connectionInfo = [
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
        
        $conn = sqlsrv_connect(DB_HOST, $connectionInfo);
        
        if ($conn === false) {
            $errors = sqlsrv_errors();
            $error_msg = is_array($errors) && isset($errors[0]['message']) 
                ? $errors[0]['message'] 
                : 'Error desconocido de conexión';
            
            error_log('Error SQLSRV connect: ' . $error_msg);
            return ['success' => false, 'error' => 'Error de conexión a base de datos'];
        }
        
        return ['success' => true, 'conn' => $conn];
        
    } catch (Exception $e) {
        error_log('Excepción en connectSQLServer: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Error interno del servidor'];
    }
}

// Obtener todos los medios de la nueva tabla
function getAllMedia() {
    $connection = connectSQLServer();
    if (!$connection['success']) {
        error_log('Error en getAllMedia: ' . ($connection['error'] ?? 'Error desconocido'));
        return [];
    }
    
    $conn = $connection['conn'];
    $medios = [];
    
    try {
        $sql = "SELECT 
                    id,
                    titulo,
                    descripcion,
                    tipo,
                    extension,
                    vistas,
                    likes,
                    guid,
                    CONVERT(VARCHAR, fecha_subida, 103) + ' ' + CONVERT(VARCHAR, fecha_subida, 108) as fecha_formateada
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1
                ORDER BY fecha_subida DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_msg = is_array($errors) && isset($errors[0]['message']) 
                ? $errors[0]['message'] 
                : 'Error en consulta SQL';
            throw new Exception($error_msg);
        }
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $titulo = limpiarString($row['titulo'] ?? 'Sin título');
            $descripcion = limpiarString($row['descripcion'] ?? 'Sin descripción');
            $extension = limpiarString($row['extension'] ?? '');
            
            // Thumbnails según tipo
            $thumbnails = [
                'video' => [
                    'default' => 'https://img.freepik.com/fotos-premium/conceito-de-sistema-de-gestao-de-inventario-de-armazem-inteligente_46383-19082.jpg',
                    'mp4' => 'https://img.freepik.com/fotos-premium/conceito-de-sistema-de-gestao-de-inventario-de-armazem-inteligente_46383-19082.jpg',
                    'capacitacion' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                    'seguridad' => 'https://images.unsplash.com/photo-1581094794329-c8112a89af12?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'
                ],
                'audio' => [
                    'default' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                    'mp3' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'
                ]
            ];
            
            // Seleccionar thumbnail
            $thumbnail = $thumbnails[$row['tipo']][$extension] ?? $thumbnails[$row['tipo']]['default'];
            
            // Personalizar por palabras clave en título
            $titulo_lower = strtolower($titulo);
            if ($row['tipo'] === 'video') {
                if (strpos($titulo_lower, 'capacitación') !== false || strpos($titulo_lower, 'capacitacion') !== false) {
                    $thumbnail = $thumbnails['video']['capacitacion'];
                } elseif (strpos($titulo_lower, 'seguridad') !== false) {
                    $thumbnail = $thumbnails['video']['seguridad'];
                }
            }
            
            $medios[] = [
                'id' => 'media_' . $row['id'],
                'guid' => $row['guid'],
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'tipo' => $row['tipo'],
                'extension' => strtoupper($extension),
                'vistas' => (int)$row['vistas'],
                'likes' => (int)$row['likes'],
                'fecha' => $row['fecha_formateada'] ?? date('d/m/Y H:i:s'),
                'thumbnail' => $thumbnail,
                'url' => 'stream.php?guid=' . $row['guid']
            ];
        }
        
        sqlsrv_free_stmt($stmt);
        
    } catch (Exception $e) {
        error_log('Error en getAllMedia: ' . $e->getMessage());
    } finally {
        if ($conn) {
            sqlsrv_close($conn);
        }
    }
    
    return $medios;
}

// Incrementar vistas
function incrementarVistas($guid) {
    $guid = limpiarString($guid);
    if (empty($guid)) {
        return false;
    }
    
    $connection = connectSQLServer();
    if (!$connection['success']) {
        return false;
    }
    
    $conn = $connection['conn'];
    $success = false;
    
    try {
        $sql = "UPDATE " . DB_SCHEMA . "." . DB_TABLE . " 
                SET vistas = vistas + 1,
                    ultima_vista = GETDATE()
                WHERE guid = ?";
        
        $params = [$guid];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $success = true;
            sqlsrv_free_stmt($stmt);
        }
        
    } catch (Exception $e) {
        error_log('Error en incrementarVistas: ' . $e->getMessage());
    } finally {
        sqlsrv_close($conn);
    }
    
    return $success;
}

// Incrementar likes
function incrementarLikes($guid) {
    $guid = limpiarString($guid);
    if (empty($guid)) {
        return false;
    }
    
    $connection = connectSQLServer();
    if (!$connection['success']) {
        return false;
    }
    
    $conn = $connection['conn'];
    $success = false;
    
    try {
        $sql = "UPDATE " . DB_SCHEMA . "." . DB_TABLE . " 
                SET likes = likes + 1
                WHERE guid = ?";
        
        $params = [$guid];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $success = true;
            sqlsrv_free_stmt($stmt);
        }
        
    } catch (Exception $e) {
        error_log('Error en incrementarLikes: ' . $e->getMessage());
    } finally {
        sqlsrv_close($conn);
    }
    
    return $success;
}

// ===== MANEJO DE PETICIONES AJAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'incrementar_vistas') {
            $guid = limpiarString($_POST['guid'] ?? '');
            
            if (empty($guid)) {
                echo json_encode(['success' => false, 'error' => 'GUID no válido'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = incrementarVistas($guid);
            echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE);
            exit;
            
        } elseif ($_POST['action'] === 'incrementar_likes') {
            $guid = limpiarString($_POST['guid'] ?? '');
            
            if (empty($guid)) {
                echo json_encode(['success' => false, 'error' => 'GUID no válido'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = incrementarLikes($guid);
            echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (Exception $e) {
        error_log('Error en AJAX: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Obtener todos los medios
$all_media = getAllMedia();
$total_archivos = count($all_media);

$sede_usuario = isset($_SESSION['tienda']) ? limpiarString($_SESSION['tienda']) : '';
$usuario = isset($_SESSION['usuario']) ? limpiarString($_SESSION['usuario']) : 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal Multimedia - RANSA</title>

    <!-- CSS del template - Rutas corregidas para tu estructura -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="vendors/iCheck/skins/flat/green.css" rel="stylesheet">
    <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
    <link href="vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
    <link href="build/css/custom.min.css" rel="stylesheet">
    
    <!-- Video.js desde CDN (CORREGIDO) -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    
    <style>
        /* Fondo sólido para el body */
        body.nav-md {
            background: #f5f7fa !important;
            min-height: 100vh;
        }
        
        /* Fondo con imagen SOLO para el área de contenido principal - CORREGIDO */
        .right_col {
            background: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.98)), 
                        url('https://via.placeholder.com/1920x1080/009A3F/ffffff?text=RANSA') center/cover no-repeat;
            border-radius: 10px;
            margin: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 100px);
        }
        
        /* Panel interno transparente para mostrar el fondo */
        .x_panel {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        /* BARRA DE BÚSQUEDA CON FONDO BLANCO */
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            border: 1px solid #e8f5e9;
            position: relative;
            overflow: hidden;
        }

        .search-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #009A3F, #00c853, #009A3F);
        }

        .search-box {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input .form-control {
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: 40px;
        }

        .search-input .form-control:focus {
            border-color: #009A3F;
            box-shadow: 0 0 0 2px rgba(0, 154, 63, 0.1);
            background: white;
        }

        .search-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            font-size: 14px;
        }

        /* BOTÓN SUBIR */
        .btn-upload {
            background: linear-gradient(135deg, #009A3F, #00c853);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(0, 154, 63, 0.25);
            height: 40px;
            white-space: nowrap;
            border: none;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 154, 63, 0.35);
            background: linear-gradient(135deg, #008a35, #00b848);
            color: white;
            text-decoration: none;
        }

        .btn-upload:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 154, 63, 0.3);
        }

        /* GRID DE MULTIMEDIA */
        .media-container {
            padding: 0;
            background: transparent;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 15px;
            margin: 0;
        }

        /* TARJETAS CON FONDO BLANCO */
        .media-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid #e8f5e9;
            height: 280px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .media-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 154, 63, 0.15);
            border-color: #009A3F;
        }

        .media-type-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0, 154, 63, 0.9);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(2px);
        }

        .media-thumbnail {
            position: relative;
            width: 100%;
            height: 140px;
            overflow: hidden;
            background: #f5f5f5;
            flex-shrink: 0;
        }

        .media-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .media-card:hover .media-thumbnail img {
            transform: scale(1.05);
        }

        .media-play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 154, 63, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .media-card:hover .media-play-overlay {
            opacity: 1;
        }

        .media-play-btn {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #009A3F;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .media-card:hover .media-play-btn {
            transform: scale(1);
        }

        .media-info {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .media-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 40px;
        }

        .media-description {
            color: #666;
            font-size: 11px;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
            height: 30px;
        }

        .media-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #888;
            font-size: 10px;
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }

        .media-stats {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .media-date {
            color: #999;
            font-size: 9px;
        }

        /* BADGES PARA TIPOS DE ARCHIVO */
        .badge-video {
            background: #FF5722;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-audio {
            background: #2196F3;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-extension {
            background: #e0e0e0;
            color: #333;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }

        /* ESTADOS */
        .no-media {
            text-align: center;
            padding: 50px 20px;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            margin: 20px 0;
            grid-column: 1 / -1;
        }

        .no-media i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }

        .no-media h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #333;
        }

        .no-media p {
            font-size: 13px;
            margin-bottom: 20px;
            color: #777;
        }

        /* MODALES */
        .modal-header {
            background: linear-gradient(135deg, #009A3F, #00c853);
            color: white;
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 15px 20px;
        }

        .modal-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
            text-shadow: none;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .upload-area {
            border: 2px dashed #009A3F;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            background: rgba(0, 154, 63, 0.03);
            margin: 15px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            background: rgba(0, 154, 63, 0.08);
            border-color: #008a35;
            transform: translateY(-2px);
        }

        .upload-area i {
            font-size: 42px;
            color: #009A3F;
            margin-bottom: 10px;
        }

        .upload-area h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .upload-area p {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }

        /* PREVIEW */
        .preview-container {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .file-info {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }

        /* REPRODUCTOR */
        .player-container {
            padding: 20px;
        }

        .video-js {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
            border-radius: 8px;
        }

        audio {
            width: 100%;
            margin: 20px 0;
        }

        /* FOOTER */
        .footer-dashboard {
            margin-top: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 8px;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.02);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .search-box {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-upload {
                width: 100%;
                justify-content: center;
            }
            
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
                gap: 12px;
            }
            
            .media-card {
                height: 260px;
            }
            
            .media-thumbnail {
                height: 120px;
            }
            
            .right_col {
                margin: 10px;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .media-grid {
                grid-template-columns: 1fr;
            }
            
            .media-card {
                max-width: 100%;
                height: auto;
                min-height: 250px;
            }
            
            .media-thumbnail {
                height: 150px;
            }
        }

        /* ANIMACIONES */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .media-card {
            animation: fadeIn 0.3s ease-out;
        }

        /* LOADING */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,154,63,0.3);
            border-radius: 50%;
            border-top-color: #009A3F;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <!-- SIDEBAR -->
            <div class="col-md-3 left_col">
                <div class="left_col scroll-view">
                    <div class="navbar nav_title" style="border: 0;">
                        <a href="main.php" class="site_title">
                            <img src="img/logo.png" alt="RANSA Logo" style="height: 32px;">
                            <span>RANSA Multimedia</span>
                        </a>
                    </div>
                    <div class="clearfix"></div>

                    <!-- Información del usuario -->
                    <div class="profile clearfix">
                        <div class="profile_info">
                            <span>Bienvenido,</span>
                            <h2><?php echo htmlspecialchars($usuario); ?></h2>
                            <span><?php echo htmlspecialchars($_SESSION['correo'] ?? ''); ?></span>
                        </div>
                    </div>

                    <br />

                    <!-- MENU -->
                    <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                        <div class="menu_section">
                            <h3>Navegación</h3>
                            <ul class="nav side-menu">
                                <li class="active">
                                    <a href="main.php"><i class="fa fa-film"></i> Multimedia</a>
                                </li>
                                <li>
                                    <a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- FOOTER SIDEBAR -->
                    <div class="sidebar-footer hidden-small">
                        <a title="Actualizar" data-toggle="tooltip" data-placement="top" onclick="location.reload()">
                            <span class="glyphicon glyphicon-refresh"></span>
                        </a>
                        <a title="Salir" data-toggle="tooltip" data-placement="top" onclick="cerrarSesion()">
                            <span class="glyphicon glyphicon-off"></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- NAVBAR SUPERIOR -->
            <div class="top_nav">
                <div class="nav_menu">
                    <div class="nav toggle">
                        <a id="menu_toggle"><i class="fa fa-bars"></i></a>
                    </div>
                    <div class="nav navbar-nav navbar-right">
                        <span style="color: white; padding: 15px; font-weight: 600;">
                            <i class="fa fa-user-circle"></i> 
                            <?php echo htmlspecialchars($usuario); ?>
                            <?php if (!empty($sede_usuario)): ?>
                                <small style="opacity: 0.8; margin-left: 10px;">
                                    <i class="fa fa-map-marker"></i> 
                                    <?php echo htmlspecialchars($sede_usuario); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="right_col" role="main">
                <div class="page-title"></div>
                
                <div class="clearfix"></div>
                
                <div class="row">
                    <div class="col-md-12 col-sm-12">
                        <div class="x_panel">
                            <div class="x_content">
                                <!-- Barra de búsqueda y subida -->
                                <div class="search-container">
                                    <div class="search-box">
                                        <div class="search-input">
                                            <i class="fa fa-search"></i>
                                            <input type="text" id="searchInput" class="form-control" 
                                                   placeholder="Buscar por título, descripción o tipo...">
                                        </div>
                                        <button class="btn-upload" data-toggle="modal" data-target="#uploadModal">
                                            <i class="fa fa-plus"></i> Agregar Contenido
                                        </button>
                                    </div>
                                </div>

                                <!-- Grid de contenido multimedia -->
                                <div class="media-container">
                                    <?php if (empty($all_media)): ?>
                                        <div class="no-media">
                                            <i class="fa fa-film"></i>
                                            <h3>No hay contenido multimedia</h3>
                                            <p>Comienza subiendo tu primer video o audio</p>
                                            <button class="btn-upload" data-toggle="modal" data-target="#uploadModal">
                                                <i class="fa fa-plus"></i> Agregar Contenido
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="media-grid" id="mediaGrid">
                                            <?php foreach ($all_media as $media): ?>
                                                <!-- Tarjeta con debug -->
                                                <div class="media-card" 
                                                     onclick="debugReproduccion('<?php echo $media['guid']; ?>', '<?php echo $media['tipo']; ?>', '<?php echo $media['titulo']; ?>')" 
                                                     data-guid="<?php echo $media['guid']; ?>"
                                                     data-tipo="<?php echo $media['tipo']; ?>"
                                                     data-titulo="<?php echo htmlspecialchars($media['titulo']); ?>">
                                                    
                                                    <div class="media-thumbnail">
                                                        <img src="<?php echo htmlspecialchars($media['thumbnail']); ?>" 
                                                             alt="<?php echo htmlspecialchars($media['titulo']); ?>"
                                                             loading="lazy">
                                                        
                                                        <span class="media-type-badge">
                                                            <?php if ($media['tipo'] === 'video'): ?>
                                                                <i class="fa fa-video-camera"></i> VIDEO
                                                            <?php else: ?>
                                                                <i class="fa fa-music"></i> AUDIO
                                                            <?php endif; ?>
                                                        </span>
                                                        
                                                        <div class="media-play-overlay">
                                                            <div class="media-play-btn">
                                                                <i class="fa fa-play"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="media-info">
                                                        <h5 class="media-title" title="<?php echo htmlspecialchars($media['titulo']); ?>">
                                                            <?php echo htmlspecialchars($media['titulo']); ?>
                                                        </h5>
                                                        
                                                        <div class="media-description" title="<?php echo htmlspecialchars($media['descripcion']); ?>">
                                                            <?php echo htmlspecialchars($media['descripcion']); ?>
                                                        </div>
                                                        
                                                        <div class="media-meta">
                                                            <div class="media-stats">
                                                                <span class="badge-extension">
                                                                    <?php echo $media['extension']; ?>
                                                                </span>
                                                                
                                                                <span class="stat-item">
                                                                    <i class="fa fa-eye"></i>
                                                                    <span class="vistas-<?php echo $media['id']; ?>"><?php echo $media['vistas']; ?></span>
                                                                </span>
                                                                
                                                                <span class="stat-item">
                                                                    <i class="fa fa-thumbs-up"></i>
                                                                    <span class="likes-<?php echo $media['id']; ?>"><?php echo $media['likes']; ?></span>
                                                                </span>
                                                            </div>
                                                            
                                                            <div class="media-date">
                                                                <i class="fa fa-calendar"></i>
                                                                <?php echo $media['fecha']; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <footer class="footer-dashboard">
                <div class="pull-right">
                    <i class="fa fa-copyright"></i> <?php echo date('Y'); ?> RANSA - Portal Multimedia v2.0
                    <span class="badge badge-info ml-2">Total: <?php echo $total_archivos; ?> archivos</span>
                </div>
                <div class="clearfix"></div>
            </footer>
        </div>
    </div>

    <!-- MODAL SUBIR ARCHIVO -->
    <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-upload"></i> Subir Nuevo Contenido
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <!-- Área de selección de archivo -->
                        <div class="upload-area" id="uploadArea">
                            <i class="fa fa-cloud-upload-alt"></i>
                            <h4>Arrastra tu archivo aquí</h4>
                            <p>o haz clic para seleccionar</p>
                            <div class="mt-2">
                                <span class="badge badge-success mr-1">MP4, AVI, MOV</span>
                                <span class="badge badge-info mr-1">MP3, WAV, OGG</span>
                            </div>
                            <p class="text-muted mt-2" style="font-size: 11px;">
                                Tamaño máximo: 500MB
                            </p>
                            <input type="file" id="mediaFile" name="mediaFile" 
                                   accept="video/*,audio/*" style="display: none;" required>
                        </div>

                        <!-- Previsualización -->
                        <div id="previewContainer" style="display: none;">
                            <div class="preview-container">
                                <div id="videoPreview" style="display: none;">
                                    <video controls style="width: 100%; max-height: 200px; border-radius: 6px;"></video>
                                </div>
                                <div id="audioPreview" style="display: none;">
                                    <audio controls style="width: 100%;"></audio>
                                </div>
                                <div class="file-info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong id="fileName"></strong><br>
                                            <small class="text-muted" id="fileSize"></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="resetFileSelection()">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos del formulario -->
                        <div class="form-group mt-3">
                            <label>Título <span class="text-danger">*</span></label>
                            <input type="text" id="titulo" name="titulo" class="form-control" 
                                   placeholder="Ej: Capacitación de seguridad 2024" required>
                        </div>

                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea id="descripcion" name="descripcion" class="form-control" 
                                      rows="2" placeholder="Breve descripción del contenido..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tipo</label>
                                    <select id="tipo" name="tipo" class="form-control" disabled>
                                        <option value="video">Video</option>
                                        <option value="audio">Audio</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Extensión</label>
                                    <input type="text" id="extension" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" id="btnGuardar" onclick="subirArchivo()" disabled>
                        <i class="fa fa-upload"></i> Subir Archivo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL REPRODUCTOR -->
    <div class="modal fade" id="playerModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-play-circle"></i> <span id="playerTitle">Reproductor</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-0">
                    <div id="playerContainer" class="player-container">
                        <!-- Contenido dinámico del reproductor -->
                    </div>
                </div>
                
                <div class="modal-footer">
                    <div class="mr-auto">
                        <button class="btn btn-sm btn-outline-success" onclick="darLike()" id="btnLike">
                            <i class="fa fa-thumbs-up"></i> Me gusta <span id="likesCount">0</span>
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS - Rutas corregidas -->
    <script src="vendors/jquery/dist/jquery.min.js"></script>
    <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="vendors/fastclick/lib/fastclick.js"></script>
    <script src="vendors/nprogress/nprogress.js"></script>
    <script src="build/js/custom.min.js"></script>
    
    <!-- Video.js desde CDN (CORREGIDO) -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // =============================================
    // VARIABLES GLOBALES
    // =============================================
    let player = null;
    let selectedFile = null;
    let mediaActual = null;
    let guidActual = null;
    
    const mediaFiles = <?php echo json_encode($all_media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    // =============================================
    // INICIALIZACIÓN
    // =============================================
    $(document).ready(function() {
        configurarEventos();
        inicializarFileUpload();
        
        // Mostrar información de depuración en consola al cargar la página
        console.log('========== INFO DE CARGA ==========');
        console.log('Total medios cargados:', mediaFiles.length);
        if (mediaFiles.length > 0) {
            console.log('Primer medio:', mediaFiles[0]);
            console.log('Ejemplo de GUID:', mediaFiles[0]?.guid);
        }
        console.log('====================================');
    });

    function configurarEventos() {
        // Búsqueda en tiempo real
        let timeoutBusqueda;
        $('#searchInput').on('input', function() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                const termino = $(this).val().toLowerCase().trim();
                if (termino) {
                    filtrarMedios(termino);
                } else {
                    mostrarTodos();
                }
            }, 300);
        });

        // Cerrar modales
        $('#uploadModal').on('hidden.bs.modal', function() {
            resetUploadForm();
        });

        $('#playerModal').on('hidden.bs.modal', function() {
            if (player) {
                player.dispose();
                player = null;
            }
            mediaActual = null;
            guidActual = null;
        });

        // Atajos de teclado
        $(document).on('keydown', function(e) {
            // Ctrl + F2 para abrir upload
            if (e.ctrlKey && e.key === 'F2') {
                e.preventDefault();
                $('#uploadModal').modal('show');
            }
            // Escape para cerrar modales
            if (e.key === 'Escape') {
                $('#uploadModal').modal('hide');
                $('#playerModal').modal('hide');
            }
        });
    }

    function inicializarFileUpload() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('mediaFile');
        
        if (!uploadArea || !fileInput) return;
        
        // Click en área
        uploadArea.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.click();
        });
        
        // Cambio en input
        fileInput.addEventListener('change', manejarSeleccionArchivo);
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = 'rgba(0, 154, 63, 0.1)';
            this.style.borderColor = '#008a35';
        });
        
        uploadArea.addEventListener('dragleave', function() {
            this.style.background = 'rgba(0, 154, 63, 0.03)';
            this.style.borderColor = '#009A3F';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = 'rgba(0, 154, 63, 0.03)';
            this.style.borderColor = '#009A3F';
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                manejarSeleccionArchivo({ target: fileInput });
            }
        });
    }

    function manejarSeleccionArchivo(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validar extensión
        const nombre = file.name;
        const extension = nombre.split('.').pop().toLowerCase();
        const extensionesValidas = ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'webm', 'mp3', 'wav', 'ogg', 'm4a', 'flac'];
        
        if (!extensionesValidas.includes(extension)) {
            Swal.fire({
                title: 'Extensión no válida',
                text: 'Solo se permiten archivos de video o audio',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Validar tamaño (500MB)
        if (file.size > 500 * 1024 * 1024) {
            Swal.fire({
                title: 'Archivo muy grande',
                text: 'El tamaño máximo es 500MB',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        selectedFile = file;
        
        // Determinar tipo
        const tipo = extension.match(/^(mp4|avi|mov|mkv|wmv|webm)$/) ? 'video' : 'audio';
        
        // Actualizar campos
        $('#tipo').val(tipo);
        $('#extension').val(extension.toUpperCase());
        
        // Sugerir título
        if (!$('#titulo').val()) {
            const nombreSinExt = nombre.substring(0, nombre.lastIndexOf('.')) || nombre;
            $('#titulo').val(nombreSinExt.replace(/[_-]/g, ' '));
        }
        
        // Mostrar preview
        mostrarPreview(file, tipo);
        
        // Habilitar botón
        $('#btnGuardar').prop('disabled', false);
    }

    function mostrarPreview(file, tipo) {
        $('#previewContainer').show();
        $('#videoPreview, #audioPreview').hide();
        
        const url = URL.createObjectURL(file);
        
        if (tipo === 'video') {
            const videoPreview = $('#videoPreview video');
            videoPreview.attr('src', url);
            videoPreview[0].load();
            $('#videoPreview').show();
        } else {
            const audioPreview = $('#audioPreview audio');
            audioPreview.attr('src', url);
            audioPreview[0].load();
            $('#audioPreview').show();
        }
        
        $('#fileName').text(file.name);
        $('#fileSize').text(formatFileSize(file.size));
    }

    function resetFileSelection() {
        selectedFile = null;
        $('#mediaFile').val('');
        $('#previewContainer').hide();
        $('#titulo').val('');
        $('#descripcion').val('');
        $('#tipo').val('video');
        $('#extension').val('');
        $('#btnGuardar').prop('disabled', true);
    }

    function resetUploadForm() {
        resetFileSelection();
    }

    function subirArchivo() {
        if (!selectedFile) {
            Swal.fire('Error', 'Selecciona un archivo', 'warning');
            return;
        }
        
        const titulo = $('#titulo').val().trim();
        if (!titulo) {
            Swal.fire('Error', 'Ingresa un título', 'warning');
            return;
        }
        
        const btn = $('#btnGuardar');
        const textoOriginal = btn.html();
        
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Subiendo...');
        
        const formData = new FormData();
        formData.append('mediaFile', selectedFile);
        formData.append('titulo', titulo);
        formData.append('descripcion', $('#descripcion').val().trim());
        
        fetch('upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: '¡Éxito!',
                    text: 'Archivo subido correctamente',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#uploadModal').modal('hide');
                    setTimeout(() => location.reload(), 500);
                });
            } else {
                throw new Error(data.error || 'Error al subir');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            btn.prop('disabled', false).html(textoOriginal);
        });
    }

    // =============================================
    // FUNCIÓN DE DEBUG
    // =============================================
    function debugReproduccion(guid, tipo, titulo) {
        console.log('%c========== DEBUG REPRODUCCIÓN ==========', 'background: #009A3F; color: white; font-size: 14px;');
        console.log('1️⃣ GUID recibido:', guid);
        console.log('2️⃣ Tipo:', tipo);
        console.log('3️⃣ Título:', titulo);
        console.log('4️⃣ Longitud GUID:', guid.length);
        console.log('5️⃣ ¿GUID vacío?', guid === '');
        console.log('6️⃣ Formato GUID válido:', /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(guid));
        
        // Buscar en mediaFiles
        console.log('7️⃣ Buscando en mediaFiles...');
        console.log('   Total medios:', mediaFiles.length);
        
        // Mostrar todos los GUIDs disponibles
        console.log('8️⃣ GUIDs disponibles en mediaFiles:');
        if (mediaFiles.length > 0) {
            mediaFiles.forEach((m, index) => {
                console.log(`   [${index}] GUID: ${m.guid} - Título: ${m.titulo}`);
            });
        } else {
            console.log('   ❌ mediaFiles está VACÍO');
        }
        
        const media = mediaFiles.find(m => m.guid === guid);
        
        if (media) {
            console.log('%c✅ MEDIO ENCONTRADO:', 'color: green; font-weight: bold');
            console.log('   - Título:', media.titulo);
            console.log('   - Tipo:', media.tipo);
            console.log('   - Extensión:', media.extension);
            console.log('   - URL:', media.url);
            console.log('   - ID:', media.id);
            
            // Ahora proceder a reproducir
            console.log('9️⃣ Procediendo a reproducir...');
            reproducirMedia(guid, tipo);
        } else {
            console.log('%c❌ ERROR: MEDIO NO ENCONTRADO', 'color: red; font-weight: bold');
            console.log('   El GUID', guid, 'no existe en mediaFiles');
            
            // Mostrar alerta al usuario
            Swal.fire({
                title: 'Error de reproducción',
                text: 'No se encontró el contenido. Revisa la consola (F12) para más detalles.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
        
        console.log('%c========== FIN DEBUG ==========', 'background: #009A3F; color: white;');
    }

    // =============================================
    // FUNCIÓN DE REPRODUCCIÓN
    // =============================================
    function reproducirMedia(guid, tipo) {
        // Buscar el medio por GUID
        const media = mediaFiles.find(m => m.guid === guid);
        
        if (!media) {
            console.error('Error crítico: No se encontró media para GUID:', guid);
            return;
        }
        
        mediaActual = media;
        guidActual = guid;
        
        $('#playerTitle').text(media.titulo);
        $('#likesCount').text(media.likes);
        
        const playerContainer = $('#playerContainer');
        
        if (tipo === 'video') {
            playerContainer.html(`
                <video id="reproductorVideo" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto">
                    <source src="stream.php?guid=${guid}" type="video/${media.extension.toLowerCase()}">
                    <p class="vjs-no-js">Tu navegador no soporta video</p>
                </video>
                <div class="mt-3">
                    <p class="text-muted">${media.descripcion}</p>
                </div>
            `);
            
            setTimeout(() => {
                if (player) {
                    player.dispose();
                }
                player = videojs('reproductorVideo', {
                    controls: true,
                    autoplay: true,
                    preload: 'auto',
                    fluid: true,
                    playbackRates: [0.5, 1, 1.5, 2]
                });
                
                // Verificar si el video se cargó correctamente
                player.on('error', function() {
                    console.error('Error de video:', player.error());
                    Swal.fire({
                        title: 'Error de reproducción',
                        text: 'No se pudo cargar el video. Verifica la consola.',
                        icon: 'error'
                    });
                });
            }, 100);
        } else {
            playerContainer.html(`
                <div class="text-center">
                    <img src="${media.thumbnail}" style="max-width: 200px; border-radius: 8px; margin-bottom: 15px;">
                    <audio controls autoplay style="width: 100%;">
                        <source src="stream.php?guid=${guid}" type="audio/${media.extension.toLowerCase()}">
                    </audio>
                    <div class="mt-3">
                        <p class="text-muted">${media.descripcion}</p>
                    </div>
                </div>
            `);
            
            // Verificar si el audio se cargó correctamente
            setTimeout(() => {
                const audio = document.querySelector('audio');
                if (audio) {
                    audio.onerror = function(e) {
                        console.error('Error de audio:', e);
                        Swal.fire({
                            title: 'Error de reproducción',
                            text: 'No se pudo cargar el audio. Verifica la consola.',
                            icon: 'error'
                        });
                    };
                }
            }, 100);
        }
        
        $('#playerModal').modal('show');
        
        // Incrementar vistas
        incrementarVistas(guid, media.id);
    }

    function incrementarVistas(guid, mediaId) {
        fetch('main.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=incrementar_vistas&guid=${guid}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const vistasSpan = $(`.vistas-${mediaId}`);
                const vistasActuales = parseInt(vistasSpan.text());
                vistasSpan.text(vistasActuales + 1);
            }
        })
        .catch(console.error);
    }

    function darLike() {
        if (!guidActual) return;
        
        fetch('main.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=incrementar_likes&guid=${guidActual}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const likesSpan = $(`.likes-${mediaActual.id}`);
                const likesActuales = parseInt(likesSpan.text());
                likesSpan.text(likesActuales + 1);
                $('#likesCount').text(likesActuales + 1);
                
                Swal.fire({
                    title: '¡Gracias!',
                    text: 'Like registrado',
                    icon: 'success',
                    timer: 1000,
                    showConfirmButton: false
                });
            }
        })
        .catch(console.error);
    }

    function filtrarMedios(termino) {
        const cards = document.querySelectorAll('.media-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const title = card.querySelector('.media-title').textContent.toLowerCase();
            const desc = card.querySelector('.media-description').textContent.toLowerCase();
            
            if (title.includes(termino) || desc.includes(termino)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        const noResults = document.getElementById('noResults');
        if (visibleCount === 0 && cards.length > 0) {
            if (!noResults) {
                const grid = document.getElementById('mediaGrid');
                const message = document.createElement('div');
                message.id = 'noResults';
                message.className = 'no-media';
                message.innerHTML = `
                    <i class="fa fa-search"></i>
                    <h3>No se encontraron resultados</h3>
                    <p>No hay contenido que coincida con "${termino}"</p>
                `;
                grid.appendChild(message);
            }
        } else if (noResults) {
            noResults.remove();
        }
    }

    function mostrarTodos() {
        const cards = document.querySelectorAll('.media-card');
        cards.forEach(card => {
            card.style.display = 'flex';
        });
        
        const noResults = document.getElementById('noResults');
        if (noResults) {
            noResults.remove();
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function cerrarSesion() {
        if (confirm('¿Está seguro de que desea cerrar sesión?')) {
            window.location.href = 'logout.php';
        }
    }
    </script>
</body>
</html>