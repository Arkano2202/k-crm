<?php
// asignar_usuarios.php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'asignar_usuarios';

// Obtener usuario actual
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

// Obtener agentes (tipo 2 o 3) y TLs (tipo 4 o 5)
try {
    // Obtener agentes (tipo 2 o 3)
    $query_agentes = "SELECT id, Nombre, Usuario, Ext, Tipo, grupo 
                     FROM users 
                     WHERE Tipo IN (2, 3) 
                     ORDER BY Nombre";
    $stmt_agentes = $db->prepare($query_agentes);
    $stmt_agentes->execute();
    $agentes = $stmt_agentes->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener Team Leaders (tipo 4 o 5)
    $query_tls = "SELECT id, Nombre, Usuario, Ext 
                 FROM users 
                 WHERE Tipo IN (4, 5) 
                 ORDER BY Nombre";
    $stmt_tls = $db->prepare($query_tls);
    $stmt_tls->execute();
    $tls = $stmt_tls->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $agentes = [];
    $tls = [];
    $error = "Error al cargar usuarios: " . $e->getMessage();
}

// Procesar asignación de TL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'asignar') {
            // Asignar TL a agentes seleccionados
            if (isset($_POST['agentes_seleccionados']) && !empty($_POST['agentes_seleccionados']) && isset($_POST['tl_id'])) {
                $agentes_ids = $_POST['agentes_seleccionados'];
                $tl_id = (int)$_POST['tl_id'];
                
                // Verificar que el TL existe
                $check_tl = $db->prepare("SELECT id FROM users WHERE id = ? AND Tipo IN (4,5)");
                $check_tl->execute([$tl_id]);
                
                if ($check_tl->rowCount() > 0) {
                    // Actualizar grupo de los agentes seleccionados
                    $placeholders = str_repeat('?,', count($agentes_ids) - 1) . '?';
                    $query = "UPDATE users SET grupo = ? WHERE id IN ($placeholders) AND Tipo IN (2,3)";
                    
                    $stmt = $db->prepare($query);
                    $params = array_merge([$tl_id], $agentes_ids);
                    $stmt->execute($params);
                    
                    $agentes_actualizados = $stmt->rowCount();
                    $mensaje = "✅ $agentes_actualizados agente(s) asignado(s) correctamente al Team Leader";
                } else {
                    $error = "❌ El Team Leader seleccionado no existe o no es válido";
                }
            } else {
                $error = "❌ Debe seleccionar al menos un agente y un Team Leader";
            }
        } 
        elseif ($_POST['accion'] === 'quitar') {
            // Quitar asignación de agentes seleccionados
            if (isset($_POST['agentes_seleccionados']) && !empty($_POST['agentes_seleccionados'])) {
                $agentes_ids = $_POST['agentes_seleccionados'];
                
                $placeholders = str_repeat('?,', count($agentes_ids) - 1) . '?';
                $query = "UPDATE users SET grupo = 0 WHERE id IN ($placeholders) AND Tipo IN (2,3)";
                
                $stmt = $db->prepare($query);
                $stmt->execute($agentes_ids);
                
                $agentes_actualizados = $stmt->rowCount();
                $mensaje = "✅ $agentes_actualizados agente(s) liberado(s) correctamente (sin Team Leader asignado)";
            } else {
                $error = "❌ Debe seleccionar al menos un agente para quitar la asignación";
            }
        }
        
        // Recargar datos después de la actualización
        $stmt_agentes->execute();
        $agentes = $stmt_agentes->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = "❌ Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Obtener información de TLs para mostrar en la tabla
