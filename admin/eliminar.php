<?php
// eliminar.php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'eliminar';

// Obtener usuario actual
$usuario_actual = getCurrentUser();

// INCLUIR HEADER PRIMERO PARA TENER LA CLASE Database DISPONIBLE
include '../includes/header.php';
include '../includes/sidebar.php';

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

// Construir consulta con filtros
$where_conditions = [];
$params = [];

// Búsqueda básica MODIFICADA PARA INCLUIR TP
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $busqueda = '%' . $_GET['busqueda'] . '%';
    $where_conditions[] = "(TP LIKE :busqueda OR Nombre LIKE :busqueda OR Apellido LIKE :busqueda OR Correo LIKE :busqueda OR Numero LIKE :busqueda)";
    $params[':busqueda'] = $busqueda;
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

// Configuración de paginación
$registros_por_pagina_default = 20;
$registros_por_pagina = isset($_GET['registros_por_pagina']) ? (int)$_GET['registros_por_pagina'] : $registros_por_pagina_default;
$registros_por_pagina = min($registros_por_pagina, 2000);
$registros_por_pagina = max($registros_por_pagina, 1);

$pagina_actual_num = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual_num - 1) * $registros_por_pagina;

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
        <div class="page-title">Eliminar Clientes</div>
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
                    Eliminar Clientes
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

            <!-- SISTEMA DE FILTROS -->
            <div class="filters-container" style="padding: 20px; border-bottom: 1px solid #ecf0f1; background-color: #f8f9fa;">
                <form id="filtersForm" method="GET" action="eliminar.php">
                    <!-- Búsqueda Básica MODIFICADA -->
                    <div class="filter-section">
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 16px;">
                            <i class="fas fa-search"></i> BÚSQUEDA BÁSICA
                        </h3>
                        <div style="position: relative;">
                            <input type="text" 
                                   name="busqueda" 
                                   placeholder="Buscar por TP, nombre, teléfono o correo..."
                                   value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>"
                                   class="form-control"
                                   style="padding-left: 40px; width: 100%; max-width: 400px;">
                            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #7f8c8d;"></i>
                        </div>
                        <small style="color: #7f8c8d; font-size: 12px; margin-top: 5px; display: block;">
                            Busca en TP, nombre, teléfono y correo electrónico
                        </small>
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
                                   max="2000"
                                   style="width: 100px;">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Contador de Seleccionados -->
            <div id="contadorSeleccionados" class="alert alert-warning" style="margin: 15px 20px; display: none;">
                <i class="fas fa-exclamation-triangle"></i> <span id="textoSeleccionados">0 clientes seleccionados para eliminar</span>
            </div>

            <!-- Tabla de Clientes -->
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>TP</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>País</th>
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
                            ORDER BY Nombre 
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
                                    if ($cliente['Estado']) {
                                        echo "<span class='badge badge-info'>" . htmlspecialchars($cliente['Estado']) . "</span>";
                                    } else {
                                        echo "<span class='badge badge-secondary'>Sin estado</span>";
                                    }
                                    echo "</td>";
                                    echo "<td>";
                                    if ($cliente['UltimaGestion']) {
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
                        $url_base = "eliminar.php?";
                        $params_url = [];
                        
                        // Agregar parámetros de filtros
                        if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
                            $params_url[] = "busqueda=" . urlencode($_GET['busqueda']);
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

            <!-- Botón de Eliminar -->
            <div style="padding: 20px; text-align: right; border-top: 1px solid #ecf0f1;">
                <button id="btnEliminar" class="btn btn-danger btn-lg" disabled>
                    <i class="fas fa-trash-alt"></i> Eliminar Seleccionados
                </button>
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
.leads-table th:nth-child(2), .leads-table td:nth-child(2) { width: 120px; }
.leads-table th:nth-child(3), .leads-table td:nth-child(3) { width: 150px; }
.leads-table th:nth-child(4), .leads-table td:nth-child(4) { width: 150px; }
.leads-table th:nth-child(5), .leads-table td:nth-child(5) { width: 100px; }
.leads-table th:nth-child(6), .leads-table td:nth-child(6) { width: 150px; }
.leads-table th:nth-child(7), .leads-table td:nth-child(7) { width: 120px; }
.leads-table th:nth-child(8), .leads-table td:nth-child(8) { width: 150px; }

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
}

