<?php
// exportar_leads.php
$pagina_actual = 'exportar_leads';

// Incluir archivos necesarios
include '../includes/session.php';
requireLogin();

// Incluir la clase Database si existe
$databasePath = '../config/database.php';
if (file_exists($databasePath)) {
    include $databasePath;
} else {
    // Si no existe Database.php, intentar incluir conexión directa
    include '../config/db.php'; // o el archivo que uses para conexión
}

// Verificar que sea administrador
$usuario_actual = getCurrentUser();
if ($usuario_actual['tipo'] != 1) {
    header('Location: index.php');
    exit();
}

// Mostrar mensajes de error si existen
$mensaje = '';
$errores = [];

if (isset($_SESSION['export_error'])) {
    $errores[] = $_SESSION['export_error'];
    unset($_SESSION['export_error']);
}

// Obtener consultas guardadas del usuario actual
function obtenerConsultasGuardadas($usuario_id) {
    global $conn; // Usar la conexión global si existe
    
    // Intentar diferentes formas de conexión
    try {
        // Método 1: Usar PDO directamente si $conn es PDO
        if (isset($conn) && $conn instanceof PDO) {
            $query = "SELECT id, nombre, filtros, fecha_creacion 
                      FROM consultas_guardadas 
                      WHERE usuario_id = :usuario_id 
                      AND (tipo = 'exportacion' OR tipo IS NULL)  -- CAMBIO: filtrar por tipo
                      ORDER BY fecha_creacion DESC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Método 2: Usar mysqli si $conn es mysqli
        elseif (isset($conn) && $conn instanceof mysqli) {
            $usuario_id = $conn->real_escape_string($usuario_id);
            $query = "SELECT id, nombre, filtros, fecha_creacion 
                      FROM consultas_guardadas 
                      WHERE usuario_id = '$usuario_id' 
                      AND (tipo = 'exportacion' OR tipo IS NULL)  -- CAMBIO: filtrar por tipo
                      ORDER BY fecha_creacion DESC";
            $result = $conn->query($query);
            
            $consultas = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $consultas[] = $row;
                }
            }
            return $consultas;
        }
        // Método 3: Intentar con la clase Database
        else {
            if (class_exists('Database')) {
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT id, nombre, filtros, fecha_creacion 
                          FROM consultas_guardadas 
                          WHERE usuario_id = :usuario_id 
                          AND (tipo = 'exportacion' OR tipo IS NULL)  -- CAMBIO: filtrar por tipo
                          ORDER BY fecha_creacion DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo consultas guardadas: " . $e->getMessage());
        return [];
    }
    
    return [];
}

// Obtener mensajes de éxito/error
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $errores[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

$consultas_guardadas = obtenerConsultasGuardadas($usuario_actual['id']);

include '../includes/header.php';
include '../includes/sidebar.php';

// Obtener opciones para los filtros
function obtenerOpcionesFiltro($campo) {
    global $conn;
    
    try {
        // Método 1: Usar PDO
        if (isset($conn) && $conn instanceof PDO) {
            $query = "SELECT DISTINCT $campo FROM clientes WHERE $campo IS NOT NULL AND $campo != '' ORDER BY $campo";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        // Método 2: Usar mysqli
        elseif (isset($conn) && $conn instanceof mysqli) {
            $campo = $conn->real_escape_string($campo);
            $query = "SELECT DISTINCT $campo FROM clientes WHERE $campo IS NOT NULL AND $campo != '' ORDER BY $campo";
            $result = $conn->query($query);
            
            $opciones = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_array()) {
                    $opciones[] = $row[0];
                }
            }
            return $opciones;
        }
        // Método 3: Usar clase Database
        else {
            if (class_exists('Database')) {
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT DISTINCT $campo FROM clientes WHERE $campo IS NOT NULL AND $campo != '' ORDER BY $campo";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    } catch (Exception $e) {
        error_log("Error obteniendo opciones para $campo: " . $e->getMessage());
        return [];
    }
    
    return [];
}

// Obtener opciones
$paises = obtenerOpcionesFiltro('Pais');
$campanias = obtenerOpcionesFiltro('Campaña');
$asignados = obtenerOpcionesFiltro('Asignado');
$estados = obtenerOpcionesFiltro('Estado');
$gestiones = obtenerOpcionesFiltro('UltimaGestion');
?>

<div class="main-content">
    <!-- Header Mejorado -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Exportar Leads</h1>
                <p class="page-description">Filtrar y seleccionar leads para exportación</p>
            </div>
            <div class="header-actions">
                <div class="user-welcome">
                    <span class="welcome-text">Bienvenido,</span>
                    <span class="username"><?php echo getCurrentUsername(); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="content-wrapper">
        <!-- MODAL PARA GUARDAR CONSULTA -->
        <div id="modalGuardarConsulta" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-save"></i> Guardar Consulta</h3>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formGuardarConsulta" method="POST" action="guardar_consulta.php">
                        <div class="form-group">
                            <label for="nombreConsulta">Nombre de la consulta:</label>
                            <input type="text" id="nombreConsulta" name="nombre" 
                                class="form-control" required 
                                placeholder="Ej: Leads Argentina Asignados a Juan">
                        </div>
                        <div class="form-group">
                            <label for="descripcionConsulta">Descripción (opcional):</label>
                            <textarea id="descripcionConsulta" name="descripcion" 
                                    class="form-control" rows="2"
                                    placeholder="Breve descripción de los filtros aplicados"></textarea>
                        </div>
                        <!-- AÑADE ESTE CAMPO OCULTO PARA EL TIPO -->
                        <input type="hidden" name="tipo" value="exportacion">
                        <input type="hidden" id="filtrosConsulta" name="filtros">
                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-secondary close-modal">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header-enhanced">
                <div class="card-title">
                    <i class="fas fa-file-export"></i>
                    <h2>Exportar Leads</h2>
                </div>
                <div class="card-badge">
                    <span class="badge success">Filtros</span>
                </div>
            </div>
            
            <div class="card-content">
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div class="alert-content">
                            <strong>¡Éxito!</strong>
                            <p><?php echo $mensaje; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="alert-content">
                            <strong>Error</strong>
                            <?php foreach ($errores as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- CONSULTAS GUARDADAS -->
                <?php if (!empty($consultas_guardadas)): ?>
                <div class="consultas-guardadas-card">
                    <div class="consultas-header">
                        <h3><i class="fas fa-history"></i> Consultas Guardadas</h3>
                        <small><?php echo count($consultas_guardadas); ?> guardadas</small>
                    </div>
                    <div class="consultas-list">
                        <?php foreach ($consultas_guardadas as $consulta): ?>
                            <div class="consulta-item" data-filtros="<?php echo htmlspecialchars($consulta['filtros']); ?>">
                                <div class="consulta-info">
                                    <strong><?php echo htmlspecialchars($consulta['nombre']); ?></strong>
                                    <small>Creada: <?php echo date('d/m/Y', strtotime($consulta['fecha_creacion'])); ?></small>
                                </div>
                                <div class="consulta-actions">
                                    <button type="button" class="btn-aplicar-consulta btn-xs btn-primary" 
                                            title="Aplicar esta consulta">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <a href="eliminar_consulta.php?id=<?php echo $consulta['id']; ?>&tipo=exportacion" 
                                        class="btn-xs btn-danger" 
                                        onclick="return confirm('¿Eliminar esta consulta?')"
                                        title="Eliminar consulta">
                                            <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CAMBIAR ACTION A resultados_exportacion.php -->
                <form method="GET" action="resultados_exportacion.php" class="export-form" id="formFiltros">
                    <!-- FILA 1: País, Asignado, Estado -->
                    <div class="filtros-fila">
                        <!-- Filtro País -->
                        <div class="form-group enhanced-checkbox-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-globe-americas"></i>
                                    País
                                </label>
                                <div class="filter-actions">
                                    <button type="button" class="btn-select-all btn-xs" data-target="paises">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="btn-deselect-all btn-xs" data-target="paises">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="checkbox-scroll-container compact">
                                <?php if (empty($paises)): ?>
                                    <div class="no-options compact">
                                        <i class="fas fa-info-circle"></i>
                                        No hay países disponibles
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-grid compact">
                                        <?php foreach ($paises as $pais): ?>
                                            <div class="checkbox-item compact">
                                                <input type="checkbox" 
                                                       id="pais_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $pais); ?>" 
                                                       name="paises[]" 
                                                       value="<?php echo htmlspecialchars($pais); ?>"
                                                       class="filter-checkbox compact">
                                                <label for="pais_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $pais); ?>">
                                                    <span class="checkbox-custom compact"></span>
                                                    <span class="checkbox-label compact"><?php echo htmlspecialchars($pais); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="filter-footer compact">
                                <small id="paises-selected">0 seleccionados</small>
                                <small class="total-items">Total: <?php echo count($paises); ?></small>
                            </div>
                        </div>

                        <!-- Filtro Asignado -->
                        <div class="form-group enhanced-checkbox-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-user-tag"></i>
                                    Asignado
                                </label>
                                <div class="filter-actions">
                                    <button type="button" class="btn-select-all btn-xs" data-target="asignados">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="btn-deselect-all btn-xs" data-target="asignados">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="checkbox-scroll-container compact">
                                <?php if (empty($asignados)): ?>
                                    <div class="no-options compact">
                                        <i class="fas fa-info-circle"></i>
                                        No hay asignados disponibles
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-grid compact">
                                        <?php foreach ($asignados as $asignado): ?>
                                            <div class="checkbox-item compact">
                                                <input type="checkbox" 
                                                       id="asignado_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $asignado); ?>" 
                                                       name="asignados[]" 
                                                       value="<?php echo htmlspecialchars($asignado); ?>"
                                                       class="filter-checkbox compact">
                                                <label for="asignado_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $asignado); ?>">
                                                    <span class="checkbox-custom compact"></span>
                                                    <span class="checkbox-label compact"><?php echo htmlspecialchars($asignado); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="filter-footer compact">
                                <small id="asignados-selected">0 seleccionados</small>
                                <small class="total-items">Total: <?php echo count($asignados); ?></small>
                            </div>
                        </div>

                        <!-- Filtro Estado -->
                        <div class="form-group enhanced-checkbox-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-flag"></i>
                                    Estado
                                </label>
                                <div class="filter-actions">
                                    <button type="button" class="btn-select-all btn-xs" data-target="estados">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="btn-deselect-all btn-xs" data-target="estados">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="checkbox-scroll-container compact">
                                <?php if (empty($estados)): ?>
                                    <div class="no-options compact">
                                        <i class="fas fa-info-circle"></i>
                                        No hay estados disponibles
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-grid compact">
                                        <?php foreach ($estados as $estado): ?>
                                            <div class="checkbox-item compact">
                                                <input type="checkbox" 
                                                       id="estado_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $estado); ?>" 
                                                       name="estados[]" 
                                                       value="<?php echo htmlspecialchars($estado); ?>"
                                                       class="filter-checkbox compact">
                                                <label for="estado_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $estado); ?>">
                                                    <span class="checkbox-custom compact"></span>
                                                    <span class="checkbox-label compact"><?php echo htmlspecialchars($estado); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="filter-footer compact">
                                <small id="estados-selected">0 seleccionados</small>
                                <small class="total-items">Total: <?php echo count($estados); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- FILA 2: Última Gestión, Fechas, Campaña -->
                    <div class="filtros-fila">
                        <!-- Filtro Última Gestión -->
                        <div class="form-group enhanced-checkbox-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-history"></i>
                                    Última Gestión
                                </label>
                                <div class="filter-actions">
                                    <button type="button" class="btn-select-all btn-xs" data-target="gestiones">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="btn-deselect-all btn-xs" data-target="gestiones">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="checkbox-scroll-container compact">
                                <?php if (empty($gestiones)): ?>
                                    <div class="no-options compact">
                                        <i class="fas fa-info-circle"></i>
                                        No hay gestiones disponibles
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-grid compact">
                                        <?php foreach ($gestiones as $gestion): ?>
                                            <div class="checkbox-item compact">
                                                <input type="checkbox" 
                                                       id="gestion_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $gestion); ?>" 
                                                       name="gestiones[]" 
                                                       value="<?php echo htmlspecialchars($gestion); ?>"
                                                       class="filter-checkbox compact">
                                                <label for="gestion_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $gestion); ?>">
                                                    <span class="checkbox-custom compact"></span>
                                                    <span class="checkbox-label compact"><?php echo htmlspecialchars($gestion); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="filter-footer compact">
                                <small id="gestiones-selected">0 seleccionados</small>
                                <small class="total-items">Total: <?php echo count($gestiones); ?></small>
                            </div>
                        </div>

                        <!-- Filtro Fecha Creación -->
                        <div class="form-group date-filter-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fecha de Creación
                                </label>
                            </div>
                            <div class="date-range-container compact">
                                <div class="date-input-group compact">
                                    <label for="fecha_inicio" class="date-label">Desde:</label>
                                    <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                           class="form-control date-input compact" 
                                           placeholder="dd/mm/aaaa">
                                </div>
                                <div class="date-input-group compact">
                                    <label for="fecha_fin" class="date-label">Hasta:</label>
                                    <input type="date" id="fecha_fin" name="fecha_fin" 
                                           class="form-control date-input compact" 
                                           placeholder="dd/mm/aaaa">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro Campaña (como select) -->
                        <div class="form-group select-filter-group compact">
                            <div class="filter-header compact">
                                <label class="form-label">
                                    <i class="fas fa-bullhorn"></i>
                                    Campaña
                                </label>
                                <div class="filter-actions">
                                    <button type="button" class="btn-select-all btn-xs" data-target="campanias">
                                        <i class="fas fa-check-double"></i> Todos
                                    </button>
                                    <button type="button" class="btn-deselect-all btn-xs" data-target="campanias">
                                        <i class="fas fa-times"></i> Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="select-scroll-container compact">
                                <select id="campanias-select" name="campanias[]" multiple class="form-select-multiple">
                                    <?php if (empty($campanias)): ?>
                                        <option value="" disabled>No hay campañas disponibles</option>
                                    <?php else: ?>
                                        <?php foreach ($campanias as $campania): ?>
                                            <option value="<?php echo htmlspecialchars($campania); ?>">
                                                <?php echo htmlspecialchars($campania); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="filter-footer compact">
                                <small id="campanias-selected">0 seleccionados</small>
                                <small class="total-items">Total: <?php echo count($campanias); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-enhanced">
                        <!-- BOTÓN PARA GUARDAR CONSULTA -->
                        <button type="button" id="btnGuardarConsulta" class="btn btn-success btn-medium">
                            <i class="fas fa-save"></i>
                            <span>Guardar Consulta</span>
                        </button>
                        
                        <!-- CAMBIAR BOTÓN A "MOSTRAR" -->
                        <button type="submit" name="mostrar" class="btn btn-primary btn-medium">
                            <i class="fas fa-eye"></i>
                            <span>Mostrar Leads</span>
                        </button>
                        <button type="button" id="limpiarFiltros" class="btn btn-secondary btn-medium">
                            <i class="fas fa-eraser"></i>
                            <span>Limpiar Filtros</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== CONSULTAS EN FORMATO GRID MODERNO ===== */

.consultas-list {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 4 por fila */
    gap: 12px;
    max-height: 260px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Card estilo widget */
.consulta-item {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid #e1e8ed;
    border-radius: 10px;
    padding: 12px;
    min-height: 90px;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}

.consulta-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(52, 152, 219, 0.15);
    border-color: #3498db;
}

/* Información */
.consulta-info strong {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
    display: block;
}

.consulta-info small {
    font-size: 11px;
    color: #7f8c8d;
}

/* Botones abajo a la derecha */
.consulta-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 10px;
}

/* Botón aplicar */
.btn-aplicar-consulta {
    background: #3498db;
    border: none;
    border-radius: 5px;
    color: white;
    width: 26px;
    height: 26px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}

.btn-aplicar-consulta:hover {
    background: #2980b9;
}

/* Botón eliminar */
.btn-danger {
    border-radius: 5px;
    width: 26px;
    height: 26px;
    padding: 0;
}


/* ===== ESTILOS PARA MODAL ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e1e8ed;
}

.modal-header h3 {
    margin: 0;
    font-size: 16px;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-modal:hover {
    background: #f8f9fa;
    color: #2c3e50;
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
    color: #495057;
    font-size: 13px;
}

.modal-body input,
.modal-body textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 13px;
}

.modal-body input:focus,
.modal-body textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

/* Botón éxito */
.btn-success {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(46, 204, 113, 0.2);
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
}

/* Botón peligro */
.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2);
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

/* ===== ESTILOS MEJORADOS Y COMPACTOS PARA FILTROS ===== */

/* Layout principal - FILAS DE 3 COLUMNAS */
.filtros-fila {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 20px;
}

/* Grupos de checkboxes COMPACTOS */
.enhanced-checkbox-group.compact,
.date-filter-group.compact,
.select-filter-group.compact {
    background: #ffffff;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 240px;
}

/* Header compacto */
.filter-header.compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e1e8ed;
}

.filter-header.compact .form-label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 13px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-header.compact .form-label i {
    font-size: 12px;
}

.filter-actions {
    display: flex;
    gap: 6px;
}

.btn-xs {
    padding: 4px 8px;
    border: 1px solid #3498db;
    border-radius: 3px;
    background: white;
    color: #3498db;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 3px;
}

.btn-xs:hover {
    background: #3498db;
    color: white;
    transform: translateY(-1px);
}

.btn-select-all {
    border-color: #2ecc71;
    color: #2ecc71;
}

.btn-select-all:hover {
    background: #2ecc71;
    color: white;
}

.btn-deselect-all {
    border-color: #e74c3c;
    color: #e74c3c;
}

.btn-deselect-all:hover {
    background: #e74c3c;
    color: white;
}

/* Contenedor con scroll COMPACTO */
.checkbox-scroll-container.compact,
.select-scroll-container.compact {
    flex: 1;
    max-height: 180px;
    min-height: 160px;
    overflow-y: auto;
    padding: 12px 16px;
    background: white;
}

/* Grid de checkboxes COMPACTO */
.checkbox-grid.compact {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

/* Items individuales COMPACTOS */
.checkbox-item.compact {
    display: flex;
    align-items: center;
    margin: 0;
}

.checkbox-item.compact input[type="checkbox"] {
    display: none;
}

.checkbox-item.compact label {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    background: #f8f9fa;
    border: 1px solid transparent;
    font-size: 11px;
    min-height: 32px;
}

.checkbox-item.compact label:hover {
    background: #e3f2fd;
    border-color: #3498db;
}

.checkbox-custom.compact {
    width: 12px;
    height: 12px;
    border: 2px solid #95a5a6;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.checkbox-item.compact input:checked + label .checkbox-custom.compact {
    background: #3498db;
    border-color: #3498db;
}

.checkbox-item.compact input:checked + label .checkbox-custom.compact::after {
    content: '✓';
    color: white;
    font-size: 9px;
    font-weight: bold;
}

.checkbox-label.compact {
    font-size: 11px;
    color: #2c3e50;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.checkbox-item.compact input:checked + label {
    background: #e8f4fd;
    border-color: #3498db;
    color: #2980b9;
}

/* Select multiple estilizado */
.form-select-multiple {
    width: 100%;
    height: 136px;
    padding: 8px;
    border: 1px solid #e1e8ed;
    border-radius: 4px;
    background: white;
    font-size: 12px;
    color: #2c3e50;
    outline: none;
    resize: vertical;
    overflow-y: auto;
}

.form-select-multiple:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.form-select-multiple option {
    padding: 6px 8px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.form-select-multiple option:hover {
    background-color: #e3f2fd;
}

.form-select-multiple option:checked {
    background-color: #3498db;
    color: white;
}

/* Footer del filtro COMPACTO */
.filter-footer.compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 16px;
    background: #f8f9fa;
    border-top: 1px solid #e1e8ed;
    font-size: 10px;
}

.filter-footer.compact small {
    color: #6c757d;
    font-weight: 500;
}

.filter-footer.compact #paises-selected,
.filter-footer.compact #campanias-selected,
.filter-footer.compact #asignados-selected,
.filter-footer.compact #estados-selected,
.filter-footer.compact #gestiones-selected {
    color: #3498db;
    font-weight: 600;
}

.total-items {
    color: #95a5a6;
}

/* Mensaje sin opciones COMPACTO */
.no-options.compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px 16px;
    color: #95a5a6;
    text-align: center;
    gap: 5px;
    font-size: 11px;
    grid-column: 1 / -1;
    height: 100%;
}

