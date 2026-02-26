<?php
// Incluir verificación de sesión
include '../includes/session.php';
requireLogin();

$pagina_actual = 'dashboard';

// Obtener usuario actual con todos los campos
$usuario_actual = getCurrentUser();


// Incluir el header
include '../includes/header.php';

// Incluir el sidebar
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Dashboard</div>
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
        <!-- Tarjetas de estadísticas - SOLO TOTAL CLIENTES ASIGNADOS -->
        <div class="stats-container">
            <?php
            // Consulta para total de clientes ASIGNADOS AL USUARIO
            try {
                // Usar getCurrentUserId() en lugar de $usuario_actual['id']
                $user = getCurrentUserId();
                $username = getCurrentUsername();
                
                echo "<!-- Debug: User ID: $user, Username: $username -->";
                
                $query = "SELECT COUNT(*) as total_clientes FROM clientes WHERE asignado IN (select nombre from users where grupo = :usuario_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':usuario_id', $user);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_clientes_asignados = $result['total_clientes'];
                
            } catch (PDOException $e) {
                // Si hay error o no existe la relación, mostrar 0
                $total_clientes_asignados = 0;
                echo "<!-- Error: " . $e->getMessage() . " -->";
            }
            ?>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_clientes_asignados; ?></div>
                <div class="stat-label">Total Clientes</div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <!-- Sección de clientes recientes ASIGNADOS AL USUARIO -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">Mis Clientes Recientes</div>
                <div class="card-actions">
                    <button class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </button>
                </div>
            </div>
            
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Consulta para clientes asignados al usuario actual
                        $user = getCurrentUserId();
                        
                        $query = "SELECT 
                                    nombre,
                                    apellido,  
                                    correo, 
                                    numero, 
                                    estado
                                  FROM clientes 
                                  WHERE asignado IN (select nombre from users where grupo = :usuario_id)
                                  ORDER BY id DESC 
                                  LIMIT 5";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':usuario_id', $user);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // Ocultar teléfono con asteriscos
                                $telefono = $row['numero'] ?? '';
                                if (!empty($telefono)) {
                                    // Reemplazar todos los dígitos con asteriscos
                                    $telefono_oculto = preg_replace('/[0-9]/', '*', $telefono);
                                } else {
                                    $telefono_oculto = 'No disponible';
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['nombre'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row['apellido'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row['correo'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($telefono_oculto) . "</td>";
                                echo "<td><span class='status-badge status-" . htmlspecialchars($row['estado'] ?? 'nuevo') . "'>" . ucfirst($row['estado'] ?? 'Nuevo') . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; padding: 20px;'>No tienes clientes asignados.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5' style='text-align: center; padding: 20px; color: #e74c3c;'>";
                        echo "Error al cargar clientes: " . $e->getMessage();
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Sección de próximas citas - CORREGIDA -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">Mis Próximas Citas de Hoy</div>
                <div class="card-actions">
                    <a href="nueva_cita.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Cita
                    </a>
                </div>
            </div>
            
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Descripción</th>
                        <th>Hora</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Consulta para citas del día actual del usuario
                        $username = getCurrentUsername();
                        
                        $query = "SELECT 
                                    c.id,
                                    c.titulo,
                                    c.descripcion,
                                    c.hora,
                                    u.nombre as usuario_nombre
                                  FROM citas c
                                  LEFT JOIN users u ON c.usuario_id = u.usuario
                                  WHERE c.fecha = CURDATE() AND c.usuario_id = :usuario_login
                                  ORDER BY c.hora ASC LIMIT 5";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':usuario_login', $username);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['titulo']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
                                
                                // Formatear hora para mejor visualización
                                $hora = substr($row['hora'], 0, 5);
                                echo "<td>" . htmlspecialchars($hora) . "</td>";
                                
                                echo "<td>" . htmlspecialchars($row['usuario_nombre']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align: center; padding: 20px;'>No tienes citas programadas para hoy.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='4' style='text-align: center; padding: 20px; color: #e74c3c;'>";
                        echo "Error al cargar citas: " . $e->getMessage();
                        echo "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Navegación entre secciones
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function(e) {
        // Prevenir comportamiento por defecto si es un enlace
        e.preventDefault();
        
        // Remover clase active de todos los items
        document.querySelectorAll('.menu-item').forEach(i => {
            i.classList.remove('active');
        });
        
        // Agregar clase active al item clickeado
        this.classList.add('active');
        
        // Cambiar el título de la página
        const sectionName = this.getAttribute('data-section');
        const pageTitle = document.querySelector('.page-title');
        
        // Redirigir a la página correspondiente
        switch(sectionName) {
            case 'dashboard':
                window.location.href = 'index.php';
                break;
            case 'leads':
                window.location.href = 'leads.php';
                break;
            case 'calendar':
                window.location.href = 'calendar.php';
                break;
            case 'users':
                window.location.href = 'users.php';
                break;
            case 'reports':
                window.location.href = 'reports.php';
                break;
        }
    });
});
</script>

<script>
// Toggle del menú de usuario en el top bar
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.classList.toggle('show');
}

// Cerrar menú al hacer click fuera
document.addEventListener('click', function(e) {
    const userMenu = document.getElementById('userMenu');
    const userInfoTop = document.querySelector('.user-info-top');
    
    if (!userInfoTop.contains(e.target)) {
        userMenu.classList.remove('show');
    }
});

// Cerrar menú al presionar ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('userMenu').classList.remove('show');
    }
});
</script>

<script src="../js/script.js"></script>
</body>
</html>