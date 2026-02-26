<?php
// asignar_individual.php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'asignar_individual';

// Obtener usuario actual
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

// =============================================
// NUEVO: FUNCIÓN PARA OBTENER CONSULTAS GUARDADAS
// =============================================
function obtenerConsultasGuardadas($usuario_id) {
    global $conn, $db;
    
    try {
        // Usar la conexión disponible
        if (isset($db) && $db instanceof PDO) {
            $query = "SELECT id, nombre, filtros, fecha_creacion 
                      FROM consultas_guardadas 
                      WHERE usuario_id = :usuario_id 
                      AND (tipo = 'asignacion' OR tipo IS NULL)  -- Incluir consultas sin tipo (compatibilidad)
                      ORDER BY fecha_creacion DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Para conexión mysqli
        elseif (isset($conn) && $conn instanceof mysqli) {
            $usuario_id = $conn->real_escape_string($usuario_id);
            $query = "SELECT id, nombre, filtros, fecha_creacion 
                      FROM consultas_guardadas 
                      WHERE usuario_id = '$usuario_id' 
                      AND (tipo = 'asignacion' OR tipo IS NULL)  -- Incluir consultas sin tipo (compatibilidad)
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
    } catch (Exception $e) {
        error_log("Error obteniendo consultas guardadas: " . $e->getMessage());
        return [];
    }
    
    return [];
}

// =============================================
// NUEVO: OBTENER CONSULTAS GUARDADAS Y MENSAJES
// =============================================
$consultas_guardadas = obtenerConsultasGuardadas($usuario_actual['id']);

// Obtener mensajes de éxito/error (si existen)
$mensaje = '';
$errores = [];

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $errores[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Obtener opciones para filtros desde la base de datos
try {
    // Países únicos
    $query_paises = "SELECT DISTINCT Pais FROM clientes WHERE Pais IS NOT NULL AND Pais != '' ORDER BY Pais";
    $stmt_paises = $db->prepare($query_paises);
    $stmt_paises->execute();
    $paises = $stmt_paises->fetchAll(PDO::FETCH_COLUMN);
    
    // Usuarios asignados únicos
    $query_asignados = "SELECT DISTINCT Asignado FROM clientes WHERE Asignado IS NOT NULL AND Asignado != '' ORDER BY Asignado";
    $stmt_asignados = $db->prepare($query_asignados);
    $stmt_asignados->execute();
    $asignados = $stmt_asignados->fetchAll(PDO::FETCH_COLUMN);
    
    // Apellidos únicos
    $query_apellidos = "SELECT DISTINCT Apellido FROM clientes WHERE Apellido IS NOT NULL AND Apellido != '' ORDER BY Apellido";
    $stmt_apellidos = $db->prepare($query_apellidos);
    $stmt_apellidos->execute();
    $apellidos = $stmt_apellidos->fetchAll(PDO::FETCH_COLUMN);
    
    // Estados únicos
    $query_estados = "SELECT DISTINCT Estado FROM clientes WHERE Estado IS NOT NULL AND Estado != '' ORDER BY Estado";
    $stmt_estados = $db->prepare($query_estados);
    $stmt_estados->execute();
    $estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
    
    // Últimas gestiones únicas
    $query_gestiones = "SELECT DISTINCT UltimaGestion FROM clientes WHERE UltimaGestion IS NOT NULL AND UltimaGestion != '' ORDER BY UltimaGestion";
    $stmt_gestiones = $db->prepare($query_gestiones);
    $stmt_gestiones->execute();
    $gestiones = $stmt_gestiones->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $paises = $asignados = $apellidos = $estados = $gestiones = [];
}

// Configuración de paginación
$registros_por_pagina_default = 20;

// Obtener valor del formulario o usar por defecto
if (isset($_GET['registros_por_pagina'])) {
    $registros_input = trim($_GET['registros_por_pagina']);
    
    // Validar que sea un número positivo
    if (is_numeric($registros_input) && $registros_input > 0) {
        $registros_por_pagina = (int)$registros_input;
        
        // Limitar máximo a 1000 por razones de rendimiento
        if ($registros_por_pagina > 1000) {
            $registros_por_pagina = 1000;
        }
        
        // Mínimo 1 registro por página
        if ($registros_por_pagina < 1) {
            $registros_por_pagina = 1;
        }
    } else {
        $registros_por_pagina = $registros_por_pagina_default;
    }
} else {
    $registros_por_pagina = $registros_por_pagina_default;
}

$pagina_actual_num = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual_num - 1) * $registros_por_pagina;

// Parámetros de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$campo_busqueda = isset($_GET['campo_busqueda']) ? $_GET['campo_busqueda'] : 'todos';

// Parámetros de ordenamiento
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'TP';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'ASC';

// FUNCIÓN MEJORADA PARA PROCESAR BÚSQUEDAS MÚLTIPLES
function procesarBusquedaMultiple($busqueda) {
    $terminos = [];
    
    if (!empty($busqueda)) {
        // Separar por comas y limpiar cada término
        $terminos_raw = explode(',', $busqueda);
        foreach ($terminos_raw as $termino) {
            $termino_limpio = trim($termino);
            if (!empty($termino_limpio)) {
                $terminos[] = $termino_limpio;
            }
        }
    }
    
    return $terminos;
}

// Procesar búsqueda múltiple
$terminos_busqueda = procesarBusquedaMultiple($busqueda);
$es_busqueda_multiple = count($terminos_busqueda) > 1;

// Construir consulta con filtros
$where_conditions = [];
$params = [];

// BÚSQUEDA MÚLTIPLE MEJORADA
if (!empty($terminos_busqueda)) {
    // Array de campos según la opción seleccionada
    $campos_a_buscar = [];
    
    switch ($campo_busqueda) {
        case 'tp':
            $campos_a_buscar = ['TP'];
            break;
        case 'nombre':
            $campos_a_buscar = ['Nombre', 'Apellido'];
            break;
        case 'asignado':
            $campos_a_buscar = ['Asignado'];
            break;
        case 'ambos':
            $campos_a_buscar = ['TP', 'Nombre', 'Apellido'];
            break;
        case 'todos':
        default:
            $campos_a_buscar = ['TP', 'Nombre', 'Apellido', 'Correo', 'Numero', 'Asignado'];
            break;
    }
    
    // Construir condiciones para cada término
    $condiciones_por_termino = [];
    
    foreach ($terminos_busqueda as $index_termino => $termino) {
        $condiciones_por_campo = [];
        
        foreach ($campos_a_buscar as $campo) {
            $param_name = ":busqueda_{$campo}_{$index_termino}";
            $condiciones_por_campo[] = "{$campo} LIKE {$param_name}";
            $params[$param_name] = "%{$termino}%";
        }
        
        if (!empty($condiciones_por_campo)) {
            $condiciones_por_termino[] = "(" . implode(" OR ", $condiciones_por_campo) . ")";
        }
    }
    
    if (!empty($condiciones_por_termino)) {
        // Si es búsqueda múltiple, usar OR entre términos
        if ($es_busqueda_multiple) {
            $where_conditions[] = "(" . implode(" OR ", $condiciones_por_termino) . ")";
        } else {
            $where_conditions[] = $condiciones_por_termino[0];
        }
    }
}

// Filtros de país
if (isset($_GET['paises']) && is_array($_GET['paises']) && !empty($_GET['paises'])) {
    $placeholders = [];
    foreach ($_GET['paises'] as $index => $pais) {
        $param = ':pais_' . $index;
        $placeholders[] = $param;
        $params[$param] = $pais;
    }
    $where_conditions[] = "Pais IN (" . implode(', ', $placeholders) . ")";
}

// Filtros de asignado
if (isset($_GET['asignados']) && is_array($_GET['asignados']) && !empty($_GET['asignados'])) {
    $placeholders = [];
    foreach ($_GET['asignados'] as $index => $asignado) {
        $param = ':asignado_' . $index;
        $placeholders[] = $param;
        $params[$param] = $asignado;
    }
    $where_conditions[] = "Asignado IN (" . implode(', ', $placeholders) . ")";
}

// Filtros de apellido
if (isset($_GET['apellidos']) && is_array($_GET['apellidos']) && !empty($_GET['apellidos'])) {
    $placeholders = [];
    foreach ($_GET['apellidos'] as $index => $apellido) {
        $param = ':apellido_' . $index;
        $placeholders[] = $param;
        $params[$param] = $apellido;
    }
    $where_conditions[] = "Apellido IN (" . implode(', ', $placeholders) . ")";
}

// Filtros de estado
if (isset($_GET['estados']) && is_array($_GET['estados']) && !empty($_GET['estados'])) {
    $placeholders = [];
    foreach ($_GET['estados'] as $index => $estado) {
        $param = ':estado_' . $index;
        $placeholders[] = $param;
        $params[$param] = $estado;
    }
    $where_conditions[] = "Estado IN (" . implode(', ', $placeholders) . ")";
}

// Filtros de última gestión
if (isset($_GET['gestiones']) && is_array($_GET['gestiones']) && !empty($_GET['gestiones'])) {
    $placeholders = [];
    foreach ($_GET['gestiones'] as $index => $gestion) {
        $param = ':gestion_' . $index;
        $placeholders[] = $param;
        $params[$param] = $gestion;
    }
    $where_conditions[] = "UltimaGestion IN (" . implode(', ', $placeholders) . ")";
}

// Construir WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para obtener el total de clientes con filtros
try {
    $query_total = "SELECT COUNT(*) as total FROM clientes $where_clause";
    $stmt_total = $db->prepare($query_total);
    foreach ($params as $key => $value) {
        $stmt_total->bindValue($key, $value);
    }
    $stmt_total->execute();
    $total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);
} catch (PDOException $e) {
    $total_registros = 0;
    $total_paginas = 1;
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Asignar Individualmente</div>
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
        <!-- Card Principal -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    Asignar Clientes Individualmente
                    <span style="font-size: 14px; color: #7f8c8d; margin-left: 10px;">
                        (Total: <?php echo $total_registros; ?> clientes)
                    </span>
                </div>
                <div class="card-actions">
                    <button class="btn btn-secondary" onclick="recargarClientes()">
                        <i class="fas fa-sync-alt"></i> Recargar
                    </button>
                </div>
            </div>

            <!-- ============================================= -->
            <!-- NUEVO: MODAL PARA GUARDAR CONSULTA -->
            <!-- ============================================= -->
            <div id="modalGuardarConsulta" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-save"></i> Guardar Consulta de Asignación</h3>
                        <button type="button" class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="formGuardarConsulta" method="POST" action="guardar_consulta.php">
                            <div class="form-group">
                                <label for="nombreConsulta">Nombre de la consulta:</label>
                                <input type="text" id="nombreConsulta" name="nombre" 
                                    class="form-control" required 
                                    placeholder="Ej: Leads Argentina para asignar a Juan">
                            </div>
                            <div class="form-group">
                                <label for="descripcionConsulta">Descripción (opcional):</label>
                                <textarea id="descripcionConsulta" name="descripcion" 
                                        class="form-control" rows="2"
                                        placeholder="Breve descripción de los filtros aplicados"></textarea>
                            </div>
                            <!-- CAMPO OCULTO IMPORTANTE: Esto define que es para asignación -->
                            <input type="hidden" name="tipo" value="asignacion">
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

            <!-- ============================================= -->
            <!-- NUEVO: CONSULTAS GUARDADAS -->
            <!-- ============================================= -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success" style="margin: 15px 20px;">
                    <i class="fas fa-check-circle"></i>
                    <div class="alert-content">
                        <strong>¡Éxito!</strong>
                        <p><?php echo $mensaje; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errores)): ?>
                <div class="alert alert-error" style="margin: 15px 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="alert-content">
                        <strong>Error</strong>
                        <?php foreach ($errores as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($consultas_guardadas)): ?>
            <div class="consultas-guardadas-card" style="margin: 15px 20px;">
                <div class="consultas-header">
                    <h3><i class="fas fa-history"></i> Consultas de Asignación Guardadas</h3>
                    <small><?php echo count($consultas_guardadas); ?> guardadas</small>
                </div>
                <div class="consultas-list" style="max-height: 150px; overflow-y: auto;">
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
                                <a href="eliminar_consulta.php?id=<?php echo $consulta['id']; ?>&tipo=asignacion" 
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

            <!-- BUSCADOR Y FILTROS -->
            <div class="filters-container" style="padding: 20px; border-bottom: 1px solid #ecf0f1; background-color: #f8f9fa;">
                <form id="filtersForm" method="GET" action="asignar_individual.php">
                    <!-- Búsqueda Múltiple Mejorada -->
                    <div class="filter-section">
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 16px;">
                            <i class="fas fa-search"></i> BÚSQUEDA AVANZADA
                        </h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div style="position: relative; flex: 1;">
                                <input type="text" 
                                       name="busqueda" 
                                       id="busquedaInput"
                                       placeholder="Ej: 12345, Juan Pérez, cliente@email.com, 555-1234..."
                                       value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>"
                                       class="form-control"
                                       style="padding-left: 40px; width: 100%;">
                                <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #7f8c8d;"></i>
                            </div>
                            
                            <select name="campo_busqueda" id="campoBusqueda" class="form-control" style="width: 200px;">
                                <option value="todos" <?php echo $campo_busqueda === 'todos' ? 'selected' : ''; ?>>Buscar en Todos los Campos</option>
                                <option value="ambos" <?php echo $campo_busqueda === 'ambos' ? 'selected' : ''; ?>>Buscar en TP y Nombre</option>
                                <option value="tp" <?php echo $campo_busqueda === 'tp' ? 'selected' : ''; ?>>Solo por TP</option>
                                <option value="nombre" <?php echo $campo_busqueda === 'nombre' ? 'selected' : ''; ?>>Solo por Nombre/Apellido</option>
                                <option value="asignado" <?php echo $campo_busqueda === 'asignado' ? 'selected' : ''; ?>>Solo por Asignado</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                        
                        <div style="margin-top: 10px; color: #7f8c8d; font-size: 12px; line-height: 1.4;">
                            <div><i class="fas fa-info-circle"></i> <strong>Búsqueda múltiple:</strong> Separe los términos con comas para buscar varios items a la vez</div>
                            <div><strong>Ejemplos:</strong></div>
                            <ul style="margin: 5px 0 0 20px; font-size: 11px;">
                                <li><code>12345, 67890</code> - Busca por TP 12345 O 67890</li>
                                <li><code>Juan, Maria, Carlos</code> - Busca por nombres que contengan Juan O Maria O Carlos</li>
                                <li><code>cliente@email.com, 555-1234</code> - Busca por correo O teléfono</li>
                                <li><code>Pérez, González</code> - Busca por apellidos Pérez O González</li>
                            </ul>
                        </div>
                    </div>

                    <div style="height: 1px; background: #ddd; margin: 20px 0;"></div>

                    <!-- Filtros en columnas -->
                    <div class="filters-grid">
                        <!-- Columna 1: País -->
                        <div class="filter-column">
                            <h4 class="filter-title">
                                <i class="fas fa-globe-americas"></i> PAÍS
                            </h4>
                            <div class="filter-options">
                                <?php foreach ($paises as $pais): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="paises[]" 
                                               value="<?php echo htmlspecialchars($pais); ?>"
                                               <?php echo (isset($_GET['paises']) && in_array($pais, $_GET['paises'])) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo htmlspecialchars($pais); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Columna 2: Asignado -->
                        <div class="filter-column">
                            <h4 class="filter-title">
                                <i class="fas fa-user"></i> ASIGNADO A
                            </h4>
                            <div class="filter-options">
                                <?php foreach ($asignados as $asignado): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="asignados[]" 
                                               value="<?php echo htmlspecialchars($asignado); ?>"
                                               <?php echo (isset($_GET['asignados']) && in_array($asignado, $_GET['asignados'])) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo htmlspecialchars($asignado); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Columna 3: Apellido -->
                        <div class="filter-column">
                            <h4 class="filter-title">
                                <i class="fas fa-signature"></i> APELLIDO
                            </h4>
                            <div class="filter-options">
                                <?php foreach ($apellidos as $apellido): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="apellidos[]" 
                                               value="<?php echo htmlspecialchars($apellido); ?>"
                                               <?php echo (isset($_GET['apellidos']) && in_array($apellido, $_GET['apellidos'])) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo htmlspecialchars($apellido); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Columna 4: Estado -->
                        <div class="filter-column">
                            <h4 class="filter-title">
                                <i class="fas fa-flag"></i> ESTADO
                            </h4>
                            <div class="filter-options">
                                <?php foreach ($estados as $estado): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="estados[]" 
                                               value="<?php echo htmlspecialchars($estado); ?>"
                                               <?php echo (isset($_GET['estados']) && in_array($estado, $_GET['estados'])) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo htmlspecialchars($estado); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Columna 5: Última Gestión -->
                        <div class="filter-column">
                            <h4 class="filter-title">
                                <i class="fas fa-history"></i> ÚLTIMA GESTIÓN
                            </h4>
                            <div class="filter-options">
                                <?php foreach ($gestiones as $gestion): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="gestiones[]" 
                                               value="<?php echo htmlspecialchars($gestion); ?>"
                                               <?php echo (isset($_GET['gestiones']) && in_array($gestion, $_GET['gestiones'])) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo htmlspecialchars($gestion); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones de filtros -->
                    <div class="filter-actions" style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                        <!-- ============================================= -->
                        <!-- NUEVO: BOTÓN PARA GUARDAR CONSULTA -->
                        <!-- ============================================= -->
                        <button type="button" id="btnGuardarConsulta" class="btn btn-success">
                            <i class="fas fa-save"></i> Guardar Consulta
                        </button>
                        
                        <button type="button" onclick="limpiarFiltros()" class="btn btn-secondary">
                            <i class="fas fa-broom"></i> Limpiar Todo
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                        
                        <!-- Configuración de registros por página -->
                        <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                            <label style="font-weight: 600; color: #2c3e50; white-space: nowrap;">
                                <i class="fas fa-list-ol"></i> Registros por página:
                            </label>
                            <input type="number" 
                                name="registros_por_pagina" 
                                class="form-control" 
                                placeholder="Ej: 50" 
                                value="<?php echo $registros_por_pagina; ?>"
                                min="1" 
                                max="1000"
                                style="width: 100px;">
                        </div>
                    </div>
                </form>

                <!-- NUEVO: CONTROLES DE ORDENAMIENTO -->
                <div style="margin-top: 15px; padding: 15px; background-color: #f1f8ff; border-radius: 4px; border: 1px solid #d1e7ff;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-sort" style="color: #3498db;"></i>
                            <strong style="color: #2c3e50;">Ordenar por:</strong>
                        </div>
                        
                        <form method="GET" action="asignar_individual.php" style="display: flex; gap: 10px; align-items: center;">
                            <!-- Mantener parámetros de búsqueda -->
                            <?php if (!empty($busqueda)): ?>
                            <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                            <?php endif; ?>
                            <?php if (!empty($campo_busqueda)): ?>
                            <input type="hidden" name="campo_busqueda" value="<?php echo htmlspecialchars($campo_busqueda); ?>">
                            <?php endif; ?>
                            <?php if (isset($_GET['paises']) && is_array($_GET['paises'])): ?>
                                <?php foreach ($_GET['paises'] as $pais): ?>
                                    <input type="hidden" name="paises[]" value="<?php echo htmlspecialchars($pais); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (isset($_GET['asignados']) && is_array($_GET['asignados'])): ?>
                                <?php foreach ($_GET['asignados'] as $asignado): ?>
                                    <input type="hidden" name="asignados[]" value="<?php echo htmlspecialchars($asignado); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (isset($_GET['apellidos']) && is_array($_GET['apellidos'])): ?>
                                <?php foreach ($_GET['apellidos'] as $apellido): ?>
                                    <input type="hidden" name="apellidos[]" value="<?php echo htmlspecialchars($apellido); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (isset($_GET['estados']) && is_array($_GET['estados'])): ?>
                                <?php foreach ($_GET['estados'] as $estado): ?>
                                    <input type="hidden" name="estados[]" value="<?php echo htmlspecialchars($estado); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (isset($_GET['gestiones']) && is_array($_GET['gestiones'])): ?>
                                <?php foreach ($_GET['gestiones'] as $gestion): ?>
                                    <input type="hidden" name="gestiones[]" value="<?php echo htmlspecialchars($gestion); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($registros_por_pagina)): ?>
                            <input type="hidden" name="registros_por_pagina" value="<?php echo htmlspecialchars($registros_por_pagina); ?>">
                            <?php endif; ?>
                            
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <label for="ordenar_por" style="font-weight: 500; color: #2c3e50;">Campo:</label>
                                <select id="ordenar_por" name="ordenar_por" class="form-control" style="width: 120px; padding: 6px 10px;">
                                    <option value="TP" <?php echo $ordenar_por === 'TP' ? 'selected' : ''; ?>>TP</option>
                                    <option value="Nombre" <?php echo $ordenar_por === 'Nombre' ? 'selected' : ''; ?>>Nombre</option>
                                    <option value="Pais" <?php echo $ordenar_por === 'Pais' ? 'selected' : ''; ?>>País</option>
                                    <option value="Asignado" <?php echo $ordenar_por === 'Asignado' ? 'selected' : ''; ?>>Asignado</option>
                                    <option value="Estado" <?php echo $ordenar_por === 'Estado' ? 'selected' : ''; ?>>Estado</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <label for="orden" style="font-weight: 500; color: #2c3e50;">Orden:</label>
                                <select id="orden" name="orden" class="form-control" style="width: 130px; padding: 6px 10px;">
                                    <option value="ASC" <?php echo $orden === 'ASC' ? 'selected' : ''; ?>>Ascendente (A→Z)</option>
                                    <option value="DESC" <?php echo $orden === 'DESC' ? 'selected' : ''; ?>>Descendente (Z→A)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 6px 15px;">
                                <i class="fas fa-sort-amount-down"></i> Ordenar
                            </button>
                            
                            <!-- Botón para resetear ordenamiento -->
                            <?php 
                            $url_reset = 'asignar_individual.php?';
                            $params_reset = [];
                            if (!empty($busqueda)) $params_reset[] = 'busqueda=' . urlencode($busqueda);
                            if (!empty($campo_busqueda)) $params_reset[] = 'campo_busqueda=' . urlencode($campo_busqueda);
                            if (isset($_GET['paises']) && is_array($_GET['paises'])) {
                                foreach ($_GET['paises'] as $pais) $params_reset[] = 'paises[]=' . urlencode($pais);
                            }
                            if (isset($_GET['asignados']) && is_array($_GET['asignados'])) {
                                foreach ($_GET['asignados'] as $asignado) $params_reset[] = 'asignados[]=' . urlencode($asignado);
                            }
                            if (isset($_GET['apellidos']) && is_array($_GET['apellidos'])) {
                                foreach ($_GET['apellidos'] as $apellido) $params_reset[] = 'apellidos[]=' . urlencode($apellido);
                            }
                            if (isset($_GET['estados']) && is_array($_GET['estados'])) {
                                foreach ($_GET['estados'] as $estado) $params_reset[] = 'estados[]=' . urlencode($estado);
                            }
                            if (isset($_GET['gestiones']) && is_array($_GET['gestiones'])) {
                                foreach ($_GET['gestiones'] as $gestion) $params_reset[] = 'gestiones[]=' . urlencode($gestion);
                            }
                            if (!empty($registros_por_pagina)) $params_reset[] = 'registros_por_pagina=' . $registros_por_pagina;
                            $url_reset .= implode('&', $params_reset);
                            ?>
                            <a href="<?php echo $url_reset; ?>" class="btn btn-secondary" style="padding: 6px 15px;">
                                <i class="fas fa-times"></i> Restablecer Orden
                            </a>
                        </form>
                        
                        <!-- Indicador de ordenamiento actual -->
                        <div style="margin-left: auto; display: flex; align-items: center; gap: 5px;">
                            <span style="font-size: 13px; color: #7f8c8d;">
                                <i class="fas fa-info-circle"></i> Orden actual:
                            </span>
                            <span class="badge badge-info" style="font-size: 12px; padding: 4px 8px;">
                                <?php 
                                $campo_texto = '';
                                switch($ordenar_por) {
                                    case 'TP': $campo_texto = 'TP'; break;
                                    case 'Nombre': $campo_texto = 'Nombre'; break;
                                    case 'Pais': $campo_texto = 'País'; break;
                                    case 'Asignado': $campo_texto = 'Asignado'; break;
                                    case 'Estado': $campo_texto = 'Estado'; break;
                                }
                                $orden_texto = $orden === 'ASC' ? 'Ascendente' : 'Descendente';
                                echo $campo_texto . ' (' . $orden_texto . ')';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($busqueda) || isset($_GET['paises']) || isset($_GET['asignados']) || isset($_GET['apellidos']) || isset($_GET['estados']) || isset($_GET['gestiones'])): ?>
                <div style="margin-top: 10px; padding: 10px; background-color: #e8f4fd; border-radius: 4px; border-left: 4px solid #3498db;">
                    <i class="fas fa-info-circle"></i> 
                    <?php 
                    $filtros_aplicados = [];
                    if (!empty($busqueda)) {
                        $filtros_aplicados[] = "Búsqueda: \"<strong>" . htmlspecialchars($busqueda) . "</strong>\"";
                        if ($es_busqueda_multiple) {
                            $filtros_aplicados[] = "(" . count($terminos_busqueda) . " término(s))";
                        }
                    }
                    if (isset($_GET['paises']) && !empty($_GET['paises'])) $filtros_aplicados[] = "Países: " . count($_GET['paises']);
                    if (isset($_GET['asignados']) && !empty($_GET['asignados'])) $filtros_aplicados[] = "Asignados: " . count($_GET['asignados']);
                    if (isset($_GET['apellidos']) && !empty($_GET['apellidos'])) $filtros_aplicados[] = "Apellidos: " . count($_GET['apellidos']);
                    if (isset($_GET['estados']) && !empty($_GET['estados'])) $filtros_aplicados[] = "Estados: " . count($_GET['estados']);
                    if (isset($_GET['gestiones']) && !empty($_GET['gestiones'])) $filtros_aplicados[] = "Gestión: " . count($_GET['gestiones']);
                    
                    if (!empty($filtros_aplicados)) {
                        echo "Filtros aplicados: " . implode(', ', $filtros_aplicados);
                    }
                    ?>
                    - <strong><?php echo $total_registros; ?></strong> cliente(s) encontrado(s)
                </div>
                <?php endif; ?>
            </div>

            <!-- Selector de Usuario -->
            <div style="padding: 20px; border-bottom: 1px solid #ecf0f1;">
                <div class="form-group">
                    <label for="selectUsuario" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-users"></i> Seleccionar Usuario:
                    </label>
                    <select id="selectUsuario" class="form-control" style="max-width: 300px;">
                        <option value="">-- Seleccione un usuario --</option>
                    </select>
                </div>
            </div>

            <!-- Contador de Seleccionados -->
            <div id="contadorSeleccionados" class="alert alert-info" style="margin: 15px 20px; display: none;">
                <i class="fas fa-check-circle"></i> <span id="textoSeleccionados">0 clientes seleccionados</span>
            </div>

            <!-- Tabla de Clientes -->
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>
                                TP
                                <?php if ($ordenar_por === 'TP'): ?>
                                    <i class="fas fa-sort-<?php echo $orden === 'ASC' ? 'up' : 'down'; ?>" style="color: #3498db; margin-left: 5px;"></i>
                                <?php endif; ?>
                            </th>
                            <th>
                                Nombre
                                <?php if ($ordenar_por === 'Nombre'): ?>
                                    <i class="fas fa-sort-<?php echo $orden === 'ASC' ? 'up' : 'down'; ?>" style="color: #3498db; margin-left: 5px;"></i>
                                <?php endif; ?>
                            </th>
                            <th>Apellido</th>
                            <th>
                                País
                                <?php if ($ordenar_por === 'Pais'): ?>
                                    <i class="fas fa-sort-<?php echo $orden === 'ASC' ? 'up' : 'down'; ?>" style="color: #3498db; margin-left: 5px;"></i>
                                <?php endif; ?>
                            </th>
                            <th>Asignado Actual</th>
                            <th>Estado</th>
                            <th>Última Gestión</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyClientes">
                        <?php
                        try {
                            // Consulta para obtener clientes con filtros
                            $query = "SELECT 
                                TP,
                                Nombre,
                                Apellido,
                                Pais,
                                Asignado,
                                Estado,
                                UltimaGestion
                            FROM clientes
                            $where_clause
                            ORDER BY " . htmlspecialchars($ordenar_por) . " " . htmlspecialchars($orden) . "
                            LIMIT :limit OFFSET :offset";
                            
                            $stmt = $db->prepare($query);
                            
                            // Bind parameters de filtros
                            foreach ($params as $key => $value) {
                                $stmt->bindValue($key, $value);
                            }
                            
                            // Bind parameters de paginación
                            $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            
                            $stmt->execute();
                            
                            if ($stmt->rowCount() > 0) {
                                while ($cliente = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td><input type='checkbox' class='cliente-checkbox' value='" . htmlspecialchars($cliente['TP']) . "'></td>";
                                    echo "<td><strong>" . htmlspecialchars($cliente['TP']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($cliente['Nombre']) . "</td>";
                                    echo "<td>" . htmlspecialchars($cliente['Apellido']) . "</td>";
                                    echo "<td>" . htmlspecialchars($cliente['Pais']) . "</td>";
                                    echo "<td>";
                                    if ($cliente['Asignado']) {
                                        echo "<span class='badge badge-success'>" . htmlspecialchars($cliente['Asignado']) . "</span>";
                                    } else {
                                        echo "<span class='badge badge-secondary'>Sin asignar</span>";
                                    }
                                    echo "</td>";
                                    echo "<td>";
                                    if (!empty($cliente['Estado'])) {
                                        echo "<span class='badge badge-info'>" . htmlspecialchars($cliente['Estado']) . "</span>";
                                    } else {
                                        echo "<span class='badge badge-secondary'>Sin estado</span>";
                                    }
                                    echo "</td>";
                                    echo "<td>";
                                    if (!empty($cliente['UltimaGestion'])) {
                                        echo "<span class='badge badge-warning'>" . htmlspecialchars($cliente['UltimaGestion']) . "</span>";
                                    } else {
                                        echo "<span class='badge badge-secondary'>Sin gestión</span>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align: center; padding: 20px;'>No hay clientes que coincidan con los filtros aplicados.</td></tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='8' style='text-align: center; padding: 20px; color: #e74c3c;'>Error al cargar clientes: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                
                <!-- PAGINACIÓN -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; padding: 0 20px 20px;">
                    <div style="color: #7f8c8d; font-size: 14px;">
                        Mostrando <?php echo min($registros_por_pagina, $total_registros - $offset); ?> de <?php echo $total_registros; ?> clientes
                    </div>
                    <div class="pagination">
                        <?php 
                        // Construir URL base con parámetros de filtros
                        $url_base = "asignar_individual.php?";
                        $params_url = [];
                        
                        // Agregar parámetros de filtros
                        if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
                            $params_url[] = "busqueda=" . urlencode($_GET['busqueda']);
                        }
                        
                        if (isset($_GET['campo_busqueda']) && !empty($_GET['campo_busqueda'])) {
                            $params_url[] = "campo_busqueda=" . urlencode($_GET['campo_busqueda']);
                        }
                        
                        $filter_params = ['paises', 'asignados', 'apellidos', 'estados', 'gestiones'];
                        foreach ($filter_params as $param) {
                            if (isset($_GET[$param]) && is_array($_GET[$param])) {
                                foreach ($_GET[$param] as $value) {
                                    $params_url[] = $param . "[]=" . urlencode($value);
                                }
                            }
                        }
                        
                        // Agregar registros por página
                        if ($registros_por_pagina != $registros_por_pagina_default) {
                            $params_url[] = "registros_por_pagina=" . urlencode($registros_por_pagina);
                        }
                        
                        // Agregar ordenamiento
                        $params_url[] = "ordenar_por=" . urlencode($ordenar_por);
                        $params_url[] = "orden=" . urlencode($orden);
                        
                        $url_base .= implode("&", $params_url);
                        if (!empty($params_url)) {
                            $url_base .= "&";
                        }
                        ?>
                        
                        <?php if ($pagina_actual_num > 1): ?>
                            <a href="<?php echo $url_base; ?>pagina=1" class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_actual_num - 1; ?>" class="pagination-btn">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $inicio = max(1, $pagina_actual_num - 2);
                        $fin = min($total_paginas, $pagina_actual_num + 2);
                        
                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $i; ?>" 
                               class="pagination-btn <?php echo $i == $pagina_actual_num ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual_num < $total_paginas): ?>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_actual_num + 1; ?>" class="pagination-btn">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Botón de Asignar -->
            <div style="padding: 20px; text-align: right; border-top: 1px solid #ecf0f1;">
                <button id="btnAsignar" class="btn btn-primary btn-lg" disabled>
                    <i class="fas fa-user-check"></i> Asignar Seleccionados
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px;"></div>

<!-- Estilos CSS -->
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
    position: relative;
    cursor: pointer;
    transition: background-color 0.3s;
}

.leads-table th:hover {
    background-color: #e9ecef;
}

.leads-table th i {
    font-size: 12px;
}

.leads-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #ecf0f1;
    vertical-align: middle;
}

/* Anchos específicos para cada columna */
.leads-table th:nth-child(1), .leads-table td:nth-child(1) { width: 50px; }
.leads-table th:nth-child(2), .leads-table td:nth-child(2) { width: 120px; }
.leads-table th:nth-child(3), .leads-table td:nth-child(3) { width: 150px; }
.leads-table th:nth-child(4), .leads-table td:nth-child(4) { width: 150px; }
.leads-table th:nth-child(5), .leads-table td:nth-child(5) { width: 100px; }
.leads-table th:nth-child(6), .leads-table td:nth-child(6) { width: 150px; }

.table-container {
    max-height: 600px;
    overflow-y: auto;
}

/* Badges */
.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success {
    background-color: #d4edda;
    color: #155724;
}

.badge-secondary {
    background-color: #e2e3e5;
    color: #383d41;
}

.badge-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
.badge-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

/* Checkboxes */
.cliente-checkbox {
    transform: scale(1.2);
    accent-color: #3498db;
}

#selectAll {
    transform: scale(1.2);
    accent-color: #3498db;
}

/* Botones */
.btn {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
}

.btn i {
    margin-right: 5px;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: #2980b9;
}

.btn-primary:disabled {
    background-color: #bdc3c7;
    cursor: not-allowed;
}

.btn-secondary {
    background-color: #ecf0f1;
    color: #2c3e50;
}

.btn-secondary:hover {
    background-color: #dde4e6;
}

.btn-lg {
    padding: 12px 25px;
    font-size: 16px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 3px;
}

/* Form controls */
.form-control {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 15px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

/* Alertas */
.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Paginación */
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

/* Estilos para filtros */
.filters-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-top: 15px;
}

.filter-column {
    background: white;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    padding: 15px;
}

.filter-title {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #2c3e50;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-options {
    max-height: 200px;
    overflow-y: auto;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    cursor: pointer;
    font-size: 13px;
    color: #5d6d7e;
    transition: color 0.3s ease;
}

.filter-option:hover {
    color: #2c3e50;
}

.filter-option input[type="checkbox"] {
    transform: scale(1.1);
    accent-color: #3498db;
}

.filter-section {
    margin-bottom: 15px;
}

.filter-actions {
    border-top: 1px solid #e1e8ed;
    padding-top: 15px;
}

/* Chips para términos de búsqueda */
.search-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}

