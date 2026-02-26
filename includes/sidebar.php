<div class="app-container">
    <!-- Panel lateral -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <span>CRM Pro</span>
            </div>
        </div>
        
        <div class="menu">
            <!-- Dashboard - Visible para todos -->
            <a href="index.php" class="menu-link <?php echo ($pagina_actual == 'dashboard') ? 'active' : ''; ?>">
                <div class="menu-item" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </div>
            </a>
            
            <!-- Leads - Visible para todos -->
            <a href="leads.php" class="menu-link <?php echo ($pagina_actual == 'leads') ? 'active' : ''; ?>">
                <div class="menu-item" data-section="leads">
                    <i class="fas fa-users"></i>
                    <span>Leads</span>
                </div>
            </a>
            
            <!-- Calendario - Visible para todos -->
            <a href="calendar.php" class="menu-link <?php echo ($pagina_actual == 'calendar') ? 'active' : ''; ?>">
                <div class="menu-item" data-section="calendar">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendario</span>
                </div>
            </a>
            
            <?php 
            // Obtener información del usuario usando la función de session.php
            $usuario = getCurrentUser();
            if ($usuario && $usuario['tipo'] == 1): ?>
                <!-- Solo visible para Administradores (tipo 1) -->
                
                <!-- Subir Leads con submenú -->
                <?php
                // Determinar si el submenú debe estar activo
                $subir_leads_active = ($pagina_actual == 'subir_leads' || $pagina_actual == 'actualizar_leads' || $pagina_actual == 'asignar_individual');
                ?>
                <div class="menu-item-with-submenu <?php echo $subir_leads_active ? 'active' : ''; ?>">
                    <a href="subir_leads.php" class="menu-link">
                        <div class="menu-item" data-section="subir_leads">
                            <i class="fas fa-upload"></i>
                            <span>Subir Leads</span>
                            <i class="fas fa-chevron-down submenu-toggle"></i>
                        </div>
                    </a>
                    <div class="submenu">
                        <a href="subir_leads.php" class="submenu-link <?php echo ($pagina_actual == 'subir_leads') ? 'active' : ''; ?>">
                            <i class="fas fa-upload"></i>
                            <span>Cargar</span>
                        </a>
                        <a href="actualizar_leads.php" class="submenu-link <?php echo ($pagina_actual == 'actualizar_leads') ? 'active' : ''; ?>">
                            <i class="fas fa-sync"></i>
                            <span>Actualizar</span>
                        </a>
                         <a href="asignar_individual.php" class="submenu-link <?php echo ($pagina_actual == 'asignar_individual') ? 'active' : ''; ?>">
                            <i class="fas fa-user-check"></i>
                            <span>Asignar Individual</span>
                        </a>
                    </div>
                </div>
                
                <!-- Eliminar Leads - Solo Admin -->
                <a href="eliminar.php" class="menu-link <?php echo ($pagina_actual == 'eliminar') ? 'active' : ''; ?>">
                    <div class="menu-item" data-section="eliminar">
                        <i class="fas fa-trash-alt"></i>
                        <span>Eliminar Leads</span>
                    </div>
                </a>
                
                <!-- Gestión de Usuarios - Solo Admin -->
                <a href="users.php" class="menu-link <?php echo ($pagina_actual == 'users') ? 'active' : ''; ?>">
                    <div class="menu-item" data-section="users">
                        <i class="fas fa-user-cog"></i>
                        <span>Usuarios</span>
                    </div>
                </a>
                
                <!-- Asignar Usuarios - Solo Admin -->
                <a href="asignar_usuarios.php" class="menu-link <?php echo ($pagina_actual == 'asignar_usuarios') ? 'active' : ''; ?>">
                    <div class="menu-item" data-section="asignar_usuarios">
                        <i class="fas fa-user-cog"></i>
                        <span>Asignar Usuarios</span>
                    </div>
                </a>
                
                <!-- Histórico - Solo Admin -->
                <a href="historico.php" class="menu-link <?php echo ($pagina_actual == 'history') ? 'active' : ''; ?>">
                    <div class="menu-item" data-section="history">
                        <i class="fas fa-clock"></i>
                        <span>Historico</span>
                    </div>
                </a>

                <!-- Exportar Leads - Solo Admin -->
                <a href="exportar_leads.php" class="menu-link <?php echo ($pagina_actual == 'exportar_leads') ? 'active' : ''; ?>">
                    <div class="menu-item" data-section="exportar_leads">
                        <i class="fas fa-file-export"></i>
                        <span>Exportar Leads</span>
                    </div>
                </a>
                
            <?php endif; ?>
        </div>
        
        <div class="user-section">
            <?php
            // Obtener información del usuario logueado
            $usuario = getCurrentUser();
            if ($usuario) {
                // Generar iniciales del nombre completo
                $nombres = explode(' ', $usuario['nombre']);
                $iniciales = '';
                foreach ($nombres as $nombre) {
                    if (!empty($nombre)) {
                        $iniciales .= substr($nombre, 0, 1);
                    }
                }
                $iniciales = substr($iniciales, 0, 2); // Tomar máximo 2 iniciales
                
                $nombre_completo = htmlspecialchars($usuario['nombre']);
                
                // Determinar el rol basado en el tipo
                $roles = [
                    1 => 'Administrador',
                    2 => 'Usuario FTD',
                    3 => 'Usuario FTD',
                    4 => 'Team Leader',
                    5 => 'Team Leader',
                    6 => 'Auditor'
                ];
                $rol = isset($roles[$usuario['tipo']]) ? $roles[$usuario['tipo']] : 'Usuario';
            } else {
                // Valores por defecto en caso de error
                $iniciales = "US";
                $nombre_completo = "Usuario Sistema";
                $rol = "Usuario";
            }
            ?>
            
            <div class="user-avatar" style="background-color: #3498db;">
                <?php echo strtoupper($iniciales); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $nombre_completo; ?></div>
                <div class="user-role"><?php echo $rol; ?> - Ext: <?php echo htmlspecialchars($usuario['ext']); ?></div>
            </div>
            <a href="logout.php" class="logout-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

