<?php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'calendar';
$usuario_actual = getCurrentUser();
include '../includes/header.php';
include '../includes/sidebar.php';

// Obtener fecha (ahora día específico en lugar de mes)
$dia_actual = isset($_GET['dia']) ? $_GET['dia'] : date('Y-m-d');
$mes_actual = date('n', strtotime($dia_actual));
$anio_actual = date('Y', strtotime($dia_actual));

// Validaciones
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia_actual)) {
    $dia_actual = date('Y-m-d');
}

// DEBUG: Mostrar información del usuario en consola
echo "<script>";
echo "console.log('=== DEBUG USUARIO ===');";
echo "console.log('Usuario:', " . json_encode($usuario_actual) . ");";
echo "console.log('Usuario login:', '" . ($usuario_actual['usuario'] ?? 'NO DEFINIDO') . "');";
echo "</script>";

// Obtener citas para el día específico SOLO para el usuario actual
try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener usuario_login desde la sesión
    $usuario_login = $usuario_actual['usuario'];
    
    // DEBUG: Mostrar información del usuario
    echo "<script>";
    echo "console.log('=== DEBUG USUARIO ===');";
    echo "console.log('Usuario actual:', " . json_encode($usuario_actual) . ");";
    echo "console.log('Usuario login:', '" . $usuario_login . "');";
    echo "</script>";
    
    // Consulta SOLO para el usuario actual
    $query = "SELECT c.id, c.titulo, c.descripcion, c.fecha, c.hora, u.nombre as usuario_nombre, u.usuario as usuario_login
              FROM citas c 
              LEFT JOIN users u ON c.usuario_id = u.usuario 
              WHERE c.fecha = :fecha_dia AND c.usuario_id = :usuario_login
              ORDER BY c.hora";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha_dia', $dia_actual);
    $stmt->bindParam(':usuario_login', $usuario_login);
    
    // DEBUG: Mostrar la consulta y parámetros
    echo "<script>";
    echo "console.log('=== DEBUG CONSULTA ===');";
    echo "console.log('Consulta SQL:', " . json_encode($query) . ");";
    echo "console.log('Fecha:', '" . $dia_actual . "');";
    echo "console.log('Usuario:', '" . $usuario_login . "');";
    echo "</script>";
    
    $stmt->execute();
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Mostrar resultados
    echo "<script>";
    echo "console.log('=== DEBUG RESULTADOS ===');";
    echo "console.log('Número de citas encontradas:', " . count($citas) . ");";
    echo "console.log('Citas:', " . json_encode($citas) . ");";
    echo "</script>";
    
} catch (Exception $e) {
    $citas = [];
    $error = "Error al cargar citas: " . $e->getMessage();
    echo "<script>console.error('Error en consulta:', " . json_encode($error) . ");</script>";
}

// Resto del código permanece igual...
// Navegación entre días
$dia_anterior = date('Y-m-d', strtotime($dia_actual . ' -1 day'));
$dia_siguiente = date('Y-m-d', strtotime($dia_actual . ' +1 day'));
$hoy = date('Y-m-d');

// Función para extraer TP de la descripción
function extraerTP($descripcion) {
    $tp = '';
    if (!empty($descripcion)) {
        if (preg_match('/TP[_-]?(\d+)/i', $descripcion, $matches)) {
            $tp = $matches[1];
        }
    }
    return $tp;
}

// Función para extraer el formato completo del TP
function extraerTPCompleto($descripcion) {
    $tp_completo = '';
    if (!empty($descripcion)) {
        if (preg_match('/(TP[_-]?\d+)/i', $descripcion, $matches)) {
            $tp_completo = $matches[1];
        }
    }
    return $tp_completo;
}

// Función para generar color único para cada agente
function generarColorAgente($nombre_agente) {
    $colores = [
        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
        '#1abc9c', '#d35400', '#c0392b', '#16a085', '#8e44ad',
        '#27ae60', '#2980b9', '#f1c40f', '#e67e22', '#34495e',
        '#7f8c8d', '#2c3e50', '#95a5a6', '#bdc3c7'
    ];
    $indice = crc32($nombre_agente) % count($colores);
    return $colores[$indice];
}