.no-options.compact i {
    font-size: 14px;
    margin-bottom: 3px;
}

/* Estilos para fechas COMPACTAS */
.date-filter-group.compact .filter-header.compact {
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e1e8ed;
}

.date-range-container.compact {
    flex: 1;
    padding: 16px;
    background: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 16px;
}

.date-input-group.compact {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.date-label {
    font-size: 11px;
    font-weight: 500;
    color: #495057;
    margin-bottom: 2px;
}

.date-input.compact {
    padding: 8px 10px;
    border: 1px solid #e1e8ed;
    border-radius: 4px;
    font-size: 12px;
    transition: all 0.3s ease;
    width: 100%;
    height: 34px;
}

.date-input.compact:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

/* Botones principales */
.form-actions-enhanced {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e1e8ed;
    background: white;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    min-width: 150px;
    justify-content: center;
}

.btn-medium {
    padding: 10px 24px;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(149, 165, 166, 0.2);
}

.btn-secondary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
}

/* Custom scrollbar */
.checkbox-scroll-container.compact::-webkit-scrollbar,
.select-scroll-container.compact::-webkit-scrollbar,
.form-select-multiple::-webkit-scrollbar {
    width: 5px;
}

.checkbox-scroll-container.compact::-webkit-scrollbar-track,
.select-scroll-container.compact::-webkit-scrollbar-track,
.form-select-multiple::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.checkbox-scroll-container.compact::-webkit-scrollbar-thumb,
.select-scroll-container.compact::-webkit-scrollbar-thumb,
.form-select-multiple::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

.checkbox-scroll-container.compact::-webkit-scrollbar-thumb:hover,
.select-scroll-container.compact::-webkit-scrollbar-thumb:hover,
.form-select-multiple::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* ===== SOLUCIÓN PARA EL SCROLL ===== */

/* Permitir scroll en el contenido principal */
.content-area {
    flex-grow: 1;
    padding: 20px;
    overflow-y: auto;
    background-color: #f5f7fa;
    height: calc(100vh - 70px);
}

/* Ajustar el card para que sea desplazable */
.dashboard-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 20px;
    max-height: calc(100vh - 140px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Hacer que el contenido del card sea desplazable */
.card-content {
    flex: 1;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 15px;
    max-height: calc(70vh - 80px);
}

/* Mejorar el formulario */
.export-form {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 400px;
}

/* Ajustes para el header */
.page-header {
    padding: 15px 25px;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    z-index: 10;
    position: relative;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title h1 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.page-description {
    color: #7f8c8d;
    font-size: 13px;
}

.user-welcome {
    text-align: right;
}

.welcome-text {
    color: #7f8c8d;
    font-size: 13px;
}

.username {
    color: #2c3e50;
    font-weight: 600;
    font-size: 13px;
    display: block;
}

/* Card header más compacto */
.card-header-enhanced {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e1e8ed;
}

.card-header-enhanced .card-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header-enhanced .card-title h2 {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.card-header-enhanced .card-title i {
    color: #3498db;
    font-size: 18px;
}

.card-badge .badge.success {
    background: #d4edda;
    color: #155724;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

/* Alertas más compactas */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.alert i {
    font-size: 16px;
}

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    font-size: 13px;
    margin-bottom: 2px;
}

.alert-content p {
    font-size: 12px;
    margin: 0;
}

/* Scrollbar personalizada */
.card-content::-webkit-scrollbar,
.checkbox-scroll-container.compact::-webkit-scrollbar,
.select-scroll-container.compact::-webkit-scrollbar,
.form-select-multiple::-webkit-scrollbar {
    width: 6px;
}

.card-content::-webkit-scrollbar-track,
.checkbox-scroll-container.compact::-webkit-scrollbar-track,
.select-scroll-container.compact::-webkit-scrollbar-track,
.form-select-multiple::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.card-content::-webkit-scrollbar-thumb,
.checkbox-scroll-container.compact::-webkit-scrollbar-thumb,
.select-scroll-container.compact::-webkit-scrollbar-thumb,
.form-select-multiple::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.card-content::-webkit-scrollbar-thumb:hover,
.checkbox-scroll-container.compact::-webkit-scrollbar-thumb:hover,
.select-scroll-container.compact::-webkit-scrollbar-thumb:hover,
.form-select-multiple::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive */
@media (max-width: 1200px) {
    .filtros-fila {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .filtros-fila {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .checkbox-grid.compact {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .dashboard-card {
        max-height: calc(100vh - 130px);
    }
    
    .card-content {
        max-height: calc(70vh - 100px);
    }
}

@media (max-width: 768px) {
    .form-actions-enhanced {
        flex-direction: column;
    }
    
    .btn-medium {
        width: 100%;
    }
    
    .filter-header.compact {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
    
    .filter-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .checkbox-grid.compact {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dashboard-card {
        padding: 15px;
        max-height: calc(100vh - 120px);
    }
    
    .content-area {
        padding: 15px;
        height: calc(100vh - 60px);
    }
    
    .page-header {
        padding: 12px 20px;
    }
    
    .header-title h1 {
        font-size: 18px;
    }
    
    .page-description {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .checkbox-grid.compact {
        grid-template-columns: 1fr;
    }
    
    .filter-header.compact {
        padding: 10px 14px;
    }
    
    .checkbox-scroll-container.compact,
    .select-scroll-container.compact {
        padding: 10px 14px;
        max-height: 160px;
        min-height: 140px;
    }
    
    .checkbox-item.compact label {
        font-size: 10px;
        padding: 5px 6px;
    }
    
    .checkbox-label.compact {
        font-size: 10px;
    }
    
    .filter-footer.compact {
        padding: 6px 14px;
        font-size: 9px;
    }
    
    .date-range-container.compact {
        padding: 14px;
    }
    
    .date-label {
        font-size: 10px;
    }
    
    .date-input.compact {
        font-size: 11px;
        height: 32px;
        padding: 6px 8px;
    }
    
    .form-select-multiple {
        height: 120px;
        font-size: 11px;
    }
    
    .card-header-enhanced {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .card-badge {
        align-self: flex-start;
    }
    @media (max-width: 1200px) {
    .consultas-list {
        grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 992px) {
        .consultas-list {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .consultas-list {
            grid-template-columns: 1fr;
        }
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables para consultas guardadas
    const modalGuardarConsulta = document.getElementById('modalGuardarConsulta');
    const btnGuardarConsulta = document.getElementById('btnGuardarConsulta');
    const formGuardarConsulta = document.getElementById('formGuardarConsulta');
    const filtrosConsulta = document.getElementById('filtrosConsulta');
    const closeModalButtons = document.querySelectorAll('.close-modal');

    // Función para obtener todos los filtros actuales como JSON
    function obtenerFiltrosActuales() {
        const form = document.getElementById('formFiltros');
        const formData = new FormData(form);
        const filtros = {};
        
        // Obtener checkboxes
        ['paises', 'asignados', 'estados', 'gestiones'].forEach(nombre => {
            const valores = formData.getAll(`${nombre}[]`);
            if (valores.length > 0) {
                filtros[nombre] = valores;
            }
        });
        
        // Obtener select multiple de campañas
        const campaniasSelect = document.getElementById('campanias-select');
        if (campaniasSelect) {
            const campaniasSeleccionadas = Array.from(campaniasSelect.selectedOptions).map(opt => opt.value);
            if (campaniasSeleccionadas.length > 0) {
                filtros.campanias = campaniasSeleccionadas;
            }
        }
        
        // Obtener fechas
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        
        if (fechaInicio) filtros.fecha_inicio = fechaInicio;
        if (fechaFin) filtros.fecha_fin = fechaFin;
        
        return JSON.stringify(filtros);
    }

    // Mostrar modal para guardar consulta
    btnGuardarConsulta.addEventListener('click', function() {
        // Verificar que haya filtros seleccionados
        const filtros = obtenerFiltrosActuales();
        if (filtros === '{}') {
            alert('Por favor, selecciona al menos un filtro antes de guardar la consulta.');
            return;
        }
        
        // Guardar filtros en el campo oculto
        filtrosConsulta.value = filtros;
        
        // Mostrar modal
        modalGuardarConsulta.style.display = 'flex';
        document.getElementById('nombreConsulta').focus();
    });

    // Cerrar modal
    closeModalButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            modalGuardarConsulta.style.display = 'none';
        });
    });

    // Cerrar modal al hacer clic fuera
    modalGuardarConsulta.addEventListener('click', function(e) {
        if (e.target === modalGuardarConsulta) {
            modalGuardarConsulta.style.display = 'none';
        }
    });

    // Aplicar consulta guardada
    document.querySelectorAll('.btn-aplicar-consulta').forEach(btn => {
        btn.addEventListener('click', function() {
            const consultaItem = this.closest('.consulta-item');
            const filtrosJSON = consultaItem.getAttribute('data-filtros');
            
            try {
                const filtros = JSON.parse(filtrosJSON);
                
                // Limpiar filtros actuales primero
                document.getElementById('limpiarFiltros').click();
                
                // Aplicar checkboxes
                ['paises', 'asignados', 'estados', 'gestiones'].forEach(nombre => {
                    if (filtros[nombre]) {
                        filtros[nombre].forEach(valor => {
                            const checkbox = document.querySelector(`[name="${nombre}[]"][value="${CSS.escape(valor)}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                                const label = checkbox.closest('.checkbox-item').querySelector('label');
                                label.style.background = '#e8f4fd';
                                label.style.borderColor = '#3498db';
                            }
                        });
                    }
                });
                
                // Aplicar campañas
                if (filtros.campanias) {
                    const campaniasSelect = document.getElementById('campanias-select');
                    if (campaniasSelect) {
                        Array.from(campaniasSelect.options).forEach(option => {
                            option.selected = filtros.campanias.includes(option.value);
                        });
                    }
                }
                
                // Aplicar fechas
                if (filtros.fecha_inicio) {
                    document.getElementById('fecha_inicio').value = filtros.fecha_inicio;
                }
                if (filtros.fecha_fin) {
                    document.getElementById('fecha_fin').value = filtros.fecha_fin;
                }
                
                // Actualizar contadores
                actualizarContadores();
                
                // Mostrar mensaje de éxito
                alert('Consulta aplicada correctamente');
                
                // Desplazar hacia el formulario
                document.getElementById('formFiltros').scrollIntoView({ behavior: 'smooth' });
                
            } catch (error) {
                console.error('Error al aplicar consulta:', error);
                alert('Error al aplicar la consulta guardada');
            }
        });
    });

    // Limpiar filtros
    document.getElementById('limpiarFiltros').addEventListener('click', function() {
        // Desmarcar todos los checkboxes
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.checked = false;
            // Actualizar estado visual
            const label = checkbox.closest('.checkbox-item').querySelector('label');
            label.style.background = '#f8f9fa';
            label.style.borderColor = 'transparent';
        });
        
        // Limpiar select multiple de campañas
        const campaniasSelect = document.getElementById('campanias-select');
        if (campaniasSelect) {
            Array.from(campaniasSelect.options).forEach(option => {
                option.selected = false;
            });
        }
        
        // Limpiar fechas
        document.querySelectorAll('.date-input').forEach(input => {
            input.value = '';
        });
        
        // Actualizar contadores
        actualizarContadores();
    });
    
    // Función para actualizar contadores
    function actualizarContadores() {
        // Contadores para checkboxes
        const grupos = [
            { id: 'paises', element: 'paises-selected' },
            { id: 'asignados', element: 'asignados-selected' },
            { id: 'estados', element: 'estados-selected' },
            { id: 'gestiones', element: 'gestiones-selected' }
        ];
        
        grupos.forEach(grupo => {
            const checkboxes = document.querySelectorAll(`[name="${grupo.id}[]"]`);
            const seleccionados = Array.from(checkboxes).filter(cb => cb.checked).length;
            const contador = document.getElementById(grupo.element);
            
            if (contador) {
                contador.textContent = `${seleccionados} seleccionado${seleccionados !== 1 ? 's' : ''}`;
            }
        });
        
        // Contador para select multiple de campañas
        const campaniasSelect = document.getElementById('campanias-select');
        if (campaniasSelect) {
            const campaniasSeleccionadas = Array.from(campaniasSelect.selectedOptions).length;
            const contadorCampanias = document.getElementById('campanias-selected');
            if (contadorCampanias) {
                contadorCampanias.textContent = `${campaniasSeleccionadas} seleccionado${campaniasSeleccionadas !== 1 ? 's' : ''}`;
            }
        }
    }
    
    // Función para seleccionar/deseleccionar todos - CORREGIDA PARA SELECT MULTIPLE
    function setupSelectAllButtons() {
        document.querySelectorAll('.btn-select-all').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                
                if (target === 'campanias') {
                    const campaniasSelect = document.getElementById('campanias-select');
                    if (campaniasSelect) {
                        Array.from(campaniasSelect.options).forEach(option => {
                            option.selected = true;
                        });
                    }
                } else {
                    const checkboxes = document.querySelectorAll(`[name="${target}[]"]`);
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = true;
                        const label = checkbox.closest('.checkbox-item').querySelector('label');
                        label.style.background = '#e8f4fd';
                        label.style.borderColor = '#3498db';
                    });
                }
                
                actualizarContadores();
            });
        });
        
        document.querySelectorAll('.btn-deselect-all').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                
                if (target === 'campanias') {
                    const campaniasSelect = document.getElementById('campanias-select');
                    if (campaniasSelect) {
                        Array.from(campaniasSelect.options).forEach(option => {
                            option.selected = false;
                        });
                    }
                } else {
                    const checkboxes = document.querySelectorAll(`[name="${target}[]"]`);
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = false;
                        const label = checkbox.closest('.checkbox-item').querySelector('label');
                        label.style.background = '#f8f9fa';
                        label.style.borderColor = 'transparent';
                    });
                }
                
                actualizarContadores();
            });
        });
    }
    
    // Escuchar cambios en checkboxes para actualizar estilos
    document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const label = this.closest('.checkbox-item').querySelector('label');
            if (this.checked) {
                label.style.background = '#e8f4fd';
                label.style.borderColor = '#3498db';
            } else {
                label.style.background = '#f8f9fa';
                label.style.borderColor = 'transparent';
            }
            actualizarContadores();
        });
    });
    
    // Escuchar cambios en el select multiple de campañas
    const campaniasSelect = document.getElementById('campanias-select');
    if (campaniasSelect) {
        campaniasSelect.addEventListener('change', function() {
            actualizarContadores();
        });
    }
    
    // Prevenir envío del formulario si no hay fechas válidas
    document.querySelector('form').addEventListener('submit', function(e) {
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        
        if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
            e.preventDefault();
            alert('La fecha de inicio no puede ser mayor a la fecha de fin');
            document.getElementById('fecha_inicio').focus();
            return;
        }
        
        // NO PREVENIR EL ENVÍO - dejar que el formulario se envíe normalmente
        // El navegador manejará automáticamente los arrays
    });
    
    // Inicializar
    setupSelectAllButtons();
    actualizarContadores();
    
    // Auto-seleccionar fechas comunes
    const today = new Date();
    const lastWeek = new Date();
    lastWeek.setDate(today.getDate() - 7);
    
    // Formatear fechas para input type="date" (YYYY-MM-DD)
    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };
    
    // Opcional: Establecer fechas por defecto
    // document.getElementById('fecha_inicio').value = formatDate(lastWeek);
    // document.getElementById('fecha_fin').value = formatDate(today);
});
</script>