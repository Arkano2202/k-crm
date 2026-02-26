<?php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'calendar';
$usuario_actual = getCurrentUser();
include '../includes/header.php';
include '../includes/sidebar.php';

// Obtener mes y año
$mes_actual = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_actual = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Validaciones
if ($mes_actual < 1 || $mes_actual > 12) $mes_actual = date('n');
if ($anio_actual < 2020 || $anio_actual > 2030) $anio_actual = date('Y');

// Obtener citas directamente
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $start_date = "$anio_actual-$mes_actual-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT c.id, c.titulo, c.descripcion, c.fecha, c.hora, u.nombre as usuario_nombre, u.usuario as usuario_login
              FROM citas c 
              LEFT JOIN users u ON c.usuario_id = u.usuario 
              WHERE c.fecha BETWEEN :start_date AND :end_date";
    
    if (!isAdmin()) {
        $query .= " AND c.usuario_id = :usuario_login";
    }
    
    $query .= " ORDER BY c.fecha, c.hora";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    
    if (!isAdmin()) {
        $stmt->bindParam(':usuario_login', $usuario_actual['usuario']);
    }
    
    $stmt->execute();
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar citas por fecha para fácil acceso
    $citas_por_fecha = [];
    foreach ($citas as $cita) {
        $citas_por_fecha[$cita['fecha']][] = $cita;
    }
    
} catch (Exception $e) {
    $citas_por_fecha = [];
    $error = "Error al cargar citas: " . $e->getMessage();
}

// Cálculos del calendario
$primer_dia = date('N', strtotime("$anio_actual-$mes_actual-01"));
$ultimo_dia = date('t', strtotime("$anio_actual-$mes_actual-01"));
$dias_mes_anterior = $primer_dia - 1;
$total_celdas = ceil(($dias_mes_anterior + $ultimo_dia) / 7) * 7;

// Navegación
$mes_anterior = $mes_actual - 1;
$anio_anterior = $anio_actual;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $anio_anterior = $anio_actual - 1;
}

$mes_siguiente = $mes_actual + 1;
$anio_siguiente = $anio_actual;
if ($mes_siguiente > 12) {
    $mes_siguiente = 1;
    $anio_siguiente = $anio_actual + 1;
}

$nombres_meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Función para extraer TP de la descripción - CORREGIDA
function extraerTP($descripcion) {
    $tp = '';
    
    // Buscar TP en la descripción con diferentes patrones:
    // TP_00001, TP-000001, TP00001, etc.
    if (!empty($descripcion)) {
        // Patrón 1: TP seguido de guión bajo o guión y luego números
        if (preg_match('/TP[_-]?(\d+)/i', $descripcion, $matches)) {
            $tp = $matches[1];
        }
    }
    
    return $tp;
}

// Función para extraer el formato completo del TP (con guiones)
function extraerTPCompleto($descripcion) {
    $tp_completo = '';
    
    // Buscar el formato completo del TP (TP-000001, TP_00001, etc.)
    if (!empty($descripcion)) {
        if (preg_match('/(TP[_-]?\d+)/i', $descripcion, $matches)) {
            $tp_completo = $matches[1];
        }
    }
    
    return $tp_completo;
}

// Función para generar color único para cada agente
function generarColorAgente($nombre_agente) {
    // Lista de colores predefinidos (colores pastel distintivos)
    $colores = [
        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
        '#1abc9c', '#d35400', '#c0392b', '#16a085', '#8e44ad',
        '#27ae60', '#2980b9', '#f1c40f', '#e67e22', '#34495e',
        '#7f8c8d', '#2c3e50', '#95a5a6', '#bdc3c7'
    ];
    
    // Generar índice basado en el nombre del agente
    $indice = crc32($nombre_agente) % count($colores);
    return $colores[$indice];
}