$tl_info = [];
foreach ($tls as $tl) {
    $tl_info[$tl['id']] = $tl['Nombre'];
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Asignar Usuarios</div>
        <div class="user-actions">
            <div class="user-info-top">
                <span class="user-name-top"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></span>
                <span class="user-ext-top">Ext: <?php echo htmlspecialchars($usuario_actual['ext']); ?></span>
                <div class="user-avatar-top" style="background-color: #3498db;">
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
        <!-- Card Principal -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    Asignar Team Leaders a Agentes
                    <span style="font-size: 14px; color: #7f8c8d; margin-left: 10px;">
                        (<?php echo count($agentes); ?> agentes, <?php echo count($tls); ?> Team Leaders)
                    </span>
                </div>
                <div class="card-actions">
                    <button class="btn btn-secondary" onclick="recargarPagina()">
                        <i class="fas fa-sync-alt"></i> Recargar
                    </button>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" style="margin: 15px 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="margin: 15px 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Panel de Asignación -->
            <div style="padding: 20px; border-bottom: 1px solid #ecf0f1; background-color: #f8f9fa;">
                <form id="formAsignacion" method="POST" action="asignar_usuarios.php">
                    <input type="hidden" name="accion" id="accion" value="asignar">
                    
                    <div class="asignacion-panel">
                        <!-- Selección de Team Leader -->
                        <div class="form-group">
                            <label for="tl_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                <i class="fas fa-user-tie"></i> Team Leader:
                            </label>
                            <select id="tl_id" name="tl_id" class="form-control" style="width: 100%; max-width: 300px;">
                                <option value="">Seleccionar Team Leader...</option>
                                <?php foreach ($tls as $tl): ?>
                                    <option value="<?php echo $tl['id']; ?>">
                                        <?php echo htmlspecialchars($tl['Nombre'] . ' (' . $tl['Usuario'] . ') - Ext: ' . $tl['Ext']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Acciones -->
                        <div class="acciones-botones" style="display: flex; gap: 10px; align-items: end;">
                            <button type="button" class="btn btn-primary" id="btnAsignar">
                                <i class="fas fa-user-plus"></i> Asignar Team Leader
                            </button>
                            <button type="button" class="btn btn-warning" id="btnQuitarAsignacion">
                                <i class="fas fa-user-minus"></i> Quitar Asignación
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Contador de Seleccionados -->
            <div id="contadorSeleccionados" class="alert alert-warning" style="margin: 15px 20px; display: none;">
                <i class="fas fa-exclamation-triangle"></i> <span id="textoSeleccionados">0 agentes seleccionados</span>
            </div>

            <!-- Tabla de Agentes -->
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Ext</th>
                            <th>Tipo</th>
                            <th>Team Leader Asignado</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyAgentes">
                        <?php if (!empty($agentes)): ?>
                            <?php foreach ($agentes as $agente): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               class="agente-checkbox" 
                                               value="<?php echo $agente['id']; ?>"
                                               data-tl-id="<?php echo $agente['grupo']; ?>">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($agente['Nombre']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($agente['Usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($agente['Ext']); ?></td>
                                    <td>
                                        <?php 
                                        $tipo_texto = '';
                                        $badge_class = '';
                                        switch ($agente['Tipo']) {
                                            case 2:
                                                $tipo_texto = 'Agente FTD';
                                                $badge_class = 'badge-info';
                                                break;
                                            case 3:
                                                $tipo_texto = 'Agente RETE';
                                                $badge_class = 'badge-primary';
                                                break;
                                            default:
                                                $tipo_texto = 'Desconocido';
                                                $badge_class = 'badge-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo $tipo_texto; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($agente['grupo'] > 0 && isset($tl_info[$agente['grupo']])): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-user-tie"></i>
                                                <?php echo htmlspecialchars($tl_info[$agente['grupo']]); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times-circle"></i>
                                                Sin asignar
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    No hay agentes registrados en el sistema.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px;"></div>

<style>
/* Estilos para la tabla */
.leads-table {
    width: 100%;
    border-collapse: collapse;
}

.leads-table th {
    background-color: #f8f9fa;
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border-bottom: 1px solid #ecf0f1;
}

.leads-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #ecf0f1;
    vertical-align: middle;
}

/* Anchos específicos para cada columna */
.leads-table th:nth-child(1), .leads-table td:nth-child(1) { width: 50px; }
.leads-table th:nth-child(2), .leads-table td:nth-child(2) { width: 200px; }
.leads-table th:nth-child(3), .leads-table td:nth-child(3) { width: 150px; }
.leads-table th:nth-child(4), .leads-table td:nth-child(4) { width: 80px; }
.leads-table th:nth-child(5), .leads-table td:nth-child(5) { width: 120px; }
.leads-table th:nth-child(6), .leads-table td:nth-child(6) { width: 200px; }

.table-container {
    max-height: 600px;
    overflow-y: auto;
}

/* Badges */
.badge {
    padding: 6px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge-secondary {
    background-color: #e2e3e5;
    color: #383d41;
    border: 1px solid #d6d8db;
}

.badge-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.badge-primary {
    background-color: #cce7ff;
    color: #004085;
    border: 1px solid #b3d7ff;
}

.badge-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Checkboxes */
.agente-checkbox {
    transform: scale(1.2);
    accent-color: #3498db;
}

#selectAll {
    transform: scale(1.2);
    accent-color: #3498db;
}

/* Botones */
.btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
}

.btn i {
    margin-right: 6px;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: #2980b9;
    transform: translateY(-1px);
}

.btn-warning {
    background-color: #f39c12;
    color: white;
}

.btn-warning:hover:not(:disabled) {
    background-color: #e67e22;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background-color: #7f8c8d;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Form controls */
.form-control {
    padding: 10px 12px;
    border: 2px solid #e1e8ed;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    background-color: white;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* Alertas */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 4px solid;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border-left-color: #ffc107;
}

/* Panel de asignación */
.asignacion-panel {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 20px;
    align-items: end;
}

.acciones-botones {
    display: flex;
    gap: 10px;
}

.info-item {
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
    border-left: 3px solid #3498db;
}

/* Responsive */
@media (max-width: 768px) {
    .asignacion-panel {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .acciones-botones {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// =============================================
// ASIGNAR USUARIOS - FUNCIONES PRINCIPALES
// =============================================

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM cargado - Inicializando event listeners');
    inicializarEventListeners();
});

// Inicializar event listeners
function inicializarEventListeners() {
    console.log('🔧 Configurando event listeners...');
    
    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        console.log('🔘 Select All cambiado:', this.checked);
        const checkboxes = document.querySelectorAll('.agente-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        actualizarEstadoPanel();
    });

    // Checkboxes individuales
    document.querySelectorAll('.agente-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('🔘 Checkbox agente cambiado:', this.value, this.checked);
            actualizarEstadoPanel();
        });
    });

    // Botón de asignar
    document.getElementById('btnAsignar').addEventListener('click', function() {
        asignarTeamLeader();
    });

    // Botón de quitar asignación
    document.getElementById('btnQuitarAsignacion').addEventListener('click', function() {
        quitarAsignacion();
    });
    
    console.log('✅ Event listeners configurados correctamente');
}

// Actualizar estado del panel y contador
function actualizarEstadoPanel() {
    const agentesSeleccionados = document.querySelectorAll('.agente-checkbox:checked');
    const contador = document.getElementById('contadorSeleccionados');
    const textoSeleccionados = document.getElementById('textoSeleccionados');
    
    console.log('🔄 Actualizando estado - Seleccionados:', agentesSeleccionados.length);
    
    // Actualizar contador
    if (agentesSeleccionados.length > 0) {
        contador.style.display = 'block';
        textoSeleccionados.textContent = `${agentesSeleccionados.length} agente(s) seleccionado(s)`;
        
        // Verificar si hay agentes con TL asignado
        const agentesConTL = Array.from(agentesSeleccionados).filter(checkbox => {
            return parseInt(checkbox.getAttribute('data-tl-id')) > 0;
        });
        
        if (agentesConTL.length > 0) {
            textoSeleccionados.innerHTML += ` <small style="color: #e74c3c;">(${agentesConTL.length} con TL asignado)</small>`;
        }
    } else {
        contador.style.display = 'none';
    }
    
    // Actualizar select all si todos están seleccionados
    const totalCheckboxes = document.querySelectorAll('.agente-checkbox').length;
    const checkboxesChecked = document.querySelectorAll('.agente-checkbox:checked').length;
    document.getElementById('selectAll').checked = totalCheckboxes > 0 && checkboxesChecked === totalCheckboxes;
}

// Función para asignar Team Leader
function asignarTeamLeader() {
    const agentesSeleccionados = Array.from(document.querySelectorAll('.agente-checkbox:checked'))
        .map(cb => cb.value);

    console.log('🔍 Agentes para asignar:', agentesSeleccionados);
    console.log('🔍 Cantidad de agentes:', agentesSeleccionados.length);

    // Validaciones
    if (agentesSeleccionados.length === 0) {
        console.warn('⚠️ Validación fallida: No hay agentes seleccionados');
        mostrarAlerta('❌ Debe seleccionar al menos un agente', 'error');
        return;
    }

    const tlId = document.getElementById('tl_id').value;
    if (!tlId) {
        console.warn('⚠️ Validación fallida: No hay TL seleccionado');
        mostrarAlerta('❌ Debe seleccionar un Team Leader', 'error');
        return;
    }

    // Mostrar confirmación
    const tlNombre = document.getElementById('tl_id').options[document.getElementById('tl_id').selectedIndex].text;
    const confirmacion = confirm(`⚠️ ¿ESTÁ SEGURO DE ASIGNAR ${agentesSeleccionados.length} AGENTE(S) AL TEAM LEADER?\n\nTeam Leader: ${tlNombre}\n\nEsta acción asignará los agentes seleccionados al Team Leader.`);
    
    if (!confirmacion) {
        console.log('❌ Usuario canceló la asignación');
        return;
    }

    console.log('✅ Usuario confirmó la asignación');

    // Preparar y enviar formulario
    const form = document.getElementById('formAsignacion');
    
    // Establecer acción
    document.getElementById('accion').value = 'asignar';
    
    // Limpiar inputs anteriores de agentes
    document.querySelectorAll('input[name="agentes_seleccionados[]"]').forEach(input => input.remove());
    
    // Agregar nuevos inputs para los agentes seleccionados
    agentesSeleccionados.forEach(agenteId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'agentes_seleccionados[]';
        input.value = agenteId;
        form.appendChild(input);
    });
    
    console.log('📤 Enviando formulario con:', {
        accion: 'asignar',
        tl_id: tlId,
        agentes: agentesSeleccionados
    });
    
    // Enviar formulario
    form.submit();
}

// Función para quitar asignación
function quitarAsignacion() {
    const agentesSeleccionados = Array.from(document.querySelectorAll('.agente-checkbox:checked'))
        .map(cb => cb.value);

    console.log('🔍 Agentes para quitar asignación:', agentesSeleccionados);
    console.log('🔍 Cantidad de agentes:', agentesSeleccionados.length);

    // Validaciones
    if (agentesSeleccionados.length === 0) {
        console.warn('⚠️ Validación fallida: No hay agentes seleccionados');
        mostrarAlerta('❌ Debe seleccionar al menos un agente para quitar la asignación', 'error');
        return;
    }

    // Verificar que al menos uno tenga TL asignado
    const agentesConTL = Array.from(document.querySelectorAll('.agente-checkbox:checked'))
        .filter(cb => parseInt(cb.getAttribute('data-tl-id')) > 0);
    
    if (agentesConTL.length === 0) {
        mostrarAlerta('❌ Los agentes seleccionados no tienen Team Leader asignado', 'warning');
        return;
    }

    // Mostrar confirmación
    const confirmacion = confirm(`⚠️ ¿ESTÁ SEGURO DE QUITAR LA ASIGNACIÓN DE ${agentesSeleccionados.length} AGENTE(S)?\n\nEsto liberará a los agentes seleccionados de su Team Leader actual.`);
    
    if (!confirmacion) {
        console.log('❌ Usuario canceló la operación');
        return;
    }

    console.log('✅ Usuario confirmó quitar asignación');

    // Preparar y enviar formulario
    const form = document.getElementById('formAsignacion');
    
    // Cambiar acción
    document.getElementById('accion').value = 'quitar';
    
    // Limpiar TL selection
    document.getElementById('tl_id').value = '';
    
    // Limpiar inputs anteriores de agentes
    document.querySelectorAll('input[name="agentes_seleccionados[]"]').forEach(input => input.remove());
    
    // Agregar nuevos inputs para los agentes seleccionados
    agentesSeleccionados.forEach(agenteId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'agentes_seleccionados[]';
        input.value = agenteId;
        form.appendChild(input);
    });
    
    console.log('📤 Enviando formulario con:', {
        accion: 'quitar',
        agentes: agentesSeleccionados
    });
    
    // Enviar formulario
    form.submit();
}