.search-chip {
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.search-chip .remove-chip {
    cursor: pointer;
    font-size: 10px;
}

/* ============================================= */
/* NUEVO: ESTILOS PARA CONSULTAS GUARDADAS */
/* ============================================= */
.consultas-guardadas-card {
    background: #f8f9fa;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.consultas-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.consultas-header h3 {
    font-size: 15px;
    color: #2c3e50;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.consultas-header h3 i {
    color: #3498db;
}

.consultas-header small {
    color: #6c757d;
    font-size: 11px;
    font-weight: 500;
}

.consultas-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.consulta-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: white;
    border: 1px solid #e1e8ed;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.consulta-item:hover {
    border-color: #3498db;
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.1);
}

.consulta-info {
    flex: 1;
}

.consulta-info strong {
    display: block;
    font-size: 13px;
    color: #2c3e50;
    margin-bottom: 2px;
}

.consulta-info small {
    font-size: 11px;
    color: #6c757d;
}

.consulta-actions {
    display: flex;
    gap: 6px;
}

.btn-aplicar-consulta {
    padding: 4px 8px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 24px;
}

.btn-aplicar-consulta:hover {
    background: #2980b9;
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
    text-decoration: none;
}

.btn-xs:hover {
    background: #3498db;
    color: white;
    transform: translateY(-1px);
}