.badge-warning {
    background-color: #fff3cd;
    color: #856404;
}

/* Checkboxes */
.cliente-checkbox {
    transform: scale(1.2);
    accent-color: #e74c3c;
}

#selectAll {
    transform: scale(1.2);
    accent-color: #e74c3c;
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

.btn-danger {
    background-color: #e74c3c;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background-color: #c0392b;
}

.btn-danger:disabled {
    background-color: #f5b7b1;
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

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
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
// ELIMINAR CLIENTES - FUNCIONES PRINCIPALES CON DEBUG
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
        const checkboxes = document.querySelectorAll('.cliente-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        actualizarEstadoBoton();
    });

    // Checkboxes individuales
    document.querySelectorAll('.cliente-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('🔘 Checkbox cambiado:', this.value, this.checked);
            actualizarEstadoBoton();
        });
    });

    // Botón de eliminar
    document.getElementById('btnEliminar').addEventListener('click', eliminarClientes);
    
    console.log('✅ Event listeners configurados correctamente');
}

// Actualizar estado del botón y contador
function actualizarEstadoBoton() {
    const clientesSeleccionados = document.querySelectorAll('.cliente-checkbox:checked').length;
    const btnEliminar = document.getElementById('btnEliminar');
    const contador = document.getElementById('contadorSeleccionados');
    const textoSeleccionados = document.getElementById('textoSeleccionados');
    
    console.log('🔄 Actualizando estado - Seleccionados:', clientesSeleccionados);
    
    // Actualizar contador
    if (clientesSeleccionados > 0) {
        contador.style.display = 'block';
        textoSeleccionados.textContent = `${clientesSeleccionados} cliente${clientesSeleccionados !== 1 ? 's' : ''} seleccionado${clientesSeleccionados !== 1 ? 's' : ''} para eliminar`;
    } else {
        contador.style.display = 'none';
    }
    
    // Actualizar select all si todos están seleccionados
    const totalCheckboxes = document.querySelectorAll('.cliente-checkbox').length;
    const checkboxesChecked = document.querySelectorAll('.cliente-checkbox:checked').length;
    document.getElementById('selectAll').checked = totalCheckboxes > 0 && checkboxesChecked === totalCheckboxes;
    
    // Habilitar/deshabilitar botón
    btnEliminar.disabled = !(clientesSeleccionados > 0);
    
    console.log('✅ Estado actualizado - Botón habilitado:', !btnEliminar.disabled);
}

// Función para limpiar filtros
function limpiarFiltros() {
    console.log('🧹 Limpiando filtros...');
    
    // Limpiar búsqueda
    document.querySelector('input[name="busqueda"]').value = '';
    
    // Limpiar todos los checkboxes
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.name !== 'registros_por_pagina') {
            checkbox.checked = false;
        }
    });
    
    // Resetear registros por página
    document.querySelector('input[name="registros_por_pagina"]').value = '20';
    
    // Enviar formulario
    document.getElementById('filtersForm').submit();
}