// Función para oscurecer color (para bordes)
function oscurecerColor($color, $porcentaje = 20) {
    // Convertir color HEX a RGB
    $hex = str_replace('#', '', $color);
    
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    
    // Oscurecer el color
    $r = max(0, min(255, $r - ($r * $porcentaje / 100)));
    $g = max(0, min(255, $g - ($g * $porcentaje / 100)));
    $b = max(0, min(255, $b - ($b * $porcentaje / 100)));
    
    // Convertir de vuelta a HEX
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Obtener lista de agentes únicos para la leyenda
$agentes_unicos = [];
if (isAdmin() && isset($citas)) {
    foreach ($citas as $cita) {
        if ($cita['usuario_nombre']) {
            $agentes_unicos[$cita['usuario_nombre']] = generarColorAgente($cita['usuario_nombre']);
        }
    }
}
?>
<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Calendario</div>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar...">
        </div>
        <div class="user-actions">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <div class="notification-badge">3</div>
            </div>
            <div class="user-info-top">
                <span class="user-name-top <?php echo isAdmin() ? 'admin-user' : ''; ?>">
                    <?php echo htmlspecialchars($usuario_actual['nombre']); ?>
                    <?php if (isAdmin()): ?>
                        <span class="admin-badge">(Admin)</span>
                    <?php endif; ?>
                </span>
                <span class="user-ext-top">Ext: <?php echo htmlspecialchars($usuario_actual['ext']); ?></span>
                <div class="user-avatar-top" style="background-color: #e74c3c; cursor: pointer;" onclick="toggleUserMenu()">
                    <?php 
                    $nombres = explode(' ', $usuario_actual['nombre']);
                    $iniciales_top = '';
                    foreach ($nombres as $nombre) {
                        if (!empty($nombre)) {
                            $iniciales_top .= substr($nombre, 0, 1);
                        }
                    }
                    echo strtoupper(substr($iniciales_top, 0, 2));
                    ?>
                </div>
                <div class="user-menu" id="userMenu">
                    <a href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                    <div class="user-info-menu">
                        <small>Usuario: <?php echo htmlspecialchars($usuario_actual['usuario']); ?></small>
                        <small>Ext: <?php echo htmlspecialchars($usuario_actual['ext']); ?></small>
                        <small>Tipo: <?php echo htmlspecialchars($usuario_actual['tipo']); ?></small>
                    </div>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="content-area">
        <!-- Mostrar mensajes -->
        <?php if (isset($_GET['success'])): ?>
            <div class="notification success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notification error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Controles del calendario -->
        <div class="calendar-controls">
            <div class="calendar-navigation">
                <a href="calendar.php?mes=<?php echo $mes_anterior; ?>&anio=<?php echo $anio_anterior; ?>" class="btn-nav">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <div class="current-date">
                    <h2><?php echo $nombres_meses[$mes_actual] . ' ' . $anio_actual; ?></h2>
                </div>
                <a href="calendar.php?mes=<?php echo $mes_siguiente; ?>&anio=<?php echo $anio_siguiente; ?>" class="btn-nav">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="calendar-actions">
                <a href="calendar.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-day"></i> Hoy
                </a>
                <a href="nueva_cita.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Cita
                </a>
            </div>
        </div>

        <!-- Leyenda de colores para agentes (solo para admin) -->
        <?php if (isAdmin() && !empty($agentes_unicos)): ?>
        <div class="agents-legend">
            <h3>Leyenda de Agentes</h3>
            <div class="legend-items">
                <?php foreach ($agentes_unicos as $agente => $color): ?>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: <?php echo $color; ?>;"></span>
                    <span class="legend-name"><?php echo htmlspecialchars($agente); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendario -->
        <div class="month-calendar">
            <div class="calendar-header">
                <div class="week-day">Lun</div>
                <div class="week-day">Mar</div>
                <div class="week-day">Mié</div>
                <div class="week-day">Jue</div>
                <div class="week-day">Vie</div>
                <div class="week-day">Sáb</div>
                <div class="week-day">Dom</div>
            </div>
            
            <div class="calendar-grid">
                <?php
                // Días del mes anterior
                $mes_prev = $mes_actual - 1;
                $anio_prev = $anio_actual;
                if ($mes_prev < 1) {
                    $mes_prev = 12;
                    $anio_prev = $anio_actual - 1;
                }
                $ultimo_dia_mes_prev = date('t', strtotime("$anio_prev-$mes_prev-01"));
                
                for ($i = $dias_mes_anterior; $i > 0; $i--) {
                    $dia = $ultimo_dia_mes_prev - $i + 1;
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $dia . '</div>';
                    echo '</div>';
                }
                
                // Días del mes actual
                $hoy = date('Y-m-d');
                for ($dia = 1; $dia <= $ultimo_dia; $dia++) {
                    $fecha = "$anio_actual-$mes_actual-" . sprintf("%02d", $dia);
                    $es_hoy = ($fecha == $hoy) ? 'today' : '';
                    $dia_semana = date('N', strtotime($fecha)); // 1=lunes, 7=domingo
                    
                    echo '<div class="calendar-day ' . $es_hoy . '" data-dia-semana="' . $dia_semana . '">';
                    echo '<div class="day-number">' . $dia . '</div>';
                    echo '<div class="day-appointments">';
                    
                    // Mostrar citas directamente
                    if (isset($citas_por_fecha[$fecha])) {
                        foreach ($citas_por_fecha[$fecha] as $cita) {
                            $time = substr($cita['hora'], 0, 5);
                            $hours = (int)substr($time, 0, 2);
                            $minutes = substr($time, 3, 2);
                            $display_hour = $hours > 12 ? $hours - 12 : $hours;
                            if ($display_hour == 0) $display_hour = 12;
                            $ampm = $hours >= 12 ? 'pm' : 'am';
                            
                            // Extraer TP de la descripción
                            $tp = extraerTP($cita['descripcion']);
                            $tp_completo = extraerTPCompleto($cita['descripcion']);
                            $titulo = htmlspecialchars($cita['titulo']);
                            
                            // Generar color para el agente
                            $color_agente = isAdmin() && $cita['usuario_nombre'] ? 
                                generarColorAgente($cita['usuario_nombre']) : '#3498db';
                            
                            // Oscurecer color para el borde
                            $color_borde = oscurecerColor($color_agente, 20);
                            
                            echo '<div class="appointment" onclick="filtrarTP(\'' . $tp_completo . '\')" style="cursor: pointer; background-color: ' . $color_agente . '; border-left-color: ' . $color_borde . ';" title="Haz clic para abrir Leads filtrando por ' . ($tp_completo ? $tp_completo : 'sin TP') . ' (nueva pestaña)">';
                            echo '<div class="appointment-content">';
                            echo '<div class="appointment-time">' . $display_hour . ':' . $minutes . ' ' . $ampm . '</div>';
                            echo '<div class="appointment-title">' . $titulo . '</div>';
                            if ($tp) {
                                echo '<div class="appointment-tp">TP: ' . $tp . '</div>';
                            } else {
                                echo '<div class="appointment-no-tp">Sin TP</div>';
                            }
                            if (isAdmin() && $cita['usuario_nombre']) {
                                echo '<div class="appointment-user">' . htmlspecialchars($cita['usuario_nombre']) . '</div>';
                            }
                            echo '</div>';
                            echo '<div class="appointment-actions">';
                            echo '<a href="editar_cita.php?id=' . $cita['id'] . '" class="btn-edit" title="Editar cita" onclick="event.stopPropagation()"><i class="fas fa-edit"></i></a>';
                            echo '<a href="eliminar_cita.php?id=' . $cita['id'] . '" class="btn-delete" title="Eliminar cita" onclick="event.stopPropagation(); return confirm(\'¿Estás seguro de eliminar esta cita?\')"><i class="fas fa-trash"></i></a>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                    
                    // Botón para agregar cita en este día
                    echo '<a href="nueva_cita.php?fecha=' . $fecha . '" class="add-appointment-btn" title="Agregar cita">';
                    echo '<i class="fas fa-plus"></i>';
                    echo '</a>';
                    
                    echo '</div>';
                    echo '</div>';
                }
                
                // Días del siguiente mes
                $dias_restantes = $total_celdas - ($dias_mes_anterior + $ultimo_dia);
                $mes_next = $mes_actual + 1;
                $anio_next = $anio_actual;
                if ($mes_next > 12) {
                    $mes_next = 1;
                    $anio_next = $anio_actual + 1;
                }
                
                for ($dia = 1; $dia <= $dias_restantes; $dia++) {
                    echo '<div class="calendar-day other-month">';
                    echo '<div class="day-number">' . $dia . '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== ESTILOS PARA LA LEYENDA DE AGENTES ===== */
.agents-legend {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.agents-legend h3 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 2px solid rgba(0,0,0,0.1);
}

.legend-name {
    font-size: 14px;
    color: #2c3e50;
    font-weight: 500;
}

/* ===== ESTILOS MEJORADOS PARA EL NOMBRE DENTRO DE LAS CITAS ===== */
.appointment-user {
    font-size: 11px !important;
    font-weight: 600 !important;
    opacity: 0.9 !important;
    font-style: normal !important;
    color: rgba(255, 255, 255, 0.95) !important;
    margin-top: 3px;
    padding: 2px 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    display: inline-block;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.3);
}

/* ===== ESTILOS PARA EL NOMBRE EN LA BARRA SUPERIOR ===== */
.user-info-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-name-top {
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
    transition: all 0.3s ease;
}

.user-name-top.admin-user {
    font-size: 18px !important;
    font-weight: 700 !important;
    color: #e74c3c !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    padding: 4px 8px;
    background: rgba(231, 76, 60, 0.1);
    border-radius: 6px;
    border-left: 3px solid #e74c3c;
}

.admin-badge {
    font-size: 12px;
    background: #e74c3c;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 6px;
    font-weight: 600;
}

.user-ext-top {
    font-size: 12px;
    color: #7f8c8d;
    font-weight: 500;
}

.user-avatar-top {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s ease;
}

.user-avatar-top:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

/* ===== ESTILOS EXISTENTES DEL CALENDARIO ===== */
.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-date h2 {
    margin: 0;
    color: #2c3e50;
    min-width: 200px;
    text-align: center;
}

.btn-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
}

.btn-nav:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

/* Calendario Mensual */
.month-calendar {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #34495e;
    color: white;
    text-align: center;
}

.week-day {
    padding: 15px 10px;
    font-weight: bold;
    border-right: 1px solid #2c3e50;
    font-size: 14px;
}

.week-day:last-child {
    border-right: none;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #ecf0f1;
}

.calendar-day {
    background: white;
    min-height: 120px;
    padding: 8px;
    border: 1px solid #ecf0f1;
    position: relative;
}

.calendar-day:hover {
    background: #f8f9fa;
}

.calendar-day.today {
    background: #e3f2fd;
    border-color: #2196f3;
}

.calendar-day.other-month {
    background: #f8f9fa;
    color: #bdc3c7;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
    color: #2c3e50;
    font-size: 14px;
}

.other-month .day-number {
    color: #bdc3c7;
}

.today .day-number {
    color: #2196f3;
    font-weight: bold;
}

.day-appointments {
    min-height: 80px;
}

/* Citas */
.appointment {
    color: white;
    padding: 6px 8px;
    margin: 3px 0;
    border-radius: 4px;
    font-size: 11px;
    border-left: 3px solid;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: all 0.3s;
}

.appointment:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    filter: brightness(1.1);
}

.appointment-content {
    line-height: 1.3;
    flex: 1;
}

.appointment-time {
    font-weight: bold;
    font-size: 10px;
    margin-bottom: 2px;
}

.appointment-title {
    font-size: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 2px 0;
    font-weight: 500;
}

.appointment-tp {
    font-size: 9px;
    font-weight: bold;
    background: rgba(255,255,255,0.3);
    padding: 2px 5px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 3px;
    border: 1px solid rgba(255,255,255,0.2);
}

.appointment-no-tp {
    font-size: 8px;
    color: rgba(255,255,255,0.7);
    font-style: italic;
    margin-top: 2px;
}

.appointment-actions {
    display: flex;
    gap: 4px;
    margin-left: 5px;
}

.btn-edit, .btn-delete {
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 10px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-edit {
    background: rgba(255,255,255,0.3);
    color: white;
}

.btn-delete {
    background: rgba(255,255,255,0.3);
    color: white;
}

.btn-edit:hover, .btn-delete:hover {
    background: rgba(255,255,255,0.5);
    transform: scale(1.1);
}

/* Botón agregar cita */
.add-appointment-btn {
    display: block;
    text-align: center;
    padding: 4px;
    margin-top: 4px;
    background: #f8f9fa;
    border: 1px dashed #dee2e6;
    border-radius: 3px;
    color: #6c757d;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.3s;
}

.add-appointment-btn:hover {
    background: #e9ecef;
    color: #495057;
    border-color: #adb5bd;
}

/* Notificaciones */
.notification {
    padding: 12px 16px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-weight: 500;
}

.notification.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.notification.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Botones */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.calendar-actions {
    display: flex;
    gap: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .calendar-navigation {
        order: 2;
    }
    
    .calendar-actions {
        order: 1;
        width: 100%;
        justify-content: center;
    }
    
    .calendar-day {
        min-height: 100px;
        padding: 4px;
    }
    
    .day-number {
        font-size: 12px;
    }
    
    .appointment {
        padding: 4px 6px;
        font-size: 9px;
    }
    
    .appointment-title {
        font-size: 9px;
    }
    
    .appointment-user {
        font-size: 10px !important;
        padding: 1px 3px;
    }
    
    .user-info-top {
        gap: 8px;
    }
    
    .user-name-top.admin-user {
        font-size: 16px !important;
        padding: 3px 6px;
    }
    
    .admin-badge {
        font-size: 10px;
        padding: 1px 4px;
    }
    
    .user-ext-top {
        font-size: 11px;
    }
    
    .legend-items {
        gap: 10px;
    }
    
    .legend-item {
        gap: 6px;
    }
    
    .legend-color {
        width: 14px;
        height: 14px;
    }
    
    .legend-name {
        font-size: 12px;
    }
}
</style>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

// Función para filtrar por TP en la página de leads - CORREGIDA PARA ABRIR PESTAÑA NUEVA
function filtrarTP(tp_completo) {
    if (tp_completo) {
        // Abrir nueva pestaña con el filtro de búsqueda
        window.open('leads.php?search=' + encodeURIComponent(tp_completo) + '&pagina=1', '_blank');
    } else {
        // Mostrar mensaje informativo en lugar de alerta
        const mensaje = document.createElement('div');
        mensaje.className = 'notification info';
        mensaje.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1000; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 12px; border-radius: 4px;';
        mensaje.innerHTML = 'Esta cita no tiene un TP asociado para filtrar.';
        document.body.appendChild(mensaje);
        
        setTimeout(() => {
            mensaje.remove();
        }, 3000);
    }
}

// Cerrar menú al hacer click fuera
window.addEventListener('click', function(e) {
    if (!e.target.closest('.user-info-top')) {
        document.getElementById('userMenu').style.display = 'none';
    }
});

// Mejorar la experiencia en móviles
document.addEventListener('DOMContentLoaded', function() {
    // Agregar tooltips a los botones de acciones
    const editButtons = document.querySelectorAll('.btn-edit');
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    editButtons.forEach(btn => {
        btn.setAttribute('title', 'Editar cita');
    });
    
    deleteButtons.forEach(btn => {
        btn.setAttribute('title', 'Eliminar cita');
    });
});
</script>