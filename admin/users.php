<?php
// Siempre al inicio
include '../includes/session.php';
requireLogin(); // Si requiere autenticación

$pagina_actual = 'users';

// Obtener usuario actual con todos los campos
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Crear usuario
    if (isset($_POST['guardar_usuario'])) {
        $nombre = trim($_POST['nombre']);
        $usuario = trim($_POST['usuario']);
        $contrasena = trim($_POST['contrasena']);
        $extension = trim($_POST['extension']);
        $tipo = $_POST['tipo'];
        $grupo_id = $_POST['grupo'];
        
        // Validar campos requeridos
        if (!empty($nombre) && !empty($usuario) && !empty($contrasena) && !empty($extension) && !empty($tipo) && !empty($grupo_id)) {
            try {
                // Hash de la contraseña
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                
                // Insertar en la base de datos
                $query_insert = "INSERT INTO users (nombre, usuario, contraseña, ext, tipo, grupo_id, Grupo) 
                                VALUES (:nombre, :usuario, :contrasena, :extension, :tipo, :grupo_id, '0')";
                
                $stmt_insert = $db->prepare($query_insert);
                $stmt_insert->bindValue(':nombre', $nombre);
                $stmt_insert->bindValue(':usuario', $usuario);
                $stmt_insert->bindValue(':contrasena', $contrasena_hash);
                $stmt_insert->bindValue(':extension', $extension);
                $stmt_insert->bindValue(':tipo', $tipo);
                $stmt_insert->bindValue(':grupo_id', $grupo_id);
                
                if ($stmt_insert->execute()) {
                    $mensaje_exito = "Usuario creado correctamente";
                    // Usar JavaScript para redireccionar
                    echo '<script>window.location.href = "users.php?pagina=' . ($_GET['pagina'] ?? 1) . '&search=' . urlencode($_GET['search'] ?? '') . '";</script>';
                    exit();
                } else {
                    $errorInfo = $stmt_insert->errorInfo();
                    $mensaje_error = "Error al crear el usuario: " . $errorInfo[2];
                }
            } catch (PDOException $e) {
                $mensaje_error = "Error de base de datos: " . $e->getMessage();
            }
        } else {
            $mensaje_error = "Todos los campos son requeridos";
        }
    }
    
    // Editar usuario
    if (isset($_POST['editar_usuario'])) {
        $usuario_id = $_POST['usuario_id'];
        $extension = trim($_POST['extension']);
        $tipo = $_POST['tipo'];
        $grupo_id = $_POST['grupo'];
        $contrasena = trim($_POST['contrasena']);
        
        if (!empty($usuario_id)) {
            try {
                // Construir la consulta de actualización
                if (!empty($contrasena)) {
                    // Si hay nueva contraseña, actualizar también la contraseña
                    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                    $query_update = "UPDATE users SET ext = :extension, tipo = :tipo, grupo_id = :grupo_id, contraseña = :contrasena WHERE id = :id";
                } else {
                    // Si no hay nueva contraseña, no actualizar la contraseña
                    $query_update = "UPDATE users SET ext = :extension, tipo = :tipo, grupo_id = :grupo_id WHERE id = :id";
                }
                
                $stmt_update = $db->prepare($query_update);
                $stmt_update->bindValue(':extension', $extension);
                $stmt_update->bindValue(':tipo', $tipo);
                $stmt_update->bindValue(':grupo_id', $grupo_id);
                $stmt_update->bindValue(':id', $usuario_id, PDO::PARAM_INT);
                
                if (!empty($contrasena)) {
                    $stmt_update->bindValue(':contrasena', $contrasena_hash);
                }
                
                if ($stmt_update->execute()) {
                    $mensaje_exito = "Usuario actualizado correctamente";
                    // Usar JavaScript para redireccionar
                    echo '<script>window.location.href = "users.php?pagina=' . ($_GET['pagina'] ?? 1) . '&search=' . urlencode($_GET['search'] ?? '') . '";</script>';
                    exit();
                } else {
                    $errorInfo = $stmt_update->errorInfo();
                    $mensaje_error = "Error al actualizar el usuario: " . $errorInfo[2];
                }
            } catch (PDOException $e) {
                $mensaje_error = "Error de base de datos al actualizar: " . $e->getMessage();
            }
        } else {
            $mensaje_error = "ID de usuario no válido";
        }
    }
    
    // Eliminar usuario
    if (isset($_POST['eliminar_usuario'])) {
        $usuario_id = $_POST['usuario_id'];
        
        if (!empty($usuario_id)) {
            try {
                // Eliminar usuario de la base de datos
                $query_delete = "DELETE FROM users WHERE id = :id";
                
                $stmt_delete = $db->prepare($query_delete);
                $stmt_delete->bindValue(':id', $usuario_id, PDO::PARAM_INT);
                
                if ($stmt_delete->execute()) {
                    $mensaje_exito = "Usuario eliminado correctamente";
                    // Usar JavaScript para redireccionar
                    echo '<script>window.location.href = "users.php?pagina=' . ($_GET['pagina'] ?? 1) . '&search=' . urlencode($_GET['search'] ?? '') . '";</script>';
                    exit();
                } else {
                    $errorInfo = $stmt_delete->errorInfo();
                    $mensaje_error = "Error al eliminar el usuario: " . $errorInfo[2];
                }
            } catch (PDOException $e) {
                $mensaje_error = "Error de base de datos al eliminar: " . $e->getMessage();
            }
        } else {
            $mensaje_error = "ID de usuario no válido";
        }
    }
}