// Función para eliminar clientes
function eliminarClientes() {
    const clientesSeleccionados = Array.from(document.querySelectorAll('.cliente-checkbox:checked'))
        .map(cb => cb.value);

    console.log('🔍 DEBUG - Clientes seleccionados:', clientesSeleccionados);
    console.log('🔍 DEBUG - Cantidad de clientes:', clientesSeleccionados.length);

    // Validaciones
    if (clientesSeleccionados.length === 0) {
        console.warn('⚠️ Validación fallida: No hay clientes seleccionados');
        mostrarAlerta('Por favor seleccione al menos un cliente para eliminar', 'error');
        return;
    }

    // Mostrar confirmación
    const confirmacion = confirm(`⚠️ ¿ESTÁ SEGURO DE ELIMINAR ${clientesSeleccionados.length} CLIENTE(S)?\n\nEsta acción es irreversible y no se puede deshacer.`);
    
    if (!confirmacion) {
        console.log('❌ Usuario canceló la eliminación');
        return;
    }

    console.log('✅ Usuario confirmó la eliminación');

    // Deshabilitar botón durante el proceso
    const btnEliminar = document.getElementById('btnEliminar');
    const originalText = btnEliminar.innerHTML;
    btnEliminar.disabled = true;
    btnEliminar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

    console.log('📤 DEBUG - Enviando datos para eliminar:', {
        tp_ids: clientesSeleccionados,
        cantidad: clientesSeleccionados.length
    });

    // Enviar al backend
    console.log('🌐 DEBUG - Iniciando fetch a guardar_eliminacion.php');
    
    fetch('guardar_eliminacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tp_ids: clientesSeleccionados
        })
    })
    .then(response => {
        console.log('📡 DEBUG - Respuesta HTTP recibida:');
        console.log('   Status:', response.status, response.statusText);
        console.log('   URL:', response.url);
        console.log('   OK:', response.ok);
        
        return response.text().then(text => {
            console.log('📄 DEBUG - Contenido crudo de la respuesta:');
            console.log('   Longitud:', text.length);
            console.log('   Primeros 200 caracteres:', text.substring(0, 200));
            
            if (text.trim().startsWith('<!DOCTYPE') || text.includes('<html') || text.includes('<body') || text.trim().startsWith('<br')) {
                console.error('❌ DEBUG - El servidor devolvió HTML en lugar de JSON');
                throw new Error('El servidor devolvió una página HTML. Posible error PHP.');
            }
            
            try {
                console.log('🔧 DEBUG - Intentando parsear como JSON...');
                const data = JSON.parse(text);
                console.log('✅ DEBUG - JSON parseado correctamente:', data);
                return data;
            } catch (e) {
                console.error('❌ DEBUG - No es JSON válido');
                throw new Error('El servidor no devolvió JSON válido. Error: ' + e.message);
            }
        });
    })
    .then(resultado => {
        console.log('✅ DEBUG - Respuesta JSON del servidor procesada:');
        console.log('   Success:', resultado.success);
        console.log('   Datos:', resultado.data);
        console.log('   Errores:', resultado.error);
        console.log('   Advertencias:', resultado.advertencias);
        
        if (resultado.success) {
            console.log('🎉 DEBUG - Eliminación exitosa');
            mostrarAlerta(`✅ ${resultado.data.clientes_eliminados} cliente(s) eliminado(s) correctamente`, 'success');
            
            // Resetear selección
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.cliente-checkbox').forEach(cb => cb.checked = false);
            actualizarEstadoBoton();
            
            console.log('🔄 DEBUG - Recargando página en 2 segundos...');
            
            // Recargar la página para ver los cambios después de 2 segundos
            setTimeout(() => {
                console.log('🔄 DEBUG - Recargando página ahora...');
                location.reload();
            }, 2000);
        } else {
            console.error('❌ DEBUG - Error del servidor:', resultado.error);
            throw new Error(resultado.error || 'Error desconocido del servidor');
        }
    })
    .catch(error => {
        console.error('❌ DEBUG - Error en el proceso de eliminación:');
        console.error('   Mensaje:', error.message);
        console.error('   Stack:', error.stack);
        mostrarAlerta(`❌ Error al eliminar clientes: ${error.message}`, 'error');
    })
    .finally(() => {
        // Restaurar botón
        console.log('🔧 DEBUG - Restaurando estado del botón');
        btnEliminar.innerHTML = originalText;
        actualizarEstadoBoton();
    });
}

// Función para recargar clientes
function recargarClientes() {
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