// Función para oscurecer color
function oscurecerColor($color, $porcentaje = 20) {
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
    $r = max(0, min(255, $r - ($r * $porcentaje / 100)));
    $g = max(0, min(255, $g - ($g * $porcentaje / 100)));
    $b = max(0, min(255, $b - ($b * $porcentaje / 100)));
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Formatear fecha para mostrar
$fecha_formateada = date('d/m/Y', strtotime($dia_actual));
$nombre_dia = [
    'Sunday' => 'Domingo',
    'Monday' => 'Lunes', 
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado'
][date('l', strtotime($dia_actual))];
?>
<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Calendario - Vista Diaria</div>
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
                <span class="user-name-top <?php echo isTL() ? 'admin-user' : ''; ?>">
                    <?php echo htmlspecialchars($usuario_actual['nombre']); ?>
                    <?php if (isTL()): ?>
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
                        <!-- Mostrar grupo del usuario -->
                        <small>Grupo: <?php echo htmlspecialchars($usuario_actual['grupo'] ?? 'No asignado'); ?></small>
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

        <!-- Controles del calendario DIARIO -->
        <div class="calendar-controls">
            <div class="calendar-navigation">
                <a href="calendar.php?dia=<?php echo $dia_anterior; ?>" class="btn-nav">
                    <i class="fas fa-chevron-left"></i> Día Anterior
                </a>
                <div class="current-date">
                    <h2><?php echo $nombre_dia . ' ' . $fecha_formateada; ?></h2>
                    <?php if ($dia_actual == $hoy): ?>
                        <span class="today-badge">Hoy</span>
                    <?php endif; ?>
                </div>
                <a href="calendar.php?dia=<?php echo $dia_siguiente; ?>" class="btn-nav">
                    Día Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="calendar-actions">
                <a href="calendar.php" class="btn btn-secondary">
                    <i class="fas fa-calendar-day"></i> Hoy
                </a>
                <a href="nueva_cita.php?fecha=<?php echo $dia_actual; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Cita
                </a>
                <!-- Selector de fecha -->
                <div class="date-picker">
                    <input type="date" id="dateSelector" value="<?php echo $dia_actual; ?>" 
                           onchange="window.location.href = 'calendar.php?dia=' + this.value">
                </div>
            </div>
        </div>

        <!-- Vista Diaria -->
        <div class="daily-view">
            <?php if (empty($citas)): ?>
                <div class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No hay citas para este día</h3>
                    <p>Puedes agregar una nueva cita usando el botón "Nueva Cita"</p>
                </div>
            <?php else: ?>
                <div class="daily-appointments">
                    <?php foreach ($citas as $cita): ?>
                        <?php
                        $time = substr($cita['hora'], 0, 5);
                        $hours = (int)substr($time, 0, 2);
                        $minutes = substr($time, 3, 2);
                        $display_hour = $hours > 12 ? $hours - 12 : $hours;
                        if ($display_hour == 0) $display_hour = 12;
                        $ampm = $hours >= 12 ? 'pm' : 'am';
                        
                        $tp = extraerTP($cita['descripcion']);
                        $tp_completo = extraerTPCompleto($cita['descripcion']);
                        $titulo = htmlspecialchars($cita['titulo']);
                        $descripcion = htmlspecialchars($cita['descripcion']);
                        
                        $color_agente = '#3498db';
                        $color_borde = oscurecerColor($color_agente, 20);
                        ?>
                        
                        <div class="daily-appointment" onclick="filtrarTP('<?php echo $tp_completo; ?>')" 
                             style="border-left-color: <?php echo $color_borde; ?>;">
                            <div class="appointment-time">
                                <span class="time"><?php echo $display_hour . ':' . $minutes . ' ' . $ampm; ?></span>
                            </div>
                            <div class="appointment-content">
                                <h4 class="appointment-title"><?php echo $titulo; ?></h4>
                                <?php if (!empty($descripcion)): ?>
                                    <p class="appointment-description"><?php echo $descripcion; ?></p>
                                <?php endif; ?>
                                <div class="appointment-meta">
                                    <?php if ($tp): ?>
                                        <span class="appointment-tp">TP: <?php echo $tp; ?></span>
                                    <?php else: ?>
                                        <span class="appointment-no-tp">Sin TP</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="appointment-actions">
                                <a href="editar_cita.php?id=<?php echo $cita['id']; ?>" class="btn-edit" title="Editar cita" onclick="event.stopPropagation()">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="eliminar_cita.php?id=<?php echo $cita['id']; ?>" class="btn-delete" title="Eliminar cita" onclick="event.stopPropagation(); return confirm('¿Estás seguro de eliminar esta cita?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ===== ESTILOS PARA VISTA DIARIA ===== */
.daily-view {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
}

.no-appointments {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.no-appointments i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #bdc3c7;
}

.no-appointments h3 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}

.no-appointments p {
    margin: 0;
    color: #7f8c8d;
}

.daily-appointments {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.daily-appointment {
    display: flex;
    align-items: flex-start;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #3498db;
    transition: all 0.3s ease;
    cursor: pointer;
}

.daily-appointment:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    background: white;
}

.appointment-time {
    min-width: 80px;
    padding-right: 20px;
}

.appointment-time .time {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
}

.appointment-content {
    flex: 1;
}

.appointment-content h4 {
    margin: 0 0 8px 0;
    color: #2c3e50;
    font-size: 16px;
}

.appointment-description {
    margin: 0 0 10px 0;
    color: #5d6d7e;
    line-height: 1.4;
}

.appointment-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.appointment-tp, .appointment-no-tp, .appointment-user {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.appointment-tp {
    background: #3498db;
    color: white;
}

.appointment-no-tp {
    background: #95a5a6;
    color: white;
}

.appointment-user {
    background: #e74c3c;
    color: white;
}

.appointment-actions {
    display: flex;
    gap: 8px;
    margin-left: 15px;
}

.btn-edit, .btn-delete {
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-edit {
    background: #3498db;
    color: white;
}

.btn-delete {
    background: #e74c3c;
    color: white;
}

.btn-edit:hover, .btn-delete:hover {
    transform: scale(1.1);
    opacity: 0.9;
}

/* Selector de fecha */
.date-picker input[type="date"] {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.today-badge {
    background: #e74c3c;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}

/* ===== ESTILOS EXISTENTES (conservados) ===== */
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
    display: flex;
    align-items: center;
}

.btn-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
    gap: 8px;
}

.btn-nav:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

.calendar-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

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

/* Responsive */
@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .calendar-navigation {
        order: 2;
        flex-direction: column;
        gap: 10px;
    }
    
    .calendar-actions {
        order: 1;
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .daily-appointment {
        flex-direction: column;
        gap: 15px;
    }
    
    .appointment-time {
        padding-right: 0;
        text-align: center;
    }
    
    .appointment-actions {
        margin-left: 0;
        justify-content: center;
    }
}
</style>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

function filtrarTP(tp_completo) {
    if (tp_completo) {
        window.open('leads.php?search=' + encodeURIComponent(tp_completo) + '&pagina=1', '_blank');
    } else {
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

window.addEventListener('click', function(e) {
    if (!e.target.closest('.user-info-top')) {
        document.getElementById('userMenu').style.display = 'none';
    }
});
</script>