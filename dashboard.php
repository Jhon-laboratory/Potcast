<?php
// dashboard.php - Dashboard Multimedia con nueva tabla medios - PHP 7.4 Compatible
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
        // Validar que las constantes existan
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
            
            error_log('Error SQLSRV connect en dashboard: ' . $error_msg);
            return ['success' => false, 'error' => 'Error de conexión a base de datos'];
        }
        
        return ['success' => true, 'conn' => $conn];
        
    } catch (Exception $e) {
        error_log('Excepción en connectSQLServer dashboard: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Error interno del servidor'];
    }
}

// Obtener estadísticas del dashboard
function getDashboardStats() {
    $connection = connectSQLServer();
    if (!$connection['success']) {
        return ['success' => false, 'error' => $connection['error']];
    }
    
    $conn = $connection['conn'];
    $stats = [];
    
    try {
        // 1. ESTADÍSTICAS GENERALES
        $sql = "SELECT 
                    COUNT(*) as total_contenidos,
                    SUM(vistas) as total_vistas,
                    SUM(likes) as total_likes,
                    AVG(CAST(vistas as float)) as promedio_vistas,
                    AVG(CAST(likes as float)) as promedio_likes,
                    SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
                    SUM(CASE WHEN tipo = 'audio' THEN 1 ELSE 0 END) as total_audios,
                    MAX(fecha_subida) as ultima_subida
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1";
        
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            throw new Exception('Error en consulta general');
        }
        
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $stats['general'] = [
                'total' => (int)($row['total_contenidos'] ?? 0),
                'vistas' => (int)($row['total_vistas'] ?? 0),
                'likes' => (int)($row['total_likes'] ?? 0),
                'promedio_vistas' => round((float)($row['promedio_vistas'] ?? 0), 1),
                'promedio_likes' => round((float)($row['promedio_likes'] ?? 0), 1),
                'videos' => (int)($row['total_videos'] ?? 0),
                'audios' => (int)($row['total_audios'] ?? 0),
                'ultima_subida' => $row['ultima_subida'] ?? '',
                'engagement' => ($row['total_vistas'] > 0) 
                    ? round(($row['total_likes'] / $row['total_vistas']) * 100, 1) 
                    : 0
            ];
        }
        sqlsrv_free_stmt($stmt);

        // 2. TOP 10 POR LIKES
        $sql = "SELECT TOP 10 
                    titulo,
                    vistas,
                    likes,
                    tipo,
                    extension,
                    CONVERT(VARCHAR(10), fecha_subida, 103) as fecha
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1 AND likes > 0
                ORDER BY likes DESC, vistas DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['top_likes'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row['titulo'] = limpiarString($row['titulo']);
                $row['vistas'] = (int)$row['vistas'];
                $row['likes'] = (int)$row['likes'];
                $row['engagement'] = $row['vistas'] > 0 
                    ? round(($row['likes'] / $row['vistas']) * 100, 1) 
                    : 0;
                $stats['top_likes'][] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }

        // 3. TOP 10 POR VISTAS
        $sql = "SELECT TOP 10 
                    titulo,
                    vistas,
                    likes,
                    tipo,
                    extension,
                    CONVERT(VARCHAR(10), fecha_subida, 103) as fecha
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1 AND vistas > 0
                ORDER BY vistas DESC, likes DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['top_vistas'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row['titulo'] = limpiarString($row['titulo']);
                $row['vistas'] = (int)$row['vistas'];
                $row['likes'] = (int)$row['likes'];
                $row['ratio'] = $row['vistas'] > 0 
                    ? round(($row['likes'] / $row['vistas']) * 100, 1) 
                    : 0;
                $stats['top_vistas'][] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }

        // 4. DISTRIBUCIÓN POR TIPO
        $sql = "SELECT 
                    tipo,
                    COUNT(*) as cantidad,
                    SUM(vistas) as total_vistas,
                    SUM(likes) as total_likes,
                    AVG(CAST(vistas as float)) as avg_vistas,
                    AVG(CAST(likes as float)) as avg_likes
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1
                GROUP BY tipo";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['distribucion'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row['cantidad'] = (int)$row['cantidad'];
                $row['total_vistas'] = (int)$row['total_vistas'];
                $row['total_likes'] = (int)$row['total_likes'];
                $row['avg_vistas'] = round((float)$row['avg_vistas'], 1);
                $row['avg_likes'] = round((float)$row['avg_likes'], 1);
                $row['engagement'] = $row['total_vistas'] > 0 
                    ? round(($row['total_likes'] / $row['total_vistas']) * 100, 1) 
                    : 0;
                $stats['distribucion'][] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }

        // 5. EXTENSIONES MÁS USADAS
        $sql = "SELECT 
                    extension,
                    COUNT(*) as cantidad,
                    SUM(vistas) as vistas,
                    SUM(likes) as likes
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1
                GROUP BY extension
                ORDER BY cantidad DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['extensiones'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row['extension'] = strtoupper(limpiarString($row['extension']));
                $row['cantidad'] = (int)$row['cantidad'];
                $row['vistas'] = (int)$row['vistas'];
                $row['likes'] = (int)$row['likes'];
                $stats['extensiones'][] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }

        // 6. ACTIVIDAD ÚLTIMOS 30 DÍAS
        $sql = "SELECT 
                    CONVERT(VARCHAR(10), fecha_subida, 103) as fecha,
                    COUNT(*) as nuevos,
                    SUM(vistas) as vistas_dia,
                    SUM(likes) as likes_dia
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE fecha_subida >= DATEADD(day, -30, GETDATE())
                GROUP BY CONVERT(VARCHAR(10), fecha_subida, 103)
                ORDER BY CONVERT(DATE, fecha_subida, 103) DESC";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['actividad'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stats['actividad'][] = [
                    'fecha' => $row['fecha'],
                    'nuevos' => (int)$row['nuevos'],
                    'vistas_dia' => (int)$row['vistas_dia'],
                    'likes_dia' => (int)$row['likes_dia']
                ];
            }
            sqlsrv_free_stmt($stmt);
        }

        // 7. ANÁLISIS DE ENGAGEMENT
        $sql = "SELECT 
                    CASE 
                        WHEN vistas = 0 THEN 'Sin vistas'
                        WHEN vistas BETWEEN 1 AND 10 THEN '1-10 vistas'
                        WHEN vistas BETWEEN 11 AND 50 THEN '11-50 vistas'
                        WHEN vistas BETWEEN 51 AND 100 THEN '51-100 vistas'
                        WHEN vistas > 100 THEN '+100 vistas'
                    END as rango,
                    COUNT(*) as cantidad,
                    AVG(CAST(likes as float)) as avg_likes,
                    AVG(CASE WHEN vistas > 0 
                        THEN (CAST(likes as float) / CAST(vistas as float)) * 100 
                        ELSE 0 END) as engagement
                FROM " . DB_SCHEMA . "." . DB_TABLE . "
                WHERE activo = 1
                GROUP BY 
                    CASE 
                        WHEN vistas = 0 THEN 'Sin vistas'
                        WHEN vistas BETWEEN 1 AND 10 THEN '1-10 vistas'
                        WHEN vistas BETWEEN 11 AND 50 THEN '11-50 vistas'
                        WHEN vistas BETWEEN 51 AND 100 THEN '51-100 vistas'
                        WHEN vistas > 100 THEN '+100 vistas'
                    END
                ORDER BY 
                    MIN(CASE 
                        WHEN vistas = 0 THEN 0
                        WHEN vistas BETWEEN 1 AND 10 THEN 1
                        WHEN vistas BETWEEN 11 AND 50 THEN 2
                        WHEN vistas BETWEEN 51 AND 100 THEN 3
                        WHEN vistas > 100 THEN 4
                    END)";
        
        $stmt = sqlsrv_query($conn, $sql);
        $stats['engagement'] = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stats['engagement'][] = [
                    'rango' => $row['rango'],
                    'cantidad' => (int)$row['cantidad'],
                    'avg_likes' => round((float)$row['avg_likes'], 1),
                    'engagement' => round((float)$row['engagement'], 1)
                ];
            }
            sqlsrv_free_stmt($stmt);
        }
        
        sqlsrv_close($conn);
        return ['success' => true, 'stats' => $stats];
        
    } catch (Exception $e) {
        error_log('Error en getDashboardStats: ' . $e->getMessage());
        if ($conn) {
            sqlsrv_close($conn);
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Obtener datos
$dashboard = getDashboardStats();
$stats = $dashboard['success'] ? $dashboard['stats'] : [];
$usuario = isset($_SESSION['usuario']) ? limpiarString($_SESSION['usuario']) : 'Usuario';
$sede_usuario = isset($_SESSION['tienda']) ? limpiarString($_SESSION['tienda']) : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Multimedia - RANSA</title>

    <!-- CSS del template -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="vendors/iCheck/skins/flat/green.css" rel="stylesheet">
    <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
    <link href="vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
    <link href="build/css/custom.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Mantener mismo estilo del main */
        body.nav-md { background: #f5f7fa !important; min-height: 100vh; }
        
        .right_col {
            background: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.98)), 
                        url('img/fondo.png') center/cover no-repeat;
            border-radius: 10px;
            margin: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 100px);
        }
        
        .x_panel { background: transparent !important; border: none !important; box-shadow: none !important; }
        
        /* Header Dashboard */
        .dashboard-header {
            background: linear-gradient(135deg, #009A3F, #00c853);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 154, 63, 0.2);
        }
        
        .dashboard-header h1 { font-size: 24px; font-weight: 600; margin-bottom: 10px; }
        .dashboard-header p { font-size: 14px; opacity: 0.9; margin-bottom: 0; }
        
        /* Cards de KPIs */
        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e8f5e9;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 154, 63, 0.1);
            border-color: #009A3F;
        }
        
        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .kpi-icon.total { background: linear-gradient(135deg, #4CAF50, #8BC34A); color: white; }
        .kpi-icon.vistas { background: linear-gradient(135deg, #2196F3, #21CBF3); color: white; }
        .kpi-icon.likes { background: linear-gradient(135deg, #FF5722, #FF8A65); color: white; }
        .kpi-icon.ratio { background: linear-gradient(135deg, #9C27B0, #E040FB); color: white; }
        
        .kpi-value { font-size: 28px; font-weight: 700; color: #333; margin-bottom: 5px; }
        .kpi-label { font-size: 13px; color: #666; font-weight: 500; }
        .kpi-trend { font-size: 12px; margin-top: 8px; color: #4CAF50; }
        
        /* Cards de ranking */
        .ranking-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e8f5e9;
            height: 100%;
        }
        
        .ranking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .ranking-header h3 { font-size: 16px; font-weight: 600; color: #333; margin: 0; }
        
        .ranking-table { width: 100%; font-size: 13px; }
        .ranking-table th { 
            background: #f8f9fa; 
            color: #666; 
            font-weight: 600; 
            padding: 10px; 
            border-bottom: 2px solid #e8f5e9;
        }
        .ranking-table td { padding: 10px; border-bottom: 1px solid #f0f0f0; }
        .ranking-table tr:last-child td { border-bottom: none; }
        .ranking-table tr:hover td { background: #f8f9fa; }
        
        .rank-1 { color: #FFD700; font-weight: 700; }
        .rank-2 { color: #C0C0C0; font-weight: 700; }
        .rank-3 { color: #CD7F32; font-weight: 700; }
        
        .badge-video { background: #2196F3; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
        .badge-audio { background: #4CAF50; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
        
        /* Progress bars */
        .progress { height: 6px; border-radius: 3px; background: #f0f0f0; }
        .progress-bar { border-radius: 3px; }
        
        /* Chart cards */
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e8f5e9;
            height: 100%;
        }
        
        .chart-container { position: relative; height: 250px; width: 100%; }
        
        /* Mini cards de info */
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border-left: 3px solid #009A3F;
        }
        .info-value { font-size: 20px; font-weight: 700; color: #009A3F; }
        .info-label { font-size: 11px; color: #666; }
        
        /* Error alert */
        .error-alert {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        /* Footer */
        .footer-dashboard {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            font-size: 12px;
            border: 1px solid #e8f5e9;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .right_col { margin: 10px; padding: 15px; }
            .dashboard-header { padding: 15px; }
            .dashboard-header h1 { font-size: 20px; }
            .kpi-value { font-size: 22px; }
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
                            <span>Dashboard</span>
                        </a>
                    </div>
                    <div class="clearfix"></div>

                    <!-- Info usuario -->
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
                                <li><a href="main.php"><i class="fa fa-film"></i> Multimedia</a></li>
                                <li class="active"><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Footer sidebar -->
                    <div class="sidebar-footer hidden-small">
                        <a title="Actualizar" onclick="location.reload()">
                            <span class="glyphicon glyphicon-refresh"></span>
                        </a>
                        <a title="Salir" onclick="cerrarSesion()">
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
                <!-- Header -->
                <div class="dashboard-header">
                    <h1><i class="fa fa-dashboard"></i> Dashboard Multimedia</h1>
                    <p>Análisis y métricas de la biblioteca multimedia - <?php echo date('d/m/Y'); ?></p>
                </div>
                
                <?php if (!$dashboard['success']): ?>
                    <div class="error-alert">
                        <i class="fa fa-exclamation-triangle"></i> 
                        Error al cargar datos: <?php echo htmlspecialchars($dashboard['error'] ?? 'Error desconocido'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- KPIs Principales -->
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="kpi-icon total"><i class="fa fa-film"></i></div>
                            <div class="kpi-value"><?php echo $stats['general']['total'] ?? 0; ?></div>
                            <div class="kpi-label">Total Contenidos</div>
                            <div class="kpi-trend">
                                <i class="fa fa-video-camera"></i> <?php echo $stats['general']['videos'] ?? 0; ?> videos • 
                                <i class="fa fa-music"></i> <?php echo $stats['general']['audios'] ?? 0; ?> audios
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="kpi-icon vistas"><i class="fa fa-eye"></i></div>
                            <div class="kpi-value"><?php echo number_format($stats['general']['vistas'] ?? 0); ?></div>
                            <div class="kpi-label">Vistas Totales</div>
                            <div class="kpi-trend">
                                <i class="fa fa-chart-line"></i> Prom. <?php echo $stats['general']['promedio_vistas'] ?? 0; ?> por contenido
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="kpi-icon likes"><i class="fa fa-thumbs-up"></i></div>
                            <div class="kpi-value"><?php echo number_format($stats['general']['likes'] ?? 0); ?></div>
                            <div class="kpi-label">Likes Totales</div>
                            <div class="kpi-trend">
                                <i class="fa fa-heart"></i> Prom. <?php echo $stats['general']['promedio_likes'] ?? 0; ?> por contenido
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6">
                        <div class="kpi-card">
                            <div class="kpi-icon ratio"><i class="fa fa-percent"></i></div>
                            <div class="kpi-value"><?php echo $stats['general']['engagement'] ?? 0; ?>%</div>
                            <div class="kpi-label">Engagement Rate</div>
                            <div class="kpi-trend">
                                <i class="fa fa-calendar"></i> Última subida: <?php 
                                    $fecha = $stats['general']['ultima_subida'] ?? '';
                                    echo $fecha ? date('d/m/Y', strtotime($fecha)) : 'N/A';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Primera fila: Rankings -->
                <div class="row">
                    <!-- Top 10 Likes -->
                    <div class="col-md-6">
                        <div class="ranking-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-trophy text-warning"></i> Top 10 por Likes</h3>
                                <span class="badge badge-success">Ranking</span>
                            </div>
                            <div class="table-responsive">
                                <table class="ranking-table">
                                    <thead>
                                        <tr><th>#</th><th>Título</th><th>Tipo</th><th>Likes</th><th>Vistas</th><th>Engagement</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($stats['top_likes'])): ?>
                                            <?php foreach ($stats['top_likes'] as $i => $item): ?>
                                                <tr>
                                                    <td class="rank-<?php echo min($i+1, 3); ?>"><?php echo $i+1; ?></td>
                                                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                                                        <?php echo htmlspecialchars($item['titulo']); ?>
                                                    </td>
                                                    <td><span class="badge-<?php echo $item['tipo']; ?>"><?php echo $item['tipo']; ?></span></td>
                                                    <td><strong><?php echo $item['likes']; ?></strong></td>
                                                    <td><?php echo $item['vistas']; ?></td>
                                                    <td>
                                                        <div class="progress" style="width:80px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo min($item['engagement'], 100); ?>%"></div>
                                                        </div>
                                                        <small><?php echo $item['engagement']; ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center">Sin datos</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top 10 Vistas -->
                    <div class="col-md-6">
                        <div class="ranking-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-chart-line text-primary"></i> Top 10 por Vistas</h3>
                                <span class="badge badge-info">Popularidad</span>
                            </div>
                            <div class="table-responsive">
                                <table class="ranking-table">
                                    <thead>
                                        <tr><th>#</th><th>Título</th><th>Tipo</th><th>Vistas</th><th>Likes</th><th>Ratio</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($stats['top_vistas'])): ?>
                                            <?php foreach ($stats['top_vistas'] as $i => $item): ?>
                                                <tr>
                                                    <td class="rank-<?php echo min($i+1, 3); ?>"><?php echo $i+1; ?></td>
                                                    <td style="max-width:200px; overflow:hidden;">
                                                        <?php echo htmlspecialchars($item['titulo']); ?>
                                                    </td>
                                                    <td><span class="badge-<?php echo $item['tipo']; ?>"><?php echo $item['tipo']; ?></span></td>
                                                    <td><strong><?php echo $item['vistas']; ?></strong></td>
                                                    <td><?php echo $item['likes']; ?></td>
                                                    <td><?php echo $item['ratio']; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center">Sin datos</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Segunda fila: Gráficos -->
                <div class="row">
                    <!-- Distribución por tipo -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-pie-chart"></i> Distribución por Tipo</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="tipoChart"></canvas>
                            </div>
                            <div class="row mt-3">
                                <?php foreach ($stats['distribucion'] ?? [] as $dist): ?>
                                    <div class="col-6">
                                        <div class="info-card">
                                            <div class="info-value"><?php echo $dist['cantidad']; ?></div>
                                            <div class="info-label">
                                                <i class="fa fa-<?php echo $dist['tipo'] == 'video' ? 'video-camera' : 'music'; ?>"></i>
                                                <?php echo ucfirst($dist['tipo']); ?>s
                                            </div>
                                            <small><?php echo $dist['total_vistas']; ?> vistas</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Extensiones más usadas -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-file-o"></i> Extensiones Más Usadas</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="extensionChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <?php foreach (array_slice($stats['extensiones'] ?? [], 0, 5) as $ext): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge badge-secondary mr-2"><?php echo $ext['extension']; ?></span>
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo ($ext['cantidad'] / ($stats['general']['total'] ?? 1)) * 100; ?>%"></div>
                                        </div>
                                        <span class="ml-2"><?php echo $ext['cantidad']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tercera fila: Engagement y Actividad -->
                <div class="row">
                    <!-- Engagement por rango -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-bar-chart"></i> Engagement por Rango de Vistas</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="engagementChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actividad reciente -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="ranking-header">
                                <h3><i class="fa fa-calendar"></i> Actividad Últimos 30 Días</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="actividadChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <footer class="footer-dashboard">
                    <div class="pull-right">
                        <i class="fa fa-copyright"></i> RANSA <?php echo date('Y'); ?> | 
                        <i class="fa fa-refresh"></i> Actualizado: <?php echo date('d/m/Y H:i'); ?>
                    </div>
                    <div class="clearfix"></div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="vendors/jquery/dist/jquery.min.js"></script>
    <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="vendors/fastclick/lib/fastclick.js"></script>
    <script src="vendors/nprogress/nprogress.js"></script>
    <script src="build/js/custom.min.js"></script>

    <script>
    $(document).ready(function() {
        // Gráfico de distribución por tipo
        <?php if (!empty($stats['distribucion'])): ?>
        const tipoCtx = document.getElementById('tipoChart').getContext('2d');
        new Chart(tipoCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($stats['distribucion'], 'tipo'), JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($stats['distribucion'], 'cantidad')); ?>,
                    backgroundColor: ['#2196F3', '#4CAF50'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        <?php endif; ?>

        // Gráfico de extensiones
        <?php if (!empty($stats['extensiones'])): ?>
        const extCtx = document.getElementById('extensionChart').getContext('2d');
        const extData = <?php echo json_encode(array_slice($stats['extensiones'], 0, 5), JSON_UNESCAPED_UNICODE); ?>;
        new Chart(extCtx, {
            type: 'bar',
            data: {
                labels: extData.map(e => e.extension),
                datasets: [{
                    label: 'Cantidad',
                    data: extData.map(e => e.cantidad),
                    backgroundColor: '#2196F3'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
        <?php endif; ?>

        // Gráfico de engagement
        <?php if (!empty($stats['engagement'])): ?>
        const engCtx = document.getElementById('engagementChart').getContext('2d');
        new Chart(engCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($stats['engagement'], 'rango'), JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'Engagement %',
                    data: <?php echo json_encode(array_column($stats['engagement'], 'engagement')); ?>,
                    backgroundColor: '#FF5722'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
        <?php endif; ?>

        // Gráfico de actividad
        <?php if (!empty($stats['actividad'])): ?>
        const actCtx = document.getElementById('actividadChart').getContext('2d');
        const actData = <?php echo json_encode(array_slice($stats['actividad'], 0, 14), JSON_UNESCAPED_UNICODE); ?>;
        new Chart(actCtx, {
            type: 'line',
            data: {
                labels: actData.map(a => a.fecha).reverse(),
                datasets: [
                    { 
                        label: 'Vistas', 
                        data: actData.map(a => a.vistas_dia).reverse(), 
                        borderColor: '#2196F3', 
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    { 
                        label: 'Likes', 
                        data: actData.map(a => a.likes_dia).reverse(), 
                        borderColor: '#FF5722',
                        backgroundColor: 'rgba(255, 87, 34, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto-refresh cada 5 minutos
        setInterval(() => location.reload(), 300000);
    });

    function cerrarSesion() {
        if (confirm('¿Cerrar sesión?')) {
            window.location.href = 'logout.php';
        }
    }

    // Atajos de teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F1') { 
            e.preventDefault(); 
            window.location.href = 'main.php'; 
        }
        if (e.key === 'F5') { 
            e.preventDefault(); 
            location.reload(); 
        }
    });
    </script>
</body>
</html>