.btn-xs.btn-danger {
    border-color: #e74c3c;
    color: #e74c3c;
}

.btn-xs.btn-danger:hover {
    background: #e74c3c;
    color: white;
}

/* ============================================= */
/* NUEVO: ESTILOS PARA MODAL */
/* ============================================= */
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

/* Responsive */
@media (max-width: 1200px) {
    .filters-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-actions > * {
        margin-bottom: 10px;
    }
}

@media (max-width: 480px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// =============================================
// ASIGNAR INDIVIDUAL - FUNCIONES PRINCIPALES
// =============================================

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    cargarUsuarios();
    inicializarEventListeners();
    inicializarFiltros();
    inicializarBuscadorMultiple();
    inicializarFuncionalidadConsultas(); // NUEVO
});

// =============================================
// NUEVO: FUNCIONALIDAD PARA CONSULTAS GUARDADAS
// =============================================
function inicializarFuncionalidadConsultas() {
    // Variables para consultas guardadas
    const modalGuardarConsulta = document.getElementById('modalGuardarConsulta');
    const btnGuardarConsulta = document.getElementById('btnGuardarConsulta');
    const formGuardarConsulta = document.getElementById('formGuardarConsulta');
    const filtrosConsulta = document.getElementById('filtrosConsulta');
    const closeModalButtons = document.querySelectorAll('.close-modal');

    // Función para obtener todos los filtros actuales como JSON
    function obtenerFiltrosActuales() {
        const form = document.getElementById('filtersForm');
        const formData = new FormData(form);
        const filtros = {};
        
        // Obtener búsqueda y campo de búsqueda
        const busqueda = document.querySelector('input[name="busqueda"]').value;
        const campoBusqueda = document.querySelector('select[name="campo_busqueda"]').value;
        
        if (busqueda) filtros.busqueda = busqueda;
        if (campoBusqueda) filtros.campo_busqueda = campoBusqueda;
        
        // Obtener checkboxes de filtros
        ['paises', 'asignados', 'apellidos', 'estados', 'gestiones'].forEach(nombre => {
            const checkboxes = document.querySelectorAll(`[name="${nombre}[]"]:checked`);
            if (checkboxes.length > 0) {
                filtros[nombre] = Array.from(checkboxes).map(cb => cb.value);
            }
        });
        
        // Obtener registros por página
        const registrosPorPagina = document.querySelector('input[name="registros_por_pagina"]').value;
        if (registrosPorPagina) filtros.registros_por_pagina = registrosPorPagina;
        
        // Obtener ordenamiento
        const ordenarPor = document.querySelector('select[name="ordenar_por"]').value;
        const orden = document.querySelector('select[name="orden"]').value;
        if (ordenarPor) filtros.ordenar_por = ordenarPor;
        if (orden) filtros.orden = orden;
        
        // Obtener página actual (si existe)
        const urlParams = new URLSearchParams(window.location.search);
        const pagina = urlParams.get('pagina');
        if (pagina) filtros.pagina = pagina;
        
        return JSON.stringify(filtros);
    }

    // Mostrar modal para guardar consulta
    if (btnGuardarConsulta) {
        btnGuardarConsulta.addEventListener('click', function() {
            // Verificar que haya filtros seleccionados
            const filtros = obtenerFiltrosActuales();
            const filtrosObj = JSON.parse(filtros);
            
            // Verificar si hay filtros activos (excluyendo campos predeterminados)
            const filtrosActivos = Object.keys(filtrosObj).filter(key => 
                !['ordenar_por', 'orden', 'pagina'].includes(key) && 
                filtrosObj[key] && 
                (!Array.isArray(filtrosObj[key]) || filtrosObj[key].length > 0)
            );
            
            if (filtrosActivos.length === 0) {
                mostrarAlerta('Por favor, selecciona al menos un filtro antes de guardar la consulta.', 'error');
                return;
            }
            
            // Guardar filtros en el campo oculto
            filtrosConsulta.value = filtros;
            
            // Mostrar modal
            modalGuardarConsulta.style.display = 'flex';
            document.getElementById('nombreConsulta').focus();
        });
    }

    // Cerrar modal
    closeModalButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            modalGuardarConsulta.style.display = 'none';
        });
    });

    // Cerrar modal al hacer clic fuera
    if (modalGuardarConsulta) {
        modalGuardarConsulta.addEventListener('click', function(e) {
            if (e.target === modalGuardarConsulta) {
                modalGuardarConsulta.style.display = 'none';
            }
        });
    }

    // Aplicar consulta guardada
        document.querySelectorAll('.btn-aplicar-consulta').forEach(btn => {
            btn.addEventListener('click', function() {
                const consultaItem = this.closest('.consulta-item');
                const filtrosJSON = consultaItem.getAttribute('data-filtros');
                
                try {
                    const filtros = JSON.parse(filtrosJSON);
                    
                    // DEBUG: Mostrar filtros en consola
                    console.log('🔍 Filtros cargados:', filtros);
                    
                    // Crear un formulario temporal
                    const form = document.getElementById('filtersForm');
                    
                    // Limpiar TODOS los campos primero
                    form.reset();
                    
                    // Aplicar búsqueda si existe
                    if (filtros.busqueda) {
                        document.querySelector('input[name="busqueda"]').value = filtros.busqueda;
                    }
                    
                    // Aplicar campo de búsqueda
                    if (filtros.campo_busqueda) {
                        document.querySelector('select[name="campo_busqueda"]').value = filtros.campo_busqueda;
                    }
                    
                    // Aplicar checkboxes de filtros - MANERA CORREGIDA
                    ['paises', 'asignados', 'apellidos', 'estados', 'gestiones'].forEach(nombre => {
                        if (filtros[nombre] && Array.isArray(filtros[nombre])) {
                            console.log(`Aplicando ${nombre}:`, filtros[nombre]);
                            
                            filtros[nombre].forEach(valor => {
                                // Buscar checkbox por valor exacto
                                const checkboxes = document.querySelectorAll(`[name="${nombre}[]"]`);
                                checkboxes.forEach(checkbox => {
                                    if (checkbox.value === valor) {
                                        checkbox.checked = true;
                                        console.log(`✓ Marcado: ${checkbox.value}`);
                                    }
                                });
                            });
                        }
                    });
                    
                    // Aplicar registros por página
                    if (filtros.registros_por_pagina) {
                        document.querySelector('input[name="registros_por_pagina"]').value = filtros.registros_por_pagina;
                    }
                    
                    // Aplicar ordenamiento
                    if (filtros.ordenar_por) {
                        document.querySelector('select[name="ordenar_por"]').value = filtros.ordenar_por;
                    }
                    if (filtros.orden) {
                        document.querySelector('select[name="orden"]').value = filtros.orden;
                    }
                    
                    // Mostrar mensaje de éxito
                    mostrarAlerta('Consulta aplicada correctamente. Aplicando filtros...', 'success');
                    
                    // DEBUG: Verificar qué se marcó
                    setTimeout(() => {
                        console.log('✅ Filtros aplicados. Enviando formulario...');
                        
                        // Crear parámetros URL manualmente
                        const params = new URLSearchParams();
                        
                        // Agregar búsqueda
                        if (filtros.busqueda) params.append('busqueda', filtros.busqueda);
                        if (filtros.campo_busqueda) params.append('campo_busqueda', filtros.campo_busqueda);
                        
                        // Agregar arrays de filtros
                        ['paises', 'asignados', 'apellidos', 'estados', 'gestiones'].forEach(nombre => {
                            if (filtros[nombre] && Array.isArray(filtros[nombre])) {
                                filtros[nombre].forEach(valor => {
                                    params.append(`${nombre}[]`, valor);
                                });
                            }
                        });
                        
                        // Agregar otros parámetros
                        if (filtros.registros_por_pagina) params.append('registros_por_pagina', filtros.registros_por_pagina);
                        if (filtros.ordenar_por) params.append('ordenar_por', filtros.ordenar_por);
                        if (filtros.orden) params.append('orden', filtros.orden);
                        
                        // Redirigir directamente
                        const url = `asignar_individual.php?${params.toString()}`;
                        console.log('🔗 URL generada:', url);
                        window.location.href = url;
                        
                    }, 500);
                    
                } catch (error) {
                    console.error('❌ Error al aplicar consulta:', error);
                    mostrarAlerta('Error al aplicar la consulta guardada: ' + error.message, 'error');
                }
            });
        });
}