// Obtener datos del usuario para editar si se solicita
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $usuario_id = $_GET['editar'];
    try {
        $query_editar = "SELECT u.id, u.nombre, u.usuario, u.ext, u.tipo, u.grupo_id 
                        FROM users u 
                        WHERE u.id = :id";
        $stmt_editar = $db->prepare($query_editar);
        $stmt_editar->bindValue(':id', $usuario_id, PDO::PARAM_INT);
        $stmt_editar->execute();
        $usuario_editar = $stmt_editar->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_error = "Error al cargar datos del usuario: " . $e->getMessage();
    }
}

// Configuración de paginación y búsqueda
$registros_por_pagina = 20;
$pagina_actual_num = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$search_term = $_GET['search'] ?? '';
$offset = ($pagina_actual_num - 1) * $registros_por_pagina;

// Construir consultas basadas en búsqueda
$where_conditions = [];
$params = [];

if (!empty($search_term)) {
    $where_conditions[] = "(u.nombre LIKE :search OR u.usuario LIKE :search OR u.ext LIKE :search OR t.Grupo LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para el total de registros
try {
    $query_total = "SELECT COUNT(*) as total FROM users u 
                   LEFT JOIN t_user t ON u.tipo = t.id 
                   $where_clause";
    $stmt_total = $db->prepare($query_total);
    
    foreach ($params as $key => $value) {
        $stmt_total->bindValue($key, $value);
    }
    
    $stmt_total->execute();
    $total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Asegurar que la página actual no exceda el total de páginas
    if ($pagina_actual_num > $total_paginas && $total_paginas > 0) {
        $pagina_actual_num = $total_paginas;
        $offset = ($pagina_actual_num - 1) * $registros_por_pagina;
    }
} catch (PDOException $e) {
    $total_registros = 0;
    $total_paginas = 1;
    $mensaje_error = "Error en la consulta: " . $e->getMessage();
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Gestión de Usuarios</div>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <form method="GET" action="users.php" style="display: flex; align-items: center; width: 100%;">
                <input type="text" name="search" placeholder="Buscar por nombre, usuario, extensión o tipo..." 
                       value="<?php echo htmlspecialchars($search_term); ?>" 
                       style="border: none; background: transparent; outline: none; width: 100%; font-size: 14px;">
                <input type="hidden" name="pagina" value="1">
                
                <?php if (!empty($search_term)): ?>
                    <a href="users.php" style="margin-left: 10px; color: #e74c3c;" title="Limpiar búsqueda">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="user-actions">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <div class="notification-badge">3</div>
            </div>
            <div class="user-info-top">
                <span class="user-name-top"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></span>
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
        <?php if (isset($mensaje_exito)): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
                <?php echo $mensaje_exito; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                <?php echo $mensaje_error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Barra de herramientas -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    Lista de Usuarios 
                    <span style="font-size: 14px; color: #7f8c8d; margin-left: 10px;">
                        (Total: <?php echo $total_registros; ?> registros)
                    </span>
                </div>
                <div class="card-actions">
                    <button class="btn btn-primary" id="newUserBtn">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </button>
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Extensión</th>
                            <th>Tipo</th>
                            <th>Grupo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Consulta para obtener usuarios con JOIN para el tipo
                            $query = "SELECT 
                                        u.id,
                                        u.nombre,
                                        u.usuario,
                                        u.ext,
                                        t.Grupo as tipo_nombre,
                                        u.grupo_id
                                      FROM users u
                                      LEFT JOIN t_user t ON u.tipo = t.id
                                      $where_clause
                                      ORDER BY u.nombre ASC
                                      LIMIT :limit OFFSET :offset";
                            
                            $stmt = $db->prepare($query);
                            
                            // Bind parameters de búsqueda
                            foreach ($params as $key => $value) {
                                $stmt->bindValue($key, $value);
                            }
                            
                            // Bind parameters de paginación
                            $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            
                            $stmt->execute();
                            
                            if ($stmt->rowCount() > 0) {
                                while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    // Convertir grupo_id a texto
                                    $grupo_texto = '';
                                    switch ($usuario['grupo_id']) {
                                        case '1': $grupo_texto = 'FTD'; break;
                                        case '2': $grupo_texto = 'Rete'; break;
                                        default: $grupo_texto = 'Desconocido';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($usuario['nombre'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($usuario['usuario'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($usuario['ext'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($usuario['tipo_nombre'] ?? 'Desconocido') . "</td>";
                                    echo "<td>" . htmlspecialchars($grupo_texto) . "</td>";
                                    
                                    echo "<td>
                                    <div class='action-buttons'>
                                        <a href='users.php?" . http_build_query(array_merge($_GET, ['editar' => $usuario['id']])) . "' class='btn-action btn-edit' title='Editar'>
                                            <i class='fas fa-edit'></i>
                                        </a>
                                        <form method='POST' action='users.php?" . http_build_query($_GET) . "' style='display: inline;' onsubmit='return confirm(\"¿Está seguro de que desea eliminar este usuario?\");'>
                                            <input type='hidden' name='usuario_id' value='" . htmlspecialchars($usuario['id'] ?? '') . "'>
                                            <input type='hidden' name='eliminar_usuario' value='1'>
                                            <button type='submit' class='btn-action btn-delete' title='Eliminar'>
                                                <i class='fas fa-trash'></i>
                                            </button>
                                        </form>
                                    </div>
                                    </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align: center; padding: 20px;'>";
                                if (!empty($search_term)) {
                                    echo "No se encontraron usuarios que coincidan con: '<strong>" . htmlspecialchars($search_term) . "</strong>'";
                                } else {
                                    echo "No hay usuarios registrados.";
                                }
                                echo "</td></tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='6' style='text-align: center; padding: 20px; color: #e74c3c;'>";
                            echo "Error al cargar usuarios: " . $e->getMessage();
                            echo "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                
                <!-- PAGINACIÓN -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #7f8c8d; font-size: 14px;">
                        Mostrando <?php echo min($registros_por_pagina, $total_registros - $offset); ?> de <?php echo $total_registros; ?> registros
                        <?php if (!empty($search_term)): ?>
                            <br><span style="color: #3498db;">Buscando: \"<?php echo htmlspecialchars($search_term); ?>\"</span>
                        <?php endif; ?>
                    </div>
                    <div class="pagination">
                        <?php if ($pagina_actual_num > 1): ?>
                            <a href="users.php?pagina=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="users.php?pagina=<?php echo $pagina_actual_num - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-btn">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar números de página
                        $inicio = max(1, $pagina_actual_num - 2);
                        $fin = min($total_paginas, $pagina_actual_num + 2);
                        
                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <a href="users.php?pagina=<?php echo $i; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
                               class="pagination-btn <?php echo $i == $pagina_actual_num ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual_num < $total_paginas): ?>
                            <a href="users.php?pagina=<?php echo $pagina_actual_num + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-btn">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="users.php?pagina=<?php echo $total_paginas; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para crear nuevo usuario -->
<div class="modal" id="modalNuevoUsuario">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nuevo Usuario</h2>
            <button class="close-btn" id="closeModal">&times;</button>
        </div>
        <form method="POST" action="users.php?<?php echo http_build_query($_GET); ?>" id="formNuevoUsuario">
            <div class="modal-body">
                <input type="hidden" name="guardar_usuario" value="1">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan Pérez" required>
                </div>
                
                <div class="form-group">
                    <label for="usuario">Usuario *</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ej: juan.perez" required>
                </div>
                
                <div class="form-group">
                    <label for="contrasena">Contraseña *</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="Mínimo 6 caracteres" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="extension">Extensión *</label>
                    <input type="text" id="extension" name="extension" placeholder="Ej: 1234" required>
                </div>
                
                <div class="form-group">
                    <label for="tipo">Tipo *</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php
                        // Obtener tipos de usuario de la tabla t_user
                        try {
                            $query_tipos = "SELECT id, Grupo FROM t_user ORDER BY Grupo";
                            $stmt_tipos = $db->query($query_tipos);
                            while ($tipo = $stmt_tipos->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($tipo['id']) . "'>" . htmlspecialchars($tipo['Grupo']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            echo "<option value=''>Error al cargar tipos</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="grupo">Grupo *</label>
                    <select id="grupo" name="grupo" required>
                        <option value="">Seleccione un grupo</option>
                        <option value="1">FTD</option>
                        <option value="2">Rete</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelar">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para editar usuario -->
<div class="modal" id="modalEditarUsuario" <?php if (isset($_GET['editar'])) echo 'style="display: flex;"'; ?>>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Usuario</h2>
            <button class="close-btn" id="closeModalEditar">&times;</button>
        </div>
        <form method="POST" action="users.php?<?php 
            $params = $_GET;
            unset($params['editar']);
            echo http_build_query($params); 
        ?>" id="formEditarUsuario">
            <div class="modal-body">
                <input type="hidden" name="editar_usuario" value="1">
                <input type="hidden" id="usuario_id_editar" name="usuario_id" value="<?php echo $usuario_editar['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="extension_editar">Extensión *</label>
                    <input type="text" id="extension_editar" name="extension" placeholder="Ej: 1234" required 
                           value="<?php echo htmlspecialchars($usuario_editar['ext'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="tipo_editar">Tipo *</label>
                    <select id="tipo_editar" name="tipo" required>
                        <option value="">Seleccione un tipo</option>
                        <?php
                        // Obtener tipos de usuario de la tabla t_user
                        try {
                            $query_tipos = "SELECT id, Grupo FROM t_user ORDER BY Grupo";
                            $stmt_tipos = $db->query($query_tipos);
                            while ($tipo = $stmt_tipos->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($usuario_editar['tipo'] ?? '') == $tipo['id'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($tipo['id']) . "' $selected>" . htmlspecialchars($tipo['Grupo']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            echo "<option value=''>Error al cargar tipos</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="grupo_editar">Grupo *</label>
                    <select id="grupo_editar" name="grupo" required>
                        <option value="">Seleccione un grupo</option>
                        <option value="1" <?php echo ($usuario_editar['grupo_id'] ?? '') == '1' ? 'selected' : ''; ?>>FTD</option>
                        <option value="2" <?php echo ($usuario_editar['grupo_id'] ?? '') == '2' ? 'selected' : ''; ?>>Rete</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="contrasena_editar">Nueva Contraseña</label>
                    <input type="password" id="contrasena_editar" name="contrasena" placeholder="Dejar vacío para no cambiar">
                    <small style="color: #7f8c8d; font-size: 12px;">Solo complete si desea cambiar la contraseña</small>
                </div>
            </div>
            <div class="modal-footer">
                <a href="users.php?<?php 
                    $params = $_GET;
                    unset($params['editar']);
                    echo http_build_query($params); 
                ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btnGuardarEditar">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.btn-action {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-edit {
    background-color: #f39c12;
    color: white;
}

.btn-delete {
    background-color: #e74c3c;
    color: white;
}

.btn-action:hover {
    opacity: 0.8;
    transform: translateY(-1px);
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th {
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border-bottom: 1px solid #ecf0f1;
    white-space: nowrap;
}

.users-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #ecf0f1;
    vertical-align: top;
    white-space: nowrap;
}

.users-table th:nth-child(1), .users-table td:nth-child(1) { width: 180px; }
.users-table th:nth-child(2), .users-table td:nth-child(2) { width: 140px; }
.users-table th:nth-child(3), .users-table td:nth-child(3) { width: 110px; }
.users-table th:nth-child(4), .users-table td:nth-child(4) { width: 130px; }
.users-table th:nth-child(5), .users-table td:nth-child(5) { width: 100px; }
.users-table th:nth-child(6), .users-table td:nth-child(6) { width: 100px; }

.table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: auto;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    width: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.modal-header {
    background-color: #3498db;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 500;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}

.modal-body {
    padding: 20px;
}

.modal-body .form-group {
    margin-bottom: 15px;
}

.modal-body label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
}

.modal-body input, .modal-body select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dce1e6;
    border-radius: 4px;
    font-size: 14px;
}

.modal-body input:focus, .modal-body select:focus {
    border-color: #3498db;
    outline: none;
}

.modal-footer {
    padding: 15px 20px;
    background-color: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e1e4e8;
}

.pagination {
    display: flex;
    gap: 5px;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #3498db;
    background: white;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.pagination-btn:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

.pagination-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-secondary {
    background-color: #95a5a6;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}
</style>

<script>
// Funcionalidad del Modal Nuevo Usuario
const newUserBtn = document.getElementById('newUserBtn');
const modalNuevoUsuario = document.getElementById('modalNuevoUsuario');
const closeModal = document.getElementById('closeModal');
const btnCancelar = document.getElementById('btnCancelar');

// Abrir modal nuevo usuario
newUserBtn.addEventListener('click', () => {
    modalNuevoUsuario.style.display = 'flex';
});

// Cerrar modal nuevo usuario
function cerrarModalNuevo() {
    modalNuevoUsuario.style.display = 'none';
}

closeModal.addEventListener('click', cerrarModalNuevo);
btnCancelar.addEventListener('click', cerrarModalNuevo);

// Funcionalidad del Modal Editar Usuario
const modalEditarUsuario = document.getElementById('modalEditarUsuario');
const closeModalEditar = document.getElementById('closeModalEditar');

// Cerrar modal editar usuario
function cerrarModalEditar() {
    modalEditarUsuario.style.display = 'none';
}

closeModalEditar.addEventListener('click', cerrarModalEditar);

// Cerrar modales al hacer clic fuera
window.addEventListener('click', (e) => {
    if (e.target === modalNuevoUsuario) {
        cerrarModalNuevo();
    }
    if (e.target === modalEditarUsuario) {
        cerrarModalEditar();
    }
});

// Auto-submit del formulario de búsqueda
document.querySelector('input[name="search"]')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});
</script>