<?php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'calendar';
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

// Verificar que se pasó un ID
if (!isset($_GET['id'])) {
    echo '<script>window.location.href = "calendar.php?error=ID de cita no especificado";</script>';
    exit;
}

$cita_id = (int)$_GET['id'];

// Obtener datos de la cita
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT c.*, u.nombre as usuario_nombre 
              FROM citas c 
              LEFT JOIN users u ON c.usuario_id = u.usuario 
              WHERE c.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $cita_id);
    $stmt->execute();
    
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cita) {
        echo '<script>window.location.href = "calendar.php?error=Cita no encontrada";</script>';
        exit;
    }
    
    // Verificar permisos (solo admin o el dueño de la cita puede editar)
    if (!isAdmin() && $cita['usuario_id'] !== $usuario_actual['usuario']) {
        echo '<script>window.location.href = "calendar.php?error=No tienes permisos para editar esta cita";</script>';
        exit;
    }
    
} catch (Exception $e) {
    echo '<script>window.location.href = "calendar.php?error=Error al cargar cita: ' . urlencode($e->getMessage()) . '";</script>';
    exit;
}

// Obtener usuarios para admin
$usuarios = [];
if (isAdmin()) {
    try {
        $query = "SELECT usuario, nombre FROM users ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al cargar usuarios: " . $e->getMessage();
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titulo = $_POST['titulo'] ?? '';
        $url = $_POST['url'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $minutos = $_POST['minutos'] ?? '';
        $usuario_seleccionado = $_POST['usuario_seleccionado'] ?? '';
        
        // Validaciones (igual que nueva_cita.php)
        if (empty($titulo) || empty($fecha) || empty($hora) || empty($minutos)) {
            throw new Exception('Todos los campos obligatorios deben ser llenados');
        }
        
        if (!strtotime($fecha)) {
            throw new Exception('Fecha no válida');
        }
        
        if (!preg_match('/^\d{2}$/', $hora) || $hora < 8 || $hora > 20) {
            throw new Exception('Hora no válida');
        }
        
        $minutos_validos = ['00', '10', '20', '30', '40', '50'];
        if (!in_array($minutos, $minutos_validos)) {
            throw new Exception('Minutos no válidos');
        }
        
        $hora_completa = $hora . ':' . $minutos . ':00';
        
        // Determinar usuario
        if (isAdmin() && !empty($usuario_seleccionado)) {
            $final_usuario_login = $usuario_seleccionado;
        } else {
            $final_usuario_login = $cita['usuario_id']; // Mantener el usuario original
        }
        
        // Actualizar cita
        $query = "UPDATE citas SET titulo = :titulo, descripcion = :descripcion, 
                  fecha = :fecha, hora = :hora, usuario_id = :usuario_login 
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descripcion', $url);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora_completa);
        $stmt->bindParam(':usuario_login', $final_usuario_login);
        $stmt->bindParam(':id', $cita_id);
        
        if ($stmt->execute()) {
            echo '<script>window.location.href = "calendar.php?success=Cita actualizada exitosamente";</script>';
            exit;
        } else {
            throw new Exception('Error al actualizar en la base de datos');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Extraer hora y minutos de la cita
$hora_parts = explode(':', $cita['hora']);
$hora_actual = $hora_parts[0];
$minutos_actual = $hora_parts[1];
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Editar Cita</div>
        <div class="user-actions">
            <div class="user-info-top">
                <span class="user-name-top"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></span>
                <span class="user-ext-top">Ext: <?php echo htmlspecialchars($usuario_actual['ext']); ?></span>
                <div class="user-avatar-top" style="background-color: #e74c3c;">
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
            </div>
        </div>
    </div>
    
    <div class="content-area">
        <div class="form-container">
            <div class="form-header">
                <h2>Editar Cita</h2>
                <a href="calendar.php" class="btn btn-secondary">Volver al Calendario</a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="notification error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="appointment-form">
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required 
                           value="<?php echo htmlspecialchars($cita['titulo']); ?>"
                           placeholder="Ingrese el título de la cita">
                </div>
                
                <div class="form-group">
                    <label for="url">TP</label>
                    <input type="text" id="url" name="url" 
                           value="<?php echo htmlspecialchars($cita['descripcion']); ?>"
                           placeholder="Ej: TP-000000">
                    <small class="form-help">Ingrese el TP del cliente</small>
                </div>
                
                <div class="form-group">
                    <label for="fecha">Fecha *</label>
                    <input type="date" id="fecha" name="fecha" required 
                           value="<?php echo htmlspecialchars($cita['fecha']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hora">Hora *</label>
                        <select id="hora" name="hora" required>
                            <option value="">Seleccionar hora</option>
                            <?php for ($i = 8; $i <= 20; $i++): ?>
                                <?php
                                $hour = $i > 12 ? $i - 12 : $i;
                                $ampm = $i >= 12 ? 'pm' : 'am';
                                $value = str_pad($i, 2, '0', STR_PAD_LEFT);
                                $selected = $hora_actual === $value ? 'selected' : '';
                                ?>
                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>>
                                    <?php echo $hour . ':00 ' . $ampm; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="minutos">Minutos *</label>
                        <select id="minutos" name="minutos" required>
                            <option value="">Seleccionar minutos</option>
                            <?php
                            $minutos_opciones = ['00', '10', '20', '30', '40', '50'];
                            foreach ($minutos_opciones as $minuto) {
                                $selected = $minutos_actual === $minuto ? 'selected' : '';
                                echo "<option value=\"$minuto\" $selected>$minuto</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <?php if (isAdmin() && !empty($usuarios)): ?>
                <div class="form-group">
                    <label for="usuario_seleccionado">Usuario</label>
                    <select id="usuario_seleccionado" name="usuario_seleccionado">
                        <option value="">Seleccionar usuario</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <?php
                            $selected = $cita['usuario_id'] === $usuario['usuario'] ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($usuario['usuario']); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($usuario['nombre'] . ' (' . $usuario['usuario'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <a href="calendar.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Actualizar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.appointment-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input, 
.form-group select {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
    background-color: #fff;
}

.form-group input:focus, 
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group input::placeholder {
    color: #adb5bd;
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #6c757d;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
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

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 30px;
    background: white;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 30px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
}

.user-info-top {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-name-top {
    font-weight: 500;
    color: #2c3e50;
}

.user-ext-top {
    color: #6c757d;
    font-size: 14px;
}

.user-avatar-top {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}
</style>