// Función para recargar página
function recargarPagina() {
    console.log('🔄 Recargando página...');
    location.reload();
}

// =============================================
// FUNCIÓN DE ALERTA
// =============================================
function mostrarAlerta(mensaje, tipo = 'info') {
    console.log(`🔔 Mostrando alerta [${tipo}]:`, mensaje);
    
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const alertHTML = `
        <div id="${alertId}" class="alert alert-${tipo === 'error' ? 'danger' : tipo}" style="margin-bottom: 10px; padding: 12px 15px; border-radius: 4px; background-color: ${tipo === 'error' ? '#f8d7da' : tipo === 'success' ? '#d4edda' : '#fff3cd'}; color: ${tipo === 'error' ? '#721c24' : tipo === 'success' ? '#155724' : '#856404'}; border: 1px solid ${tipo === 'error' ? '#f5c6cb' : tipo === 'success' ? '#c3e6cb' : '#ffeaa7'};">
            <i class="fas ${iconos[tipo] || 'fa-info-circle'}"></i> ${mensaje}
            <button type="button" onclick="document.getElementById('${alertId}').remove()" style="background: none; border: none; float: right; cursor: pointer; color: inherit;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHTML);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        const alertElement = document.getElementById(alertId);
        if (alertElement) {
            console.log(`🔔 Auto-eliminando alerta: ${alertId}`);
            alertElement.remove();
        }
    }, 5000);
}
</script>