<!-- El resto del CSS y JavaScript permanece igual -->
<style>
.menu-item-with-submenu {
    position: relative;
}

.menu-item-with-submenu .menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.submenu-toggle {
    font-size: 12px;
    transition: transform 0.3s;
    margin-left: auto;
}

.menu-item-with-submenu.active .submenu-toggle {
    transform: rotate(180deg);
}

.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    border-left: 3px solid #007bff;
    margin: 5px 0 5px 15px;
    border-radius: 0 8px 8px 0;
}

.menu-item-with-submenu.active .submenu {
    max-height: 300px;
}

.submenu-link {
    display: flex;
    align-items: center;
    padding: 12px 20px 12px 45px;
    color: #e9ecef;
    text-decoration: none;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    position: relative;
    overflow: hidden;
}

.submenu-link:last-child {
    border-bottom: none;
}

.submenu-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, rgba(0,123,255,0.3) 0%, rgba(0,123,255,0.1) 100%);
    transition: width 0.3s ease;
    z-index: 1;
}

.submenu-link:hover::before {
    width: 100%;
}

.submenu-link:hover {
    color: white;
    transform: translateX(5px);
}

.submenu-link.active {
    background: linear-gradient(90deg, rgba(0,123,255,0.4) 0%, rgba(0,123,255,0.2) 100%);
    color: white;
    border-left: 3px solid #007bff;
}

.submenu-link.active::before {
    width: 100%;
}

.submenu-link i {
    margin-right: 12px;
    font-size: 14px;
    width: 16px;
    text-align: center;
    position: relative;
    z-index: 2;
}

.submenu-link span {
    position: relative;
    z-index: 2;
    font-weight: 500;
}

.submenu-link::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s, height 0.3s;
}

.submenu-link:hover::after {
    width: 100%;
    height: 100%;
}

.submenu-link {
    opacity: 0;
    transform: translateX(-10px);
    animation: slideIn 0.3s ease forwards;
}

.submenu-link:nth-child(1) { animation-delay: 0.1s; }
.submenu-link:nth-child(2) { animation-delay: 0.2s; }
.submenu-link:nth-child(3) { animation-delay: 0.3s; }

@keyframes slideIn {
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.menu-item-with-submenu > .menu-link {
    transition: all 0.3s ease;
}

.menu-item-with-submenu > .menu-link:hover {
    background: rgba(0,123,255,0.1);
}

.menu-item-with-submenu.active > .menu-link {
    background: rgba(0,123,255,0.15);
    border-left: 3px solid #007bff;
}

/* Mejoras para la sección de usuario */
.user-section {
    display: flex;
    align-items: center;
    padding: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
    transition: all 0.3s ease;
}

.user-section:hover {
    background: rgba(0, 0, 0, 0.3);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    margin-right: 12px;
    flex-shrink: 0;
}

.user-info {
    flex-grow: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: white;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    color: rgba(255, 255, 255, 0.7);
    padding: 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logout-btn:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar submenús con mejor animación
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parent = this.closest('.menu-item-with-submenu');
            const wasActive = parent.classList.contains('active');
            
            // Cerrar todos los submenús primero
            document.querySelectorAll('.menu-item-with-submenu').forEach(item => {
                if (item !== parent) {
                    item.classList.remove('active');
                }
            });
            
            // Abrir/cerrar el actual
            parent.classList.toggle('active', !wasActive);
        });
    });
    
    // Cerrar submenús al hacer click fuera
    document.addEventListener('click', function() {
        document.querySelectorAll('.menu-item-with-submenu').forEach(item => {
            // No cerrar si hay un enlace activo en el submenú
            const hasActiveLink = item.querySelector('.submenu-link.active');
            if (!hasActiveLink) {
                item.classList.remove('active');
            }
        });
    });
    
    // Prevenir que el click en el submenú lo cierre
    document.querySelectorAll('.submenu').forEach(submenu => {
        submenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Mantener abierto el submenú si hay un enlace activo
    const activeSubmenuLink = document.querySelector('.submenu-link.active');
    if (activeSubmenuLink) {
        const parentMenuItem = activeSubmenuLink.closest('.menu-item-with-submenu');
        if (parentMenuItem) {
            parentMenuItem.classList.add('active');
        }
    }
});
</script>