<?php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'calendar';
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

// Obtener fecha si se pasa por parámetro
$fecha_seleccionada = $_GET['fecha'] ?? '';

// Obtener usuarios para admin
$usuarios = [];
if (isAdmin()) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT usuario, nombre, grupo FROM users ORDER BY nombre";
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
        $database = new Database();
        $db = $database->getConnection();
        
        $titulo = $_POST['titulo'] ?? '';
        $url = $_POST['url'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $minutos = $_POST['minutos'] ?? '';
        $usuario_seleccionado = $_POST['usuario_seleccionado'] ?? '';
        
        // Validaciones
        if (empty($titulo) || empty($fecha) || empty($hora) || empty($minutos)) {
            throw new Exception('Todos los campos obligatorios deben ser llenados');
        }
        
        // Validar fecha
        if (!strtotime($fecha)) {
            throw new Exception('Fecha no válida');
        }
        
        // Validar hora
        if (!preg_match('/^\d{2}$/', $hora) || $hora < 8 || $hora > 20) {
            throw new Exception('Hora no válida');
        }
        
        // Validar minutos
        $minutos_validos = ['00', '10', '20', '30', '40', '50'];
        if (!in_array($minutos, $minutos_validos)) {
            throw new Exception('Minutos no válidos');
        }
        
        // Construir hora completa
        $hora_completa = $hora . ':' . $minutos . ':00';
        
        // Determinar usuario y grupo
        if (isAdmin() && !empty($usuario_seleccionado)) {
            $final_usuario_login = $usuario_seleccionado;
            
            // Obtener el grupo del usuario seleccionado
            $user_query = "SELECT usuario, grupo FROM users WHERE usuario = :usuario_login";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':usuario_login', $final_usuario_login);
            $user_stmt->execute();
            $usuario_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario_data) {
                throw new Exception('Usuario no válido');
            }
            
            $grupo = $usuario_data['grupo'];
        } else {
            $final_usuario_login = $usuario_actual['usuario'];
            $grupo = $usuario_actual['grupo']; // Grupo del usuario de sesión
        }
        
        // Insertar cita CON GRUPO
        $query = "INSERT INTO citas (titulo, descripcion, fecha, hora, usuario_id, grupo) 
                  VALUES (:titulo, :descripcion, :fecha, :hora, :usuario_login, :grupo)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':titulo', $titulo);
        $stmt->bindParam(':descripcion', $url);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora_completa);
        $stmt->bindParam(':usuario_login', $final_usuario_login);
        $stmt->bindParam(':grupo', $grupo);
        
        if ($stmt->execute()) {
            // Usar JavaScript para redireccionar ya que ya enviamos headers
            echo '<script>window.location.href = "calendar.php?success=Cita guardada exitosamente";</script>';
            exit;
        } else {
            throw new Exception('Error al guardar en la base de datos');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Nueva Cita</div>
        <!-- Mismo user info que calendar_simple.php -->
    </div>
    
    <div class="content-area">
        <div class="form-container">
            <div class="form-header">
                <h2>Crear Nueva Cita</h2>
                <a href="calendar.php" class="btn btn-secondary">Volver al Calendario</a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="notification error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="appointment-form">
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required 
                           value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>"
                           placeholder="Ingrese el título de la cita">
                </div>
                
                <div class="form-group">
                    <label for="url">TP</label>
                    <input type="text" id="url" name="url" 
                           value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>"
                           placeholder="Ej: TP-000000">
                    <small class="form-help">Ingrese el TP del Cliente</small>
                </div>
                
                <div class="form-group">
                    <label for="fecha">Fecha *</label>
                    <input type="date" id="fecha" name="fecha" required 
                           value="<?php echo htmlspecialchars($_POST['fecha'] ?? $fecha_seleccionada); ?>">
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
                                $selected = ($_POST['hora'] ?? '') === $value ? 'selected' : '';
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
                                $selected = ($_POST['minutos'] ?? '') === $minuto ? 'selected' : '';
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
                            $selected = ($_POST['usuario_seleccionado'] ?? '') === $usuario['usuario'] ? 'selected' : '';
                            $info_grupo = $usuario['grupo'] ? " - Grupo: " . $usuario['grupo'] : "";
                            ?>
                            <option value="<?php echo htmlspecialchars($usuario['usuario']); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($usuario['nombre'] . ' (' . $usuario['usuario'] . $info_grupo . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <a href="calendar.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cita</button>
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

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.notification {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.notification.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>