// Función para inicializar el buscador múltiple
function inicializarBuscadorMultiple() {
    const busquedaInput = document.getElementById('busquedaInput');
    const campoBusqueda = document.getElementById('campoBusqueda');
    
    // Actualizar placeholder según campo de búsqueda
    function actualizarPlaceholder() {
        const campo = campoBusqueda.value;
        let placeholder = '';
        
        switch(campo) {
            case 'tp':
                placeholder = "Ej: 12345, 67890, 54321... (separar TPs con comas)";
                break;
            case 'nombre':
                placeholder = "Ej: Juan, Maria, Carlos Pérez... (separar nombres con comas)";
                break;
            case 'asignado':
                placeholder = "Ej: Felipe, David, Ana... (separar asignados con comas)";
                break;
            case 'ambos':
                placeholder = "Ej: 12345, Juan, González... (separar TPs y nombres con comas)";
                break;
            case 'todos':
            default:
                placeholder = "Ej: 12345, Juan Pérez, cliente@email.com, 555-1234... (separar con comas)";
                break;
        }
        
        busquedaInput.placeholder = placeholder;
    }
    
    // Actualizar placeholder al cambiar la opción
    campoBusqueda.addEventListener('change', actualizarPlaceholder);
    
    // Inicializar placeholder
    actualizarPlaceholder();
    
    // Mostrar sugerencias al escribir
    busquedaInput.addEventListener('input', function() {
        const valor = this.value;
        if (valor.includes(',')) {
            mostrarTerminosBusqueda(valor);
        }
    });
}

// Función para mostrar los términos de búsqueda como chips
function mostrarTerminosBusqueda(valor) {
    const terminos = valor.split(',')
        .map(term => term.trim())
        .filter(term => term.length > 0);
    
    console.log('🔍 Términos de búsqueda detectados:', terminos);
}

// Función para inicializar los filtros
function inicializarFiltros() {
    // Función para limpiar filtros
    window.limpiarFiltros = function() {
        console.log('🧹 Limpiando filtros...');
        
        // Limpiar búsqueda
        document.querySelector('input[name="busqueda"]').value = '';
        
        // Limpiar todos los checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (checkbox.name !== 'registros_por_pagina') {
                checkbox.checked = false;
            }
        });
        
        // Resetear registros por página a 20 (valor por defecto)
        document.querySelector('input[name="registros_por_pagina"]').value = '20';
        
        // Enviar formulario
        document.getElementById('filtersForm').submit();
    };
}

// Función para recargar clientes
function recargarClientes() {
    location.reload();
}

// Función mejorada para cargar usuarios
function cargarUsuarios() {
    fetch('obtener_usuarios.php')
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                const select = document.getElementById('selectUsuario');
                
                if (data.success && data.usuarios && data.usuarios.length > 0) {
                    select.innerHTML = '<option value="">-- Seleccione un usuario --</option>';
                    
                    data.usuarios.forEach(usuario => {
                        const option = document.createElement('option');
                        const nombreMostrar = usuario.nombre || usuario.usuario;
                        option.value = nombreMostrar;
                        option.textContent = nombreMostrar;
                        option.setAttribute('data-id', usuario.id);
                        select.appendChild(option);
                    });
                } else {
                    throw new Error(data.error || 'No se pudieron cargar los usuarios');
                }
            } catch (e) {
                console.error('Error parseando JSON:', e);
                cargarUsuariosEjemplo();
            }
        })
        .catch(error => {
            console.error('Error cargando usuarios:', error);
            cargarUsuariosEjemplo();
        });
}

// Función de fallback con usuarios de ejemplo
function cargarUsuariosEjemplo() {
    const usuariosEjemplo = [
        { id: 1, nombre: 'Felipe Alvarez', usuario: 'falvarez' },
        { id: 2, nombre: 'David Perez', usuario: 'dperez' },
        { id: 3, nombre: 'Maria Gonzalez', usuario: 'mgonzalez' },
        { id: 4, nombre: 'Carlos Rodriguez', usuario: 'crodriguez' },
        { id: 5, nombre: 'Ana Martinez', usuario: 'amartinez' }
    ];
    
    const select = document.getElementById('selectUsuario');
    select.innerHTML = '<option value="">-- Seleccione un usuario --</option>';
    
    usuariosEjemplo.forEach(usuario => {
        const option = document.createElement('option');
        const nombreMostrar = usuario.nombre;
        option.value = nombreMostrar;
        option.textContent = nombreMostrar;
        option.setAttribute('data-id', usuario.id);
        select.appendChild(option);
    });
}

// Inicializar event listeners
function inicializarEventListeners() {
    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.cliente-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        actualizarEstadoBoton();
    });

    // Cambio en selector de usuario
    document.getElementById('selectUsuario').addEventListener('change', actualizarEstadoBoton);

    // Checkboxes individuales
    document.querySelectorAll('.cliente-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarEstadoBoton);
    });

    // Botón de asignar
    document.getElementById('btnAsignar').addEventListener('click', asignarClientes);
    
    // Ordenar al hacer clic en encabezados de columna
    inicializarOrdenamientoColumnas();
}

// Función para ordenar al hacer clic en encabezados
function inicializarOrdenamientoColumnas() {
    const thTP = document.querySelector('.leads-table th:nth-child(2)');
    const thNombre = document.querySelector('.leads-table th:nth-child(3)');
    const thPais = document.querySelector('.leads-table th:nth-child(5)');
    
    if (thTP) thTP.addEventListener('click', () => ordenarPorCampo('TP'));
    if (thNombre) thNombre.addEventListener('click', () => ordenarPorCampo('Nombre'));
    if (thPais) thPais.addEventListener('click', () => ordenarPorCampo('Pais'));
}

// Función para ordenar por campo específico
function ordenarPorCampo(campo) {
    const urlParams = new URLSearchParams(window.location.search);
    const ordenActual = urlParams.get('orden') || 'ASC';
    const campoActual = urlParams.get('ordenar_por') || 'Nombre';
    
    // Si ya está ordenando por este campo, cambiar la dirección
    let nuevoOrden = 'ASC';
    if (campoActual === campo) {
        nuevoOrden = ordenActual === 'ASC' ? 'DESC' : 'ASC';
    }
    
    // Actualizar parámetros
    urlParams.set('ordenar_por', campo);
    urlParams.set('orden', nuevoOrden);
    
    // Redirigir
    window.location.href = 'asignar_individual.php?' + urlParams.toString();
}

// Actualizar estado del botón y contador
function actualizarEstadoBoton() {
    const clientesSeleccionados = document.querySelectorAll('.cliente-checkbox:checked').length;
    const usuarioSeleccionado = document.getElementById('selectUsuario').value;
    const btnAsignar = document.getElementById('btnAsignar');
    const contador = document.getElementById('contadorSeleccionados');
    const textoSeleccionados = document.getElementById('textoSeleccionados');
    
    // Actualizar contador
    if (clientesSeleccionados > 0) {
        contador.style.display = 'block';
        textoSeleccionados.textContent = `${clientesSeleccionados} cliente${clientesSeleccionados !== 1 ? 's' : ''} seleccionado${clientesSeleccionados !== 1 ? 's' : ''}`;
    } else {
        contador.style.display = 'none';
    }
    
    // Actualizar select all si todos están seleccionados
    const totalCheckboxes = document.querySelectorAll('.cliente-checkbox').length;
    const checkboxesChecked = document.querySelectorAll('.cliente-checkbox:checked').length;
    document.getElementById('selectAll').checked = totalCheckboxes > 0 && checkboxesChecked === totalCheckboxes;
    
    // Habilitar/deshabilitar botón
    btnAsignar.disabled = !(clientesSeleccionados > 0 && usuarioSeleccionado);
}

// Función mejorada para asignar clientes
function asignarClientes() {
    const usuarioSeleccionado = document.getElementById('selectUsuario').value;
    const clientesSeleccionados = Array.from(document.querySelectorAll('.cliente-checkbox:checked'))
        .map(cb => cb.value);

    // Validaciones
    if (clientesSeleccionados.length === 0) {
        mostrarAlerta('Por favor seleccione al menos un cliente', 'error');
        return;
    }

    if (!usuarioSeleccionado) {
        mostrarAlerta('Por favor seleccione un usuario', 'error');
        return;
    }

    // Mostrar confirmación
    if (!confirm(`¿Está seguro de asignar ${clientesSeleccionados.length} cliente(s) a ${usuarioSeleccionado}?`)) {
        return;
    }

    // Deshabilitar botón durante el proceso
    const btnAsignar = document.getElementById('btnAsignar');
    const originalText = btnAsignar.innerHTML;
    btnAsignar.disabled = true;
    btnAsignar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asignando...';

    // Enviar al backend
    fetch('guardar_asignacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tp_ids: clientesSeleccionados,
            nuevo_asignado: usuarioSeleccionado
        })
    })
    .then(response => response.text())
    .then(text => {
        try {
            const resultado = JSON.parse(text);

            if (resultado.success) {
                mostrarAlerta(`✅ ${resultado.data.clientes_asignados} cliente(s) asignado(s) correctamente a ${usuarioSeleccionado}`, 'success');

                // Resetear selección
                document.getElementById('selectAll').checked = false;
                document.querySelectorAll('.cliente-checkbox').forEach(cb => cb.checked = false);
                actualizarEstadoBoton();

                // Recargar la página después de 2 segundos
                setTimeout(() => {
                    location.reload();
                }, 2000);

            } else {
                throw new Error(resultado.error || 'Error del servidor');
            }

        } catch (e) {
            console.error("Respuesta inválida del servidor:", text);
            mostrarAlerta("❌ El servidor devolvió una respuesta inválida. Revisa consola.", "error");
        }
    })
    .catch(error => {
        mostrarAlerta(`❌ Error al asignar clientes: ${error.message}`, 'error');
    })
    .finally(() => {
        btnAsignar.innerHTML = originalText;
        actualizarEstadoBoton();
    });
}

// =============================================
// FUNCIÓN DE ALERTA
// =============================================
function mostrarAlerta(mensaje, tipo = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const alertHTML = `
        <div id="${alertId}" class="alert alert-${tipo === 'error' ? 'danger' : tipo}" style="margin-bottom: 10px; padding: 12px 15px; border-radius: 4px; background-color: ${tipo === 'error' ? '#f8d7da' : tipo === 'success' ? '#d4edda' : '#d1ecf1'}; color: ${tipo === 'error' ? '#721c24' : tipo === 'success' ? '#155724' : '#0c5460'}; border: 1px solid ${tipo === 'error' ? '#f5c6cb' : tipo === 'success' ? '#c3e6cb' : '#bee5eb'};">
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
            alertElement.remove();
        }
    }, 5000);
}
</script>