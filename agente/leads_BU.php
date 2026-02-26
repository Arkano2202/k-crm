<?php
// Siempre al inicio
include '../includes/session.php';
requireLogin(); // Si requiere autenticación

$pagina_actual = 'leads';

// Obtener usuario actual con todos los campos
$usuario_actual = getCurrentUser();
$usuario_session = $usuario_actual['nombre'] ?? 'Sistema';

// Incluir el header
include '../includes/header.php';
include '../includes/sidebar.php';

// Al inicio de leads.php, después de las includes
$filterTP = $_GET['filter'] ?? '';
$tpValue = $_GET['value'] ?? '';

// Si hay filtro por TP, aplicar automáticamente
if ($filterTP === 'tp' && !empty($tpValue)) {
    // Aquí aplicas el filtro a tu consulta SQL
    // Por ejemplo:
    $query = "SELECT * FROM leads WHERE tp = :tpValue";
    // ... resto de tu código de filtrado
}

// PARÁMETROS DE ORDENAMIENTO - NUEVO
$orden = $_GET['orden'] ?? 'Nombre'; // Campo por defecto
$direccion = $_GET['direccion'] ?? 'asc'; // Dirección por defecto

// Validar campo de orden
$camposPermitidos = ['TP', 'Nombre', 'Apellido', 'Estado', 'Pais', 'UltimaGestion', 'FechaUltimaGestion'];
if (!in_array($orden, $camposPermitidos)) {
    $orden = 'Nombre';
}

// Validar dirección
$direccion = strtolower($direccion);
if ($direccion !== 'asc' && $direccion !== 'desc') {
    $direccion = 'asc';
}

// Configuración de paginación y búsqueda - MODIFICADO
$registros_por_pagina = isset($_GET['registros_por_pagina']) ? (int)$_GET['registros_por_pagina'] : 20;

// Validar que sea un número positivo
if ($registros_por_pagina <= 0) {
    $registros_por_pagina = 20;
}

// Limitar máximo para evitar sobrecarga (opcional)
$max_registros = 1000; // Puedes ajustar este valor
if ($registros_por_pagina > $max_registros) {
    $registros_por_pagina = $max_registros;
}

$pagina_actual_num = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// PARÁMETROS DE BÚSQUEDA MEJORADOS
$search_term = $_GET['search'] ?? '';
$search_field = $_GET['search_field'] ?? 'all'; // Campo específico o 'all' para todos

$offset = ($pagina_actual_num - 1) * $registros_por_pagina;

// Función para procesar búsquedas múltiples
function procesarBusquedaMultiple($busqueda, $campo_busqueda) {
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
$terminos_busqueda = procesarBusquedaMultiple($search_term, $search_field);
$es_busqueda_multiple = count($terminos_busqueda) > 1;

// Construir consultas basadas en búsqueda
$where_conditions = [];
$params = [];

// FILTRO POR USUARIO DE SESIÓN - NUEVO
$where_conditions[] = "c.Asignado = :usuario_session";
$params[':usuario_session'] = $usuario_actual['nombre'];

// BÚSQUEDA MEJORADA - MÚLTIPLES CAMPOS
if (!empty($terminos_busqueda)) {
    $search_conditions = [];
    
    if ($search_field === 'all' || $search_field === 'tp') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para TP
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_tp_$index";
                $placeholders[] = "c.TP LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para TP
            $search_conditions[] = "c.TP LIKE :search_tp";
            $params[':search_tp'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'nombre') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para nombre/apellido
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_nombre = ":search_nombre_$index";
                $param_apellido = ":search_apellido_$index";
                $placeholders[] = "(c.Nombre LIKE $param_nombre OR c.Apellido LIKE $param_apellido)";
                $params[$param_nombre] = "%$termino%";
                $params[$param_apellido] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para nombre/apellido
            $search_conditions[] = "(c.Nombre LIKE :search_nombre OR c.Apellido LIKE :search_apellido)";
            $params[':search_nombre'] = "%{$terminos_busqueda[0]}%";
            $params[':search_apellido'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'correo') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para correo
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_correo_$index";
                $placeholders[] = "c.Correo LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para correo
            $search_conditions[] = "c.Correo LIKE :search_correo";
            $params[':search_correo'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'numero') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para teléfono
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_numero_$index";
                $placeholders[] = "c.Numero LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para teléfono
            $search_conditions[] = "c.Numero LIKE :search_numero";
            $params[':search_numero'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'pais') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para país
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_pais_$index";
                $placeholders[] = "c.Pais LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para país
            $search_conditions[] = "c.Pais LIKE :search_pais";
            $params[':search_pais'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'asignado') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para asignado
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_asignado_$index";
                $placeholders[] = "c.Asignado LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para asignado
            $search_conditions[] = "c.Asignado LIKE :search_asignado";
            $params[':search_asignado'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if ($search_field === 'all' || $search_field === 'gestion') {
        if ($es_busqueda_multiple) {
            // Búsqueda múltiple para gestión
            $placeholders = [];
            foreach ($terminos_busqueda as $index => $termino) {
                $param_name = ":search_gestion_$index";
                $placeholders[] = "c.UltimaGestion LIKE $param_name";
                $params[$param_name] = "%$termino%";
            }
            $search_conditions[] = "(" . implode(" OR ", $placeholders) . ")";
        } else {
            // Búsqueda individual para gestión
            $search_conditions[] = "c.UltimaGestion LIKE :search_gestion";
            $params[':search_gestion'] = "%{$terminos_busqueda[0]}%";
        }
    }
    
    if (!empty($search_conditions)) {
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
    }
}

// FILTROS ADICIONALES DESDE URL (MANTENIDOS)
$filtros_aplicados = [];

// Filtro por países
if (!empty($_GET['paises'])) {
    $paises = explode(',', $_GET['paises']);
    $placeholders = [];
    foreach ($paises as $i => $pais) {
        $key = ":pais_$i";
        $placeholders[] = $key;
        $params[$key] = $pais;
    }
    $where_conditions[] = "c.Pais IN (" . implode(',', $placeholders) . ")";
    $filtros_aplicados['paises'] = count($paises);
}

// Filtro por apellidos
if (!empty($_GET['apellidos'])) {
    $apellidos = explode(',', $_GET['apellidos']);
    $placeholders = [];
    foreach ($apellidos as $i => $apellido) {
        $key = ":apellido_$i";
        $placeholders[] = $key;
        $params[$key] = $apellido;
    }
    $where_conditions[] = "c.Apellido IN (" . implode(',', $placeholders) . ")";
    $filtros_aplicados['apellidos'] = count($apellidos);
}

// Filtro por asignados
if (!empty($_GET['asignados'])) {
    $asignados = explode(',', $_GET['asignados']);
    $placeholders = [];
    foreach ($asignados as $i => $asignado) {
        $key = ":asignado_$i";
        $placeholders[] = $key;
        $params[$key] = $asignado;
    }
    $where_conditions[] = "c.Asignado IN (" . implode(',', $placeholders) . ")";
    $filtros_aplicados['asignados'] = count($asignados);
}

// Filtro por gestiones
if (!empty($_GET['gestiones'])) {
    $gestiones = explode(',', $_GET['gestiones']);
    $placeholders = [];
    foreach ($gestiones as $i => $gestion) {
        $key = ":gestion_$i";
        $placeholders[] = $key;
        
        // Si es "Sin gestión", buscar valores nulos o vacíos
        if ($gestion === 'Sin gestión') {
            $where_conditions[] = "(c.UltimaGestion IS NULL OR c.UltimaGestion = '')";
            unset($placeholders[count($placeholders)-1]); // Remover el placeholder
        } else {
            $params[$key] = $gestion;
        }
    }
    
    // Solo agregar la condición IN si hay placeholders
    if (!empty($placeholders)) {
        $where_conditions[] = "c.UltimaGestion IN (" . implode(',', $placeholders) . ")";
    }
    $filtros_aplicados['gestiones'] = count($gestiones);
}

// Filtro por estados
if (!empty($_GET['estados'])) {
    $estados = explode(',', $_GET['estados']);
    $placeholders = [];
    foreach ($estados as $i => $estado) {
        $key = ":estado_$i";
        $placeholders[] = $key;
        $params[$key] = $estado;
    }
    $where_conditions[] = "c.Estado IN (" . implode(',', $placeholders) . ")";
    $filtros_aplicados['estados'] = count($estados);
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Mostrar mensaje de filtros aplicados
$mensaje_filtros = '';
if (isset($_GET['filtrado']) && $_GET['filtrado'] === 'true') {
    $total_filtros = array_sum($filtros_aplicados);
    if ($total_filtros > 0 || !empty($search_term)) {
        $mensaje_filtros = "Filtros activos";
        if (!empty($search_term)) {
            $mensaje_filtros .= " - Búsqueda: \"$search_term\"";
        }
        if ($total_filtros > 0) {
            $mensaje_filtros .= " - $total_filtros criterios adicionales";
        }
    }
}

// Consulta optimizada para el total de registros
try {
    $query_total = "SELECT COUNT(*) as total FROM clientes c $where_clause";
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
        <div class="page-title">Leads / Clientes</div>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <form method="GET" action="leads.php" style="display: flex; align-items: center; width: 100%; gap: 10px;">
                <select name="search_field" style="border: none; background: transparent; outline: none; font-size: 14px; min-width: 150px;">
                    <option value="all" <?php echo ($search_field === 'all') ? 'selected' : ''; ?>>Todos los campos</option>
                    <option value="tp" <?php echo ($search_field === 'tp') ? 'selected' : ''; ?>>Solo TP</option>
                    <option value="nombre" <?php echo ($search_field === 'nombre') ? 'selected' : ''; ?>>Solo Nombre/Apellido</option>
                    <option value="correo" <?php echo ($search_field === 'correo') ? 'selected' : ''; ?>>Solo Email</option>
                    <option value="numero" <?php echo ($search_field === 'numero') ? 'selected' : ''; ?>>Solo Teléfono</option>
                    <option value="pais" <?php echo ($search_field === 'pais') ? 'selected' : ''; ?>>Solo País</option>
                    <option value="asignado" <?php echo ($search_field === 'asignado') ? 'selected' : ''; ?>>Solo Asignado</option>
                    <option value="gestion" <?php echo ($search_field === 'gestion') ? 'selected' : ''; ?>>Solo Gestión</option>
                </select>
                <input type="text" name="search" placeholder="Buscar por TP, nombre, email, teléfono... (Separar múltiples con comas)" 
                       value="<?php echo htmlspecialchars($search_term); ?>" 
                       style="border: none; background: transparent; outline: none; width: 100%; font-size: 14px;">
                <input type="hidden" name="pagina" value="1">
                
                <?php if (!empty($search_term)): ?>
                    <a href="leads.php" style="margin-left: 10px; color: #e74c3c;" title="Limpiar búsqueda">
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
        <!-- Barra de herramientas -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    Lista de Clientes 
                    <span style="font-size: 14px; color: #7f8c8d; margin-left: 10px;">
                        (Total: <?php echo $total_registros; ?> registros)
                    </span>
                    <?php if (!empty($mensaje_filtros)): ?>
                        <span style="font-size: 12px; color: #e74c3c; margin-left: 10px; font-weight: bold;">
                            <?php echo $mensaje_filtros; ?>
                        </span>
                    <?php endif; ?>
                    
                    <!-- NUEVO: Indicador de ordenamiento actual -->
                    <span style="font-size: 12px; color: #3498db; margin-left: 10px; font-weight: 500; background: #e8f4fd; padding: 2px 8px; border-radius: 3px;">
                        <i class="fas fa-sort-amount-down"></i>
                        Ordenado por: <?php echo $orden; ?> 
                        (<?php echo $direccion === 'asc' ? 'Ascendente' : 'Descendente'; ?>)
                    </span>
                </div>
                <div class="card-actions">
                    <!-- Botón para quitar selección de todos -->
                    <button class="btn btn-outline" id="deseleccionarTodos" style="margin-right: 10px;">
                        <i class="fas fa-times-circle"></i> Quitar Todos
                    </button>
                    
                    <!-- GRUPO UNIFICADO: Registros por página + Ordenamiento -->
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <!-- Grupo: Registros por página -->
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label style="font-size: 14px; color: #7f8c8d; white-space: nowrap;">
                                Mostrar:
                            </label>
                            <input type="number" id="registrosPorPagina" 
                                   value="<?php echo $registros_por_pagina; ?>" 
                                   min="1" max="1000" 
                                   style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 80px; text-align: center;"
                                   placeholder="Cantidad">
                        </div>
                        
                        <!-- Grupo: Ordenamiento -->
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label style="font-size: 14px; color: #7f8c8d; white-space: nowrap;">
                                Ordenar por:
                            </label>
                            <select id="ordenCampo" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 150px;">
                                <option value="Nombre" <?php echo ($orden === 'Nombre') ? 'selected' : ''; ?>>Nombre</option>
                                <option value="Apellido" <?php echo ($orden === 'Apellido') ? 'selected' : ''; ?>>Apellido</option>
                                <option value="TP" <?php echo ($orden === 'TP') ? 'selected' : ''; ?>>TP</option>
                                <option value="Estado" <?php echo ($orden === 'Estado') ? 'selected' : ''; ?>>Estado</option>
                                <option value="Pais" <?php echo ($orden === 'Pais') ? 'selected' : ''; ?>>País</option>
                                <option value="UltimaGestion" <?php echo ($orden === 'UltimaGestion') ? 'selected' : ''; ?>>Última Gestión</option>
                                <option value="FechaUltimaGestion" <?php echo ($orden === 'FechaUltimaGestion') ? 'selected' : ''; ?>>Fecha Última Gestión</option>
                            </select>
                            
                            <select id="ordenDireccion" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 120px;">
                                <option value="asc" <?php echo ($direccion === 'asc') ? 'selected' : ''; ?>>Ascendente</option>
                                <option value="desc" <?php echo ($direccion === 'desc') ? 'selected' : ''; ?>>Descendente</option>
                            </select>
                        </div>
                        
                        <!-- Botón para aplicar ambos -->
                        <button type="button" onclick="aplicarConfiguracion()" 
                                style="padding: 6px 15px; border: 1px solid #2ecc71; border-radius: 4px; background: #2ecc71; color: white; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-check"></i> Aplicar
                        </button>
                    </div>
                    
                    <button class="btn btn-secondary" style="margin-right: 10px;" id="filterBtn">
                        <i class="fas fa-filter"></i> 
                        <?php echo (isset($_GET['filtrado']) && $_GET['filtrado'] === 'true') ? 'Filtros Activos' : 'Filtrar'; ?>
                    </button>
                    <button class="btn btn-primary" id="newClientBtn">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </button>
                </div>
            </div>

            <!-- Información de Búsqueda Mejorada -->
            <?php if (!empty($search_term)): ?>
            <div style="margin: 10px 20px; padding: 10px; background-color: #e8f4fd; border-radius: 4px; border-left: 4px solid #3498db;">
                <i class="fas fa-search"></i> 
                <?php if ($es_busqueda_multiple): ?>
                    Búsqueda múltiple: 
                    <?php foreach ($terminos_busqueda as $index => $termino): ?>
                        "<strong><?php echo htmlspecialchars($termino); ?></strong>"<?php echo $index < count($terminos_busqueda) - 1 ? ',' : ''; ?>
                    <?php endforeach; ?>
                    (<?php echo count($terminos_busqueda); ?> términos)
                <?php else: ?>
                    Búsqueda: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                <?php endif; ?>
                
                <?php if ($search_field === 'tp'): ?>
                    (solo en TP)
                <?php elseif ($search_field === 'nombre'): ?>
                    (solo en Nombre/Apellido)
                <?php elseif ($search_field === 'correo'): ?>
                    (solo en Email)
                <?php elseif ($search_field === 'numero'): ?>
                    (solo en Teléfono)
                <?php elseif ($search_field === 'pais'): ?>
                    (solo en País)
                <?php elseif ($search_field === 'asignado'): ?>
                    (solo en Asignado)
                <?php elseif ($search_field === 'gestion'): ?>
                    (solo en Gestión)
                <?php else: ?>
                    (en todos los campos)
                <?php endif; ?>
                - <strong><?php echo $total_registros; ?></strong> cliente(s) encontrado(s)
            </div>
            <?php endif; ?>

            <!-- Tabla de clientes -->
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="seleccionarTodos" title="Seleccionar todos">
                            </th>
                            <th>TP</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Estado</th>
                            <th>País</th>
                            <th>Última Gestión</th>
                            <th>Fecha Última Gestión</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // CONSULTA OPTIMIZADA con búsqueda y ordenamiento
                            $query = "SELECT 
                                c.TP,
                                c.Nombre,
                                c.Apellido,
                                c.Numero,
                                c.Correo,
                                c.Estado,
                                c.Pais,
                                c.Asignado,
                                c.UltimaGestion,
                                c.FechaUltimaGestion
                            FROM clientes c
                            $where_clause
                            ORDER BY c.$orden $direccion
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
                                while ($cliente = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr data-tp='" . htmlspecialchars($cliente['TP'] ?? '') . "'>";
                                    echo "<td style='text-align: center;'>";
                                    echo "<input type='checkbox' class='seleccionar-lead' data-tp='" . htmlspecialchars($cliente['TP'] ?? '') . "'>";
                                    echo "</td>";
                                    echo "<td><strong>" . htmlspecialchars($cliente['TP'] ?? '') . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($cliente['Nombre'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($cliente['Apellido'] ?? '') . "</td>";
                                    
                                    echo "<td><span class='status-badge status-" . htmlspecialchars($cliente['Estado'] ?? 'activo') . "'>" . ucfirst($cliente['Estado'] ?? 'Activo') . "</span></td>";
                                    echo "<td>" . htmlspecialchars($cliente['Pais'] ?? '') . "</td>";
                                    
                                    // Mostrar última gestión desde la tabla clientes
                                    $ultima_gestion = $cliente['UltimaGestion'] ?? '';
                                    echo "<td>" . htmlspecialchars($ultima_gestion ?: 'Sin gestión registrada') . "</td>";
                                    echo "<td>" . htmlspecialchars($cliente['FechaUltimaGestion'] ?? 'N/A') . "</td>";
                                    
                                    echo "<td>
                                            <div class='action-buttons'>
                                                <button class='btn-action btn-view' title='Ver detalles' onclick='viewClient(\"" . htmlspecialchars($cliente['TP'] ?? '') . "\")'>
                                                    <i class='fas fa-eye'></i>
                                                </button>
                                                <button class='btn-action btn-call' title='Llamar' onclick='makeCall(\"" . htmlspecialchars($cliente['Numero'] ?? '') . "\", \"" . htmlspecialchars($usuario_actual['ext'] ?? '') . "\")'>
                                                    <i class='fas fa-phone'></i>
                                                </button>
                                                <button class='btn-action btn-note' title='Agregar nota' onclick='addNote(\"" . htmlspecialchars($cliente['TP'] ?? '') . "\")'>
                                                    <i class='fas fa-sticky-note'></i>
                                                </button>
                                                <!-- NUEVO BOTÓN ASIGNAR CITA -->
                                                <button class='btn-action btn-cita' title='Asignar Cita' onclick='asignarCita(\"" . htmlspecialchars($cliente['TP'] ?? '') . "\", \"" . htmlspecialchars($cliente['Nombre'] ?? '') . "\")'>
                                                    <i class='fas fa-calendar-plus'></i>
                                                </button>
                                            </div>
                                        </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align: center; padding: 20px;'>";
                                if (!empty($search_term) || !empty($filtros_aplicados)) {
                                    echo "No se encontraron clientes que coincidan con los filtros aplicados.";
                                    if (!empty($search_term)) {
                                        echo "<br>Búsqueda: '<strong>" . htmlspecialchars($search_term) . "</strong>'";
                                    }
                                } else {
                                    echo "No hay clientes registrados para tu usuario.";
                                }
                                echo "</td></tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='9' style='text-align: center; padding: 20px; color: #e74c3c;'>";
                            echo "Error al cargar clientes: " . $e->getMessage();
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
                        (<?php echo $registros_por_pagina; ?> por página)
                        <?php if (!empty($search_term)): ?>
                            <br><span style="color: #3498db;">
                                <?php if ($es_busqueda_multiple): ?>
                                    Buscando múltiples términos
                                <?php else: ?>
                                    Buscando: "<?php echo htmlspecialchars($search_term); ?>"
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="pagination">
                        <?php 
                        // Construir URL base con parámetros de búsqueda
                        $url_params = $_GET;
                        unset($url_params['pagina']); // Remover página actual
                        ?>
                        
                        <?php if ($pagina_actual_num > 1): ?>
                            <a href="leads.php?<?php echo http_build_query(array_merge($url_params, ['pagina' => 1])); ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="leads.php?<?php echo http_build_query(array_merge($url_params, ['pagina' => $pagina_actual_num - 1])); ?>" class="pagination-btn">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar números de página
                        $inicio = max(1, $pagina_actual_num - 2);
                        $fin = min($total_paginas, $pagina_actual_num + 2);
                        
                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <a href="leads.php?<?php echo http_build_query(array_merge($url_params, ['pagina' => $i])); ?>" 
                               class="pagination-btn <?php echo $i == $pagina_actual_num ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual_num < $total_paginas): ?>
                            <a href="leads.php?<?php echo http_build_query(array_merge($url_params, ['pagina' => $pagina_actual_num + 1])); ?>" class="pagination-btn">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="leads.php?<?php echo http_build_query(array_merge($url_params, ['pagina' => $total_paginas])); ?>" class="pagination-btn">
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

<!-- Modal para ver detalles del cliente -->
<div class="modal" id="modalVerCliente">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh;">
        <div class="modal-header">
            <h2>Detalles del Cliente</h2>
            <button class="close-btn" id="closeModalVer">&times;</button>
        </div>
        <div class="modal-body" id="modalClienteContent" style="padding: 20px; overflow-y: auto;">
            <!-- Contenido se cargará dinámicamente -->
            <div id="loadingCliente" style="text-align: center; padding: 40px;">
                <div style="color: #3498db; font-size: 18px; margin-bottom: 10px;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <p>Cargando información del cliente...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCerrarVer">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal para Agregar Nota -->
<div class="modal" id="modalAgregarNota">
    <div class="modal-content modal-notas">
        <div class="modal-header">
            <h2>Agregar Nota</h2>
            <button class="close-btn" id="closeModalNota">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formAgregarNota">
                <div class="form-group">
                    <div class="info-group">
                        <div class="info-label">Cliente</div>
                        <div class="info-value" id="clienteNombreNota" style="font-size: 16px; font-weight: bold;"></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">TP</div>
                        <div class="info-value" id="clienteTPNota" style="font-weight: bold; color: #3498db;"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gestionSelect" class="form-label">Tipo de Gestión *</label>
                    <select id="gestionSelect" name="gestion" class="form-control" required>
                        <option value="">Seleccionar gestión...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notaDescripcion" class="form-label">Descripción de la Nota *</label>
                    <textarea id="notaDescripcion" name="descripcion" class="form-control" rows="6" 
                              placeholder="Escribe los detalles de la gestión..." required></textarea>
                </div>
                
                <input type="hidden" id="clienteTPHidden" name="tp">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelarNota">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGuardarNota">
                <i class="fas fa-save"></i> Guardar Nota
            </button>
        </div>
    </div>
</div>

<!-- NUEVO: Modal para Asignar Cita -->
<div class="modal" id="modalAsignarCita">
    <div class="modal-content modal-notas">
        <div class="modal-header">
            <h2><i class="fas fa-calendar-plus"></i> Asignar Cita</h2>
            <button class="close-btn" id="closeModalCita">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formAsignarCita">
                <div class="form-group">
                    <div class="info-group">
                        <div class="info-label">Cliente</div>
                        <div class="info-value" id="clienteNombreCita" style="font-size: 16px; font-weight: bold;"></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">TP</div>
                        <div class="info-value" id="clienteTPCita" style="font-weight: bold; color: #3498db;"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="fechaCita" class="form-label">Fecha *</label>
                    <input type="date" id="fechaCita" name="fecha" class="form-control" required>
                           <!--min="<?php echo date('Y-m-d'); ?>">-->
                </div>
                
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="horaCita" class="form-label">Hora *</label>
                        <select id="horaCita" name="hora" class="form-control" required>
                            <option value="">Seleccionar hora</option>
                            <?php for ($i = 8; $i <= 20; $i++): ?>
                                <?php
                                $hour = $i > 12 ? $i - 12 : $i;
                                $ampm = $i >= 12 ? 'pm' : 'am';
                                $value = str_pad($i, 2, '0', STR_PAD_LEFT);
                                ?>
                                <option value="<?php echo $value; ?>">
                                    <?php echo $hour . ':00 ' . $ampm; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label for="minutosCita" class="form-label">Minutos *</label>
                        <select id="minutosCita" name="minutos" class="form-control" required>
                            <option value="">Seleccionar minutos</option>
                            <option value="00">00</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="40">40</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                
                <input type="hidden" id="clienteTPHiddenCita" name="tp">
                <input type="hidden" id="clienteNombreHiddenCita" name="nombre">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelarCita">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGuardarCita">
                <i class="fas fa-calendar-check"></i> Guardar Cita
            </button>
        </div>
    </div>
</div>

<!-- Modal para Editar Cliente -->
<div class="modal" id="modalEditarCliente">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Editar Cliente</h2>
            <button class="close-btn" id="closeModalEditar">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formEditarCliente">
                <div id="loadingEditar" style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin"></i> Cargando datos del cliente...
                </div>
                <div id="formEditarContent" style="display: none;">
                    <div class="form-group">
                        <label for="editTP" class="form-label">TP</label>
                        <input type="text" id="editTP" name="tp" class="form-control" readonly style="background-color: #f8f9fa;">
                    </div>
                    
                    <div class="form-group">
                        <label for="editNombre" class="form-label">Nombre *</label>
                        <input type="text" id="editNombre" name="nombre" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editApellido" class="form-label">Apellido</label>
                        <input type="text" id="editApellido" name="apellido" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editNumero" class="form-label">Teléfono</label>
                        <input type="text" id="editNumero" name="numero" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editCorreo" class="form-label">Email</label>
                        <input type="email" id="editCorreo" name="correo" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editPais" class="form-label">País</label>
                        <input type="text" id="editPais" name="pais" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="editAuxiliar" class="form-label">Auxiliar</label>
                        <input type="text" id="editAuxiliar" name="auxiliar" class="form-control">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelarEditar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGuardarEditar">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </div>
</div>

<!-- Modal para Filtros - CON SCROLL CORREGIDO -->
<div class="modal" id="modalFiltros">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h2><i class="fas fa-filter"></i> Filtrar Leads</h2>
            <button class="close-btn" id="closeModalFiltros">&times;</button>
        </div>
        <div class="modal-body" style="padding: 25px; overflow-y: auto; flex: 1;">
            <form id="formFiltros">
                <!-- Búsqueda Básica -->
                <div class="filtro-seccion">
                    <div class="filtro-titulo">
                        <i class="fas fa-search"></i>
                        <h3>Búsqueda Básica</h3>
                    </div>
                    <div class="form-group">
                        <input type="text" id="busquedaBasica" name="busqueda_basica" 
                               class="form-control" placeholder="Buscar por nombre, teléfono o correo...">
                        <small class="texto-ayuda">Busca en nombre, teléfono y correo electrónico</small>
                    </div>
                </div>

                <div class="filtros-grid">
                    <!-- Columna Izquierda -->
                    <div class="filtros-columna">
                        <!-- Países -->
                        <div class="filtro-seccion">
                            <div class="filtro-titulo">
                                <i class="fas fa-globe-americas"></i>
                                <h3>País</h3>
                                <span class="contador-mini" id="contadorPaisesMini">0</span>
                            </div>
                            <div class="filtro-multiple" id="filtroPaises">
                                <div class="loading-opciones">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando países...
                                </div>
                            </div>
                        </div>

                        <!-- Apellidos -->
                        <div class="filtro-seccion">
                            <div class="filtro-titulo">
                                <i class="fas fa-user-tag"></i>
                                <h3>Apellido</h3>
                                <span class="contador-mini" id="contadorApellidosMini">0</span>
                            </div>
                            <div class="filtro-multiple" id="filtroApellidos">
                                <div class="loading-opciones">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando apellidos...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha -->
                    <div class="filtros-columna">
                        <!-- Asignados -->
                        <div class="filtro-seccion">
                            <div class="filtro-titulo">
                                <i class="fas fa-user-check"></i>
                                <h3>Asignado a</h3>
                                <span class="contador-mini" id="contadorAsignadosMini">0</span>
                            </div>
                            <div class="filtro-multiple" id="filtroAsignados">
                                <div class="loading-opciones">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando asignados...
                                </div>
                            </div>
                        </div>

                        <!-- Estados -->
                        <div class="filtro-seccion">
                            <div class="filtro-titulo">
                                <i class="fas fa-chart-line"></i>
                                <h3>Estado</h3>
                                <span class="contador-mini" id="contadorEstadosMini">0</span>
                            </div>
                            <div class="filtro-multiple" id="filtroEstados">
                                <div class="loading-opciones">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando estados...
                                </div>
                            </div>
                        </div>

                        <!-- Últimas Gestiones -->
                        <div class="filtro-seccion">
                            <div class="filtro-titulo">
                                <i class="fas fa-history"></i>
                                <h3>Última Gestión</h3>
                                <span class="contador-mini" id="contadorGestionesMini">0</span>
                            </div>
                            <div class="filtro-multiple" id="filtroGestiones">
                                <div class="loading-opciones">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando gestiones...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen de Filtros -->
                <div id="resumenFiltros" class="resumen-filtros" style="display: none;">
                    <div class="resumen-header">
                        <i class="fas fa-list-check"></i>
                        <strong>Filtros Seleccionados:</strong>
                    </div>
                    <div class="resumen-items">
                        <span class="resumen-item" id="resumenPaises">0 países</span>
                        <span class="resumen-item" id="resumenApellidos">0 apellidos</span>
                        <span class="resumen-item" id="resumenAsignados">0 asignados</span>
                        <span class="resumen-item" id="resumenGestiones">0 gestiones</span>
                        <span class="resumen-item" id="resumenEstados">0 estados</span>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" id="btnLimpiarFiltros">
                <i class="fas fa-eraser"></i> Limpiar Todo
            </button>
            <div style="flex: 1;"></div>
            <button type="button" class="btn btn-secondary" id="btnCancelarFiltros">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnAplicarFiltros">
                <i class="fas fa-filter"></i> Aplicar Filtros
            </button>
        </div>
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
}

.btn-view {
    background-color: #3498db;
    color: white;
}

.btn-call {
    background-color: #2ecc71;
    color: white;
}

.btn-note {
    background-color: #9b59b6;
    color: white;
}

/* NUEVO ESTILO PARA BOTÓN DE CITA */
.btn-cita {
    background-color: #e67e22;
    color: white;
}

.btn-action:hover {
    opacity: 0.8;
    transform: translateY(-1px);
}

.leads-table {
    width: 100%;
    border-collapse: collapse;
}

.leads-table th {
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

.leads-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #ecf0f1;
    vertical-align: top;
    white-space: nowrap;
    overflow: visible;
}

/* Anchos específicos para cada columna - AJUSTADOS */
.leads-table th:nth-child(1), .leads-table td:nth-child(1) { width: 50px; text-align: center; } /* Checkbox */
.leads-table th:nth-child(2), .leads-table td:nth-child(2) { width: 120px; } /* TP */
.leads-table th:nth-child(3), .leads-table td:nth-child(3) { width: 150px; } /* Nombre */
.leads-table th:nth-child(4), .leads-table td:nth-child(4) { width: 150px; } /* Apellido */
.leads-table th:nth-child(5), .leads-table td:nth-child(5) { width: 100px; } /* Estado */
.leads-table th:nth-child(6), .leads-table td:nth-child(6) { width: 100px; } /* País */
.leads-table th:nth-child(7), .leads-table td:nth-child(7) { width: 200px; } /* Última Gestión */
.leads-table th:nth-child(8), .leads-table td:nth-child(8) { width: 150px; } /* Fecha */
.leads-table th:nth-child(9), .leads-table td:nth-child(9) { width: 170px; } /* Acciones (aumentado para nuevo botón) */

.table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: auto;
}

/* Estilos para paginación */
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

/* Status badges */
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
}

.status-new {
    background-color: #e8f4fd;
    color: #3498db;
}

.status-activo {
    background-color: #e8f8f5;
    color: #2ecc71;
}

.status-inactivo {
    background-color: #fbeeee;
    color: #e74c3c;
}

/* Botones generales */
.btn {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    display: flex;
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

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #ecf0f1;
    color: #2c3e50;
}

.btn-secondary:hover {
    background-color: #dde4e6;
}

/* Tooltip para emails largos */
.leads-table td[title]:hover::after {
    content: attr(title);
    position: absolute;
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-top: 5px;
}

/* ========== ESTILOS MEJORADOS PARA MODALES ========== */
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
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.modal-header {
    background-color: #3498db;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h2 {
    font-size: 20px;
    font-weight: 500;
    margin: 0;
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
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 15px 20px;
    background-color: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e1e4e8;
    flex-shrink: 0;
}

/* Scroll para modales */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Estilos para la información del cliente */
.cliente-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.info-group {
    margin-bottom: 10px;
}

.info-label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.info-value {
    color: #34495e;
    font-size: 14px;
}

/* Estilos para las notas */
.notas-container {
    margin-top: 20px;
}

.notas-header {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #3498db;
}

.nota-item {
    background: #f8f9fa;
    border-left: 4px solid #3498db;
    padding: 12px 15px;
    margin-bottom: 10px;
    border-radius: 4px;
}

.nota-fecha {
    font-size: 12px;
    color: #7f8c8d;
    margin-bottom: 5px;
}

.nota-tipo {
    display: inline-block;
    background: #3498db;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-bottom: 8px;
}

.nota-descripcion {
    color: #2c3e50;
    line-height: 1.4;
}

.sin-notas {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 20px;
}

/* Estilos para el formulario de notas */
.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 15px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

/* Loading para el modal de notas */
.loading-estados {
    text-align: center;
    padding: 20px;
    color: #7f8c8d;
}

.loading-estados i {
    margin-right: 8px;
}

/* Modal de notas específico - MÁS GRANDE */
.modal-notas {
    max-width: 700px !important;
    width: 90% !important;
    max-height: 80vh !important;
}

.modal-notas .modal-body {
    max-height: calc(80vh - 140px);
    overflow-y: auto;
}

.modal-notas .form-control {
    font-size: 15px;
    padding: 12px;
}

.modal-notas textarea.form-control {
    min-height: 150px;
    resize: vertical;
}

.modal-notas .modal-footer .btn {
    padding: 10px 20px;
    font-size: 15px;
}

/* ========== ESTILOS MEJORADOS PARA FILTROS ========== */

/* Layout de filtros */
.filtros-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-top: 20px;
}

.filtros-columna {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Secciones de filtro */
.filtro-seccion {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.filtro-seccion:hover {
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.1);
}

.filtro-titulo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid #e9ecef;
    border-radius: 8px 8px 0 0;
    margin: 0;
}

.filtro-titulo h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filtro-titulo i {
    color: #3498db;
    font-size: 14px;
    width: 16px;
    text-align: center;
}

.contador-mini {
    background: #3498db;
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
}

/* Filtros múltiples - MEJORADO */
.filtro-multiple {
    max-height: 150px;
    overflow-y: auto;
    padding: 15px 20px;
    background: white;
    border-radius: 0 0 8px 8px;
}

.filtro-multiple::-webkit-scrollbar {
    width: 6px;
}

.filtro-multiple::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.filtro-multiple::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.filtro-multiple::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.opcion-filtro {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.opcion-filtro:hover {
    background: #e3f2fd;
    border-color: #bbdefb;
    transform: translateX(2px);
}

.opcion-filtro input[type="checkbox"] {
    margin-right: 12px;
    transform: scale(1.1);
    accent-color: #3498db;
}

.opcion-filtro label {
    cursor: pointer;
    font-size: 13px;
    margin: 0;
    flex: 1;
    color: #34495e;
    font-weight: 500;
    transition: color 0.2s ease;
}

.opcion-filtro:hover label {
    color: #2c3e50;
}

.opcion-filtro input[type="checkbox"]:checked + label {
    color: #3498db;
    font-weight: 600;
}

/* Loading estados */
.loading-opciones {
    text-align: center;
    color: #7f8c8d;
    padding: 30px 20px;
    font-size: 13px;
}

.loading-opciones i {
    margin-right: 8px;
    color: #3498db;
}

.sin-opciones {
    text-align: center;
    color: #95a5a6;
    padding: 20px;
    font-style: italic;
    font-size: 13px;
}

/* Resumen de filtros */
.resumen-filtros {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 25px;
}

.resumen-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}

.resumen-header i {
    font-size: 16px;
}

.resumen-items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.resumen-item {
    background: rgba(255, 255, 255, 0.2);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Texto de ayuda */
.texto-ayuda {
    display: block;
    margin-top: 8px;
    color: #7f8c8d;
    font-size: 12px;
    font-style: italic;
}

/* Botón outline */
.btn-outline {
    padding: 8px 15px;
    border-radius: 5px;
    border: 1px solid #dc3545;
    background: white;
    color: #dc3545;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: #dc3545;
    color: white;
}

.btn-outline i {
    margin-right: 5px;
}

/* Mejoras responsive */
@media (max-width: 768px) {
    .filtros-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .modal-content {
        margin: 20px;
        width: calc(100% - 40px);
    }
    
    .resumen-items {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Efectos de transición */
.filtro-seccion {
    animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Badge para indicar filtros activos en el botón */
#filterBtn.has-filters::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: #e74c3c;
    border-radius: 50%;
    border: 2px solid white;
}

/* Estilos para la búsqueda mejorada en leads */
.search-bar {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 25px;
    padding: 8px 15px;
    border: 1px solid #ddd;
    min-width: 500px;
    transition: all 0.3s ease;
}

.search-bar:focus-within {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.search-bar select {
    border: none;
    background: transparent;
    outline: none;
    font-size: 14px;
    min-width: 150px;
    border-right: 1px solid #eee;
    padding-right: 10px;
    margin-right: 10px;
    cursor: pointer;
}

.search-bar input {
    border: none;
    background: transparent;
    outline: none;
    width: 100%;
    font-size: 14px;
}

.search-bar .fa-search {
    color: #7f8c8d;
    margin-right: 10px;
}

/* Ocultar el filtro de Asignados */
.filtro-seccion:has(#filtroAsignados) {
    display: none !important;
}

/* Ocultar el contador de asignados en el resumen */
#contadorAsignadosMini,
#resumenAsignados {
    display: none !important;
}

/* Agregar estilos para el input de registros por página */
.card-actions {
    display: flex;
    align-items: center;
}

#registrosPorPagina {
    transition: all 0.3s ease;
}

#registrosPorPagina:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
    outline: none;
}

/* Ajustar el texto de "Mostrando X de Y registros" */
.pagination-container > div:first-child {
    min-width: 300px;
}

/* Estilos para el grupo de configuración unificada */
.card-actions > div:first-child {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-right: 15px;
}

.card-actions > div:first-child > div {
    display: flex;
    align-items: center;
    gap: 5px;
}

#registrosPorPagina, #ordenCampo, #ordenDireccion {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s ease;
}

#registrosPorPagina:focus, #ordenCampo:focus, #ordenDireccion:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
    outline: none;
}

button[onclick="aplicarConfiguracion()"] {
    padding: 6px 15px;
    border: 1px solid #2ecc71;
    border-radius: 4px;
    background: #2ecc71;
    color: white;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

button[onclick="aplicarConfiguracion()"]:hover {
    background: #27ae60;
    border-color: #27ae60;
}

button[onclick="aplicarConfiguracion()"] i {
    margin-right: 0;
}

/* Botón OK personalizado */
.card-actions button[onclick="cambiarRegistrosPorPagina()"] {
    transition: all 0.3s ease;
}

.card-actions button[onclick="cambiarRegistrosPorPagina()"]:hover {
    background: #2980b9 !important;
    border-color: #2980b9 !important;
}

/* ========== ESTILOS PARA CHECKBOXES Y SELECCIÓN DE FILAS ========== */

/* Estilos para filas seleccionadas */
.leads-table tbody tr.seleccionada {
    background-color: #e8f6f3 !important;
    border-left: 4px solid #2ecc71 !important;
}

.leads-table tbody tr.seleccionada:hover {
    background-color: #d4efec !important;
}

.leads-table tbody tr.seleccionada td {
    border-color: #d4efec !important;
}

/* Estilos para los checkboxes */
.seleccionar-lead {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #2ecc71;
}

#seleccionarTodos {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #3498db;
}

/* Botón Quitar Todos mejorado */
#deseleccionarTodos {
    background: #fff;
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

#deseleccionarTodos:hover {
    background: #e74c3c;
    color: white;
}

/* Ajuste de ancho de columnas con la nueva columna */
.leads-table th:nth-child(1), .leads-table td:nth-child(1) { 
    width: 50px !important; 
    text-align: center;
    padding: 8px 4px !important;
}

.leads-table th:nth-child(2), .leads-table td:nth-child(2) { width: 120px !important; } /* TP */
.leads-table th:nth-child(3), .leads-table td:nth-child(3) { width: 150px !important; } /* Nombre */
.leads-table th:nth-child(4), .leads-table td:nth-child(4) { width: 150px !important; } /* Apellido */
.leads-table th:nth-child(5), .leads-table td:nth-child(5) { width: 100px !important; } /* Estado */
.leads-table th:nth-child(6), .leads-table td:nth-child(6) { width: 100px !important; } /* País */
.leads-table th:nth-child(7), .leads-table td:nth-child(7) { width: 200px !important; } /* Última Gestión */
.leads-table th:nth-child(8), .leads-table td:nth-child(8) { width: 150px !important; } /* Fecha */
.leads-table th:nth-child(9), .leads-table td:nth-child(9) { width: 170px !important; } /* Acciones (aumentado para nuevo botón) */

/* Estilos para el formulario de cita */
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
}

/* Estilo para la fecha */
input[type="date"] {
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 15px;
    width: 100%;
    box-sizing: border-box;
}

input[type="date"]:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

/* ========== ESTILOS MEJORADOS PARA NOTAS ========== */

.nota-metadata {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.nota-autor {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #7f8c8d;
    background: #f8f9fa;
    padding: 4px 10px;
    border-radius: 15px;
    border: 1px solid #e9ecef;
}

.nota-autor i {
    font-size: 11px;
}

.nota-tipo {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #3498db;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}

.nota-tipo i {
    font-size: 11px;
}

.nota-fecha {
    font-size: 12px;
    color: #7f8c8d;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.nota-fecha i {
    color: #95a5a6;
}

.nota-descripcion {
    color: #2c3e50;
    line-height: 1.5;
    padding: 10px;
    background: white;
    border-radius: 5px;
    border: 1px solid #ecf0f1;
    margin-top: 5px;
}

.nota-item {
    background: #f8f9fa;
    border-left: 4px solid #3498db;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nota-item:hover {
    background: #edf2f7;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sin-notas {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 20px;
}

.sin-notas i {
    font-size: 48px;
    color: #bdc3c7;
    margin-bottom: 15px;
}
</style>

<script>
// Variable global para la extensión del usuario
const usuario_actual_ext = "<?php echo htmlspecialchars($usuario_actual['ext'] ?? ''); ?>";

// Variables globales para filtros
let opcionesFiltro = {};
let filtrosAplicados = {};

// ========== FUNCIONES DE CHECKBOXES Y SELECCIÓN ==========

// Función para inicializar el sistema de checkboxes
function inicializarSistemaCheckboxes() {
    // Seleccionar/Deseleccionar todos
    const seleccionarTodos = document.getElementById('seleccionarTodos');
    const deseleccionarTodosBtn = document.getElementById('deseleccionarTodos');
    
    if (seleccionarTodos) {
        seleccionarTodos.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.seleccionar-lead');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                actualizarEstadoFila(checkbox);
            });
        });
    }
    
    if (deseleccionarTodosBtn) {
        deseleccionarTodosBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.seleccionar-lead');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                actualizarEstadoFila(checkbox);
            });
            
            // Desmarcar también el checkbox "Seleccionar todos"
            if (seleccionarTodos) {
                seleccionarTodos.checked = false;
            }
            
            // Mostrar feedback visual
            mostrarFeedbackSeleccion();
        });
    }
    
    // Event listeners para checkboxes individuales
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('seleccionar-lead')) {
            actualizarEstadoFila(e.target);
            actualizarCheckboxSeleccionarTodos();
        }
    });
}

// Función para actualizar el estado visual de la fila
function actualizarEstadoFila(checkbox) {
    const fila = checkbox.closest('tr');
    if (checkbox.checked) {
        fila.classList.add('seleccionada');
    } else {
        fila.classList.remove('seleccionada');
    }
}

// Función para actualizar el checkbox "Seleccionar todos"
function actualizarCheckboxSeleccionarTodos() {
    const seleccionarTodos = document.getElementById('seleccionarTodos');
    if (!seleccionarTodos) return;
    
    const checkboxes = document.querySelectorAll('.seleccionar-lead');
    const totalCheckboxes = checkboxes.length;
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    
    if (checkedCount === 0) {
        seleccionarTodos.checked = false;
        seleccionarTodos.indeterminate = false;
    } else if (checkedCount === totalCheckboxes) {
        seleccionarTodos.checked = true;
        seleccionarTodos.indeterminate = false;
    } else {
        seleccionarTodos.checked = false;
        seleccionarTodos.indeterminate = true;
    }
}

// Función para mostrar feedback visual al deseleccionar todos
function mostrarFeedbackSeleccion() {
    const btn = document.getElementById('deseleccionarTodos');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    const originalBg = btn.style.background;
    const originalColor = btn.style.color;
    
    btn.innerHTML = '<i class="fas fa-check"></i> Todos deseleccionados';
    btn.style.background = '#2ecc71';
    btn.style.color = 'white';
    btn.style.borderColor = '#2ecc71';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = originalBg;
        btn.style.color = originalColor;
        btn.style.borderColor = '#e74c3c';
    }, 1500);
}

// ========== FUNCIONES DE CONFIGURACIÓN UNIFICADA ==========

// Función para validar la cantidad de registros por página
function validarRegistrosPorPagina() {
    const input = document.getElementById('registrosPorPagina');
    const cantidad = parseInt(input.value);
    
    // Validar que sea un número válido
    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Por favor ingrese un número válido mayor a 0');
        input.focus();
        return false;
    }
    
    // Limitar máximo
    const maxRegistros = 1000;
    if (cantidad > maxRegistros) {
        alert('El número máximo permitido es ' + maxRegistros);
        input.value = maxRegistros;
        input.focus();
        return false;
    }
    
    return true;
}

// Función principal para aplicar toda la configuración
function aplicarConfiguracion() {
    // Validar registros por página
    if (!validarRegistrosPorPagina()) {
        return;
    }
    
    // Obtener valores actuales
    const registrosPorPagina = document.getElementById('registrosPorPagina').value;
    const ordenCampo = document.getElementById('ordenCampo').value;
    const ordenDireccion = document.getElementById('ordenDireccion').value;
    
    // Obtener parámetros actuales de la URL
    const urlParams = new URLSearchParams(window.location.search);
    
    // Actualizar todos los parámetros
    urlParams.set('registros_por_pagina', registrosPorPagina);
    urlParams.set('orden', ordenCampo);
    urlParams.set('direccion', ordenDireccion);
    
    // Volver a la página 1 al aplicar cambios
    urlParams.set('pagina', '1');
    
    // Redirigir con los nuevos parámetros
    window.location.href = 'leads.php?' + urlParams.toString();
}

// Permitir Enter en el input de registros
document.getElementById('registrosPorPagina').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        aplicarConfiguracion();
    }
});

// ========== FUNCIÓN NUEVA PARA ASIGNAR CITA ==========

// Función para abrir modal de asignar cita
function asignarCita(tp, nombre) {
    console.log('Abriendo modal para asignar cita. TP:', tp, 'Nombre:', nombre);
    
    const modal = document.getElementById('modalAsignarCita');
    const clienteTPElement = document.getElementById('clienteTPCita');
    const clienteNombreElement = document.getElementById('clienteNombreCita');
    const clienteTPHidden = document.getElementById('clienteTPHiddenCita');
    const clienteNombreHidden = document.getElementById('clienteNombreHiddenCita');
    const fechaCita = document.getElementById('fechaCita');
    const horaCita = document.getElementById('horaCita');
    const minutosCita = document.getElementById('minutosCita');
    
    // Establecer valores del cliente
    clienteTPElement.textContent = tp;
    clienteNombreElement.textContent = nombre;
    clienteTPHidden.value = tp;
    clienteNombreHidden.value = nombre;
    
    // Establecer fecha mínima como hoy
    const today = new Date().toISOString().split('T')[0];
    fechaCita.min = today;
    fechaCita.value = today;
    
    // Resetear hora y minutos
    horaCita.value = '';
    minutosCita.value = '';
    
    // Mostrar modal
    modal.style.display = 'flex';
}

// Función para guardar la cita
function guardarCita() {
    const form = document.getElementById('formAsignarCita');
    const formData = new FormData(form);
    const btnGuardar = document.getElementById('btnGuardarCita');
    const originalText = btnGuardar.innerHTML;
    
    // Validar formulario
    if (!formData.get('fecha') || !formData.get('hora') || !formData.get('minutos')) {
        alert('Por favor complete todos los campos obligatorios.');
        return;
    }
    
    // Mostrar loading en el botón
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    console.log('Enviando datos al servidor:', {
        fecha: formData.get('fecha'),
        hora: formData.get('hora'),
        minutos: formData.get('minutos'),
        tp: formData.get('tp'),
        nombre: formData.get('nombre')
    });
    
    // Enviar datos al servidor
    fetch('../admin/guardar_cita.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta recibida. Status:', response.status, 'OK:', response.ok);
        
        // Verificar si la respuesta es JSON válido
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Respuesta no es JSON:', text.substring(0, 500));
                throw new Error(`El servidor respondió con: ${text.substring(0, 100)}...`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos del servidor:', data);
        
        if (data.success) {
            // Cerrar modal y mostrar mensaje de éxito
            document.getElementById('modalAsignarCita').style.display = 'none';
            
            // Mostrar mensaje de éxito con detalles
            alert(`✅ Cita asignada exitosamente\n\nCliente: ${data.cliente}\nTP: ${data.tp}\nFecha: ${data.fecha}\nHora: ${data.hora}`);
            
            // Opcional: Recargar la página o actualizar la tabla
            // location.reload();
        } else {
            throw new Error(data.error || 'Error al guardar la cita');
        }
    })
    .catch(error => {
        console.error('Error guardando cita:', error);
        alert('❌ Error al guardar la cita: ' + error.message);
    })
    .finally(() => {
        // Restaurar botón
        btnGuardar.innerHTML = originalText;
        btnGuardar.disabled = false;
    });
}

// ========== FUNCIONES EXISTENTES ==========

// Función para ver detalles del cliente
function viewClient(tp) {
    console.log('=== INICIANDO CARGA DE CLIENTE ===');
    console.log('TP del cliente:', tp);
    
    // Mostrar el modal
    const modal = document.getElementById('modalVerCliente');
    const modalContent = document.getElementById('modalClienteContent');
    
    // Mostrar loading
    modalContent.innerHTML = `
        <div id="loadingCliente" style="text-align: center; padding: 40px;">
            <div style="color: #3498db; font-size: 18px; margin-bottom: 10px;">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            <p>Cargando información del cliente...</p>
            <p style="font-size: 12px; color: #7f8c8d;">TP: ${tp}</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // URL de la petición
    const url = `../admin/ver_cliente.php?tp=${encodeURIComponent(tp)}`;
    console.log('URL de la petición:', url);
    
    // Hacer petición AJAX
    fetch(url)
        .then(response => {
            console.log('=== RESPUESTA DEL SERVIDOR ===');
            console.log('Status:', response.status);
            console.log('Status Text:', response.statusText);
            console.log('URL:', response.url);
            console.log('Headers:', Object.fromEntries(response.headers.entries()));
            
            return response.text().then(text => {
                console.log('Contenido de la respuesta:', text);
                
                // Verificar si la respuesta está vacía
                if (!text || text.trim() === '') {
                    throw new Error('El servidor devolvió una respuesta vacía');
                }
                
                // Verificar si es HTML (buscar etiquetas HTML)
                if (text.trim().startsWith('<!DOCTYPE') || text.includes('<html') || text.includes('<body')) {
                    console.error('El servidor devolvió HTML en lugar de JSON');
                    // Extraer el texto del error si es posible
                    const errorMatch = text.match(/<title[^>]*>(.*?)<\/title>/i) || 
                                      text.match(/<h1[^>]*>(.*?)<\/h1>/i) ||
                                      text.match(/<p[^>]*>(.*?)<\/p>/i);
                    const errorText = errorMatch ? errorMatch[1] : 'Respuesta HTML inesperada';
                    throw new Error(`El servidor devolvió HTML: ${errorText}`);
                }
                
                try {
                    const data = JSON.parse(text);
                    console.log('JSON parseado correctamente:', data);
                    
                    // Debug: Mostrar estructura de las notas
                    if (data.notas && data.notas.length > 0) {
                        console.log('=== ESTRUCTURA DE LA PRIMERA NOTA ===');
                        console.log('Campos disponibles:', Object.keys(data.notas[0]));
                        console.log('Valor de User:', data.notas[0].User);
                        console.log('Nota completa:', data.notas[0]);
                    }
                    
                    return data;
                } catch (e) {
                    console.error('Error parseando JSON. Primeros 500 caracteres:', text.substring(0, 500));
                    throw new Error('No se pudo parsear la respuesta como JSON. ¿Hay errores PHP?');
                }
            });
        })
        .then(data => {
            console.log('=== PROCESANDO DATOS ===');
            
            if (data && data.success) {
                console.log('Datos del cliente cargados correctamente');
                console.log('Cliente:', data.cliente);
                console.log('Notas encontradas:', data.notas ? data.notas.length : 0);
                
                // Mostrar la información del cliente
                mostrarInformacionCliente(data.cliente, data.notas);
            } else {
                console.error('Error en la respuesta:', data);
                const errorMsg = data && data.error ? data.error : 'Error desconocido en el servidor';
                
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #e74c3c;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <h3>Error del Servidor</h3>
                        <p>${errorMsg}</p>
                        <div style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: left;">
                            <strong>Detalles técnicos:</strong>
                            <ul style="margin-top: 10px;">
                                <li>TP solicitado: ${tp}</li>
                                <li>Archivo: ../admin/ver_cliente.php</li>
                                <li>Consulta: SELECT * FROM clientes WHERE TP = '${tp}'</li>
                            </ul>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('=== ERROR COMPLETO ===', error);
            
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3>Error de Comunicación</h3>
                    <p><strong>${error.message}</strong></p>
                    
                    <div style="margin-top: 20px; background: #fff3cd; padding: 15px; border-radius: 5px; text-align: left; border: 1px solid #ffeaa7;">
                        <h4 style="margin-top: 0; color: #856404;">Solución de problemas:</h4>
                        <ol style="text-align: left;">
                            <li>Verifica que el archivo <strong>ver_cliente.php</strong> existe en el servidor</li>
                            <li>Revisa que <strong>config/database.php</strong> tenga la conexión correcta</li>
                            <li>Abre directamente: <a href="ver_cliente.php?tp=${tp}" target="_blank">ver_cliente.php?tp=${tp}</a> para ver el error completo</li>
                            <li>Revisa los logs de error de PHP</li>
                        </ol>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button onclick="window.open('../admin/ver_cliente.php?tp=${tp}', '_blank')" class="btn btn-secondary" style="margin: 5px;">
                            <i class="fas fa-external-link-alt"></i> Abrir URL directamente
                        </button>
                    </div>
                </div>
            `;
        });
}

// Función para mostrar la información del cliente en el modal - ACTUALIZADA PARA MOSTRAR AUTOR
function mostrarInformacionCliente(cliente, notas) {
    const modalContent = document.getElementById('modalClienteContent');
    
    let html = `
        <div class="cliente-info">
            <div style="grid-column: 1 / -1;">
                <div class="info-group">
                    <div class="info-label">TP</div>
                    <div class="info-value" style="font-size: 16px; font-weight: bold; color: #2c3e50;">${cliente.TP || 'N/A'}</div>
                </div>
                <div class="info-group">
                    <div class="info-label">Nombre</div>
                    <div class="info-value" style="font-size: 18px; font-weight: bold; color: #2c3e50;">
                        ${cliente.Nombre || 'N/A'} ${cliente.Apellido ? cliente.Apellido : ''}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="notas-container">
            <div class="notas-header">
                <i class="fas fa-sticky-note"></i> Historial de Notas (${notas.length})
            </div>
            
            <div class="notas-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ecf0f1; border-radius: 5px; padding: 10px;">
    `;
    
    if (notas.length > 0) {
        notas.forEach(nota => {
            // Formatear fecha si es necesario
            const fecha = nota.FechaUltimaGestion || 'N/A';
            const autor = nota.user || 'Sistema';
            const gestion = nota.UltimaGestion || 'Nota';
            const descripcion = nota.Descripcion || 'Sin descripción';
            
            html += `
                <div class="nota-item">
                    <div class="nota-fecha">
                        <i class="far fa-calendar"></i> <strong>Fecha:</strong> ${fecha}
                    </div>
                    <div class="nota-metadata">
                        <span class="nota-tipo">
                            <i class="fas fa-tag"></i> ${gestion}
                        </span>
                        <span class="nota-autor">
                            <i class="fas fa-user-edit"></i> ${autor}
                        </span>
                    </div>
                    <div class="nota-descripcion">
                        ${descripcion}
                    </div>
                </div>
            `;
        });
    } else {
        html += `
            <div class="sin-notas">
                <i class="fas fa-sticky-note" style="font-size: 48px; color: #bdc3c7; margin-bottom: 15px;"></i>
                <p>No hay notas registradas para este cliente</p>
            </div>
        `;
    }
    
    html += `</div></div>`;
    modalContent.innerHTML = html;
}

// Función simple para realizar llamada
function makeCall(numero, extension) {
    // Validar que tengamos los datos necesarios
    if (!numero || numero.trim() === '' || !extension || extension.trim() === '') {
        return; // Salir silenciosamente si falta algún dato
    }
    
    // Realizar llamada directamente sin confirmación y sin feedback
    const url = `../admin/llamada.php?numero=${encodeURIComponent(numero)}&extension=${encodeURIComponent(extension)}`;
    
    // Usar fetch para hacer la llamada en segundo plano
    fetch(url).catch(error => console.error('Error en llamada:', error));
}

// FUNCIÓN EDITAR CLIENTE - CORREGIDA
function editClient(tp) {
    console.log('Abriendo modal para editar cliente. TP:', tp);
    
    const modal = document.getElementById('modalEditarCliente');
    const loading = document.getElementById('loadingEditar');
    const formContent = document.getElementById('formEditarContent');
    
    // Mostrar modal y loading
    modal.style.display = 'flex';
    loading.style.display = 'block';
    formContent.style.display = 'none';
    
    // Cargar datos del cliente
    fetch(`../admin/ver_cliente.php?tp=${encodeURIComponent(tp)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.success && data.cliente) {
                const cliente = data.cliente;
                
                // Llenar el formulario con los datos del cliente
                document.getElementById('editTP').value = cliente.TP || '';
                document.getElementById('editNombre').value = cliente.Nombre || '';
                document.getElementById('editApellido').value = cliente.Apellido || '';
                document.getElementById('editNumero').value = cliente.Numero || '';
                document.getElementById('editCorreo').value = cliente.Correo || '';
                document.getElementById('editPais').value = cliente.Pais || '';
                document.getElementById('editAuxiliar').value = cliente.Auxiliar || '';
                
                // Ocultar loading y mostrar formulario
                loading.style.display = 'none';
                formContent.style.display = 'block';
                
            } else {
                throw new Error(data.error || 'No se pudieron cargar los datos del cliente');
            }
        })
        .catch(error => {
            console.error('Error cargando datos del cliente:', error);
            loading.innerHTML = `
                <div style="color: #e74c3c; text-align: center;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error al cargar datos: ${error.message}</p>
                </div>
            `;
        });
}

// Funciones para los otros botones de acción
function addNote(tp) {
    console.log('Abriendo modal para agregar nota. TP:', tp);
    
    const modal = document.getElementById('modalAgregarNota');
    const clienteTPElement = document.getElementById('clienteTPNota');
    const clienteNombreElement = document.getElementById('clienteNombreNota');
    const clienteTPHidden = document.getElementById('clienteTPHidden');
    const gestionSelect = document.getElementById('gestionSelect');
    const notaDescripcion = document.getElementById('notaDescripcion');
    
    // Limpiar formulario
    gestionSelect.innerHTML = '<option value="">Seleccionar gestión...</option>';
    notaDescripcion.value = '';
    
    // Establecer TP del cliente
    clienteTPElement.textContent = tp;
    clienteTPHidden.value = tp;
    
    // Mostrar loading en el nombre del cliente
    clienteNombreElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
    
    // Mostrar modal
    modal.style.display = 'flex';
    
    // Cargar nombre del cliente y estados
    cargarDatosParaNota(tp);
}

// Función para cargar datos del cliente y estados
function cargarDatosParaNota(tp) {
    const clienteNombreElement = document.getElementById('clienteNombreNota');
    const gestionSelect = document.getElementById('gestionSelect');
    
    console.log('Cargando datos para nota, TP:', tp);
    
    // Primero cargar el nombre del cliente
    fetch(`../admin/ver_cliente.php?tp=${encodeURIComponent(tp)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Respuesta ver_cliente.php:', data);
            
            if (data.success && data.cliente) {
                clienteNombreElement.textContent = data.cliente.Nombre || 'Nombre no disponible';
                console.log('Nombre cliente cargado:', data.cliente.Nombre);
                
                // Ahora cargar los estados según el grupo del usuario
                return cargarEstados();
            } else {
                throw new Error('No se pudo cargar la información del cliente: ' + (data.error || 'Error desconocido'));
            }
        })
        .then(estados => {
            console.log('Estados recibidos:', estados);
            
            // Llenar el select con los estados
            gestionSelect.innerHTML = '<option value="">Seleccionar gestión...</option>';
            
            if (estados && estados.length > 0) {
                estados.forEach(estado => {
                    const option = document.createElement('option');
                    option.value = estado.Estado;
                    option.textContent = estado.Estado;
                    gestionSelect.appendChild(option);
                });
                console.log('Select llenado con', estados.length, 'estados');
            } else {
                gestionSelect.innerHTML = '<option value="">No hay estados disponibles</option>';
                console.warn('No se encontraron estados para este grupo');
            }
        })
        .catch(error => {
            console.error('Error cargando datos para nota:', error);
            clienteNombreElement.innerHTML = '<span style="color: #e74c3c;">Error cargando datos: ' + error.message + '</span>';
            gestionSelect.innerHTML = '<option value="">Error: ' + error.message + '</option>';
        });
}

// Función para cargar estados según el grupo del usuario
function cargarEstados() {
    console.log('Iniciando carga de estados...');
    
    return fetch('../admin/obtener_estados.php')
        .then(response => {
            console.log('Respuesta HTTP estados:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos de obtener_estados.php:', data);
            
            if (data.success) {
                console.log('Estados cargados exitosamente:', data.estados);
                console.log('Grupo ID:', data.grupo_id);
                console.log('Usuario:', data.usuario);
                return data.estados;
            } else {
                throw new Error(data.error || 'Error desconocido al cargar estados');
            }
        })
        .catch(error => {
            console.error('Error en cargarEstados:', error);
            throw error;
        });
}

// INICIALIZACIÓN DE LOS MODALES
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar sistema de checkboxes
    inicializarSistemaCheckboxes();
    
    // Modal Ver Cliente
    const modalVerCliente = document.getElementById('modalVerCliente');
    const closeModalVer = document.getElementById('closeModalVer');
    const btnCerrarVer = document.getElementById('btnCerrarVer');

    if (closeModalVer) closeModalVer.addEventListener('click', () => modalVerCliente.style.display = 'none');
    if (btnCerrarVer) btnCerrarVer.addEventListener('click', () => modalVerCliente.style.display = 'none');

    // Modal Agregar Nota
    const modalAgregarNota = document.getElementById('modalAgregarNota');
    const closeModalNota = document.getElementById('closeModalNota');
    const btnCancelarNota = document.getElementById('btnCancelarNota');
    const btnGuardarNota = document.getElementById('btnGuardarNota');

    if (closeModalNota) closeModalNota.addEventListener('click', () => modalAgregarNota.style.display = 'none');
    if (btnCancelarNota) btnCancelarNota.addEventListener('click', () => modalAgregarNota.style.display = 'none');
    if (btnGuardarNota) btnGuardarNota.addEventListener('click', guardarNota);

    // Modal Asignar Cita - NUEVO
    const modalAsignarCita = document.getElementById('modalAsignarCita');
    const closeModalCita = document.getElementById('closeModalCita');
    const btnCancelarCita = document.getElementById('btnCancelarCita');
    const btnGuardarCita = document.getElementById('btnGuardarCita');

    if (closeModalCita) closeModalCita.addEventListener('click', () => modalAsignarCita.style.display = 'none');
    if (btnCancelarCita) btnCancelarCita.addEventListener('click', () => modalAsignarCita.style.display = 'none');
    if (btnGuardarCita) btnGuardarCita.addEventListener('click', guardarCita);

    // Modal Editar Cliente
    const modalEditarCliente = document.getElementById('modalEditarCliente');
    const closeModalEditar = document.getElementById('closeModalEditar');
    const btnCancelarEditar = document.getElementById('btnCancelarEditar');
    const btnGuardarEditar = document.getElementById('btnGuardarEditar');

    if (closeModalEditar) closeModalEditar.addEventListener('click', () => modalEditarCliente.style.display = 'none');
    if (btnCancelarEditar) btnCancelarEditar.addEventListener('click', () => modalEditarCliente.style.display = 'none');
    if (btnGuardarEditar) btnGuardarEditar.addEventListener('click', guardarEdicionCliente);

    // Modal Filtros
    const modalFiltros = document.getElementById('modalFiltros');
    const btnFiltros = document.getElementById('filterBtn');
    const closeModalFiltros = document.getElementById('closeModalFiltros');
    const btnCancelarFiltros = document.getElementById('btnCancelarFiltros');
    const btnAplicarFiltros = document.getElementById('btnAplicarFiltros');
    const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');

    if (btnFiltros) btnFiltros.addEventListener('click', () => {
        modalFiltros.style.display = 'flex';
        cargarOpcionesFiltro();
    });
    if (closeModalFiltros) closeModalFiltros.addEventListener('click', () => modalFiltros.style.display = 'none');
    if (btnCancelarFiltros) btnCancelarFiltros.addEventListener('click', () => modalFiltros.style.display = 'none');
    if (btnAplicarFiltros) btnAplicarFiltros.addEventListener('click', aplicarFiltros);
    if (btnLimpiarFiltros) btnLimpiarFiltros.addEventListener('click', limpiarFiltros);

    // Cerrar modales al hacer clic fuera
    window.addEventListener('click', (e) => {
        if (e.target === modalVerCliente) modalVerCliente.style.display = 'none';
        if (e.target === modalAgregarNota) modalAgregarNota.style.display = 'none';
        if (e.target === modalAsignarCita) modalAsignarCita.style.display = 'none';
        if (e.target === modalEditarCliente) modalEditarCliente.style.display = 'none';
        if (e.target === modalFiltros) modalFiltros.style.display = 'none';
    });

    // Procesar filtros de la URL
    procesarFiltrosURL();
    
    // Inicializar buscador mejorado
    inicializarBuscadorLeads();
});

// Función para inicializar el buscador mejorado
function inicializarBuscadorLeads() {
    const formBusqueda = document.querySelector('.search-bar form');
    const inputBusqueda = document.querySelector('input[name="search"]');
    
    if (!formBusqueda || !inputBusqueda) return;
    
    // Buscar al presionar Enter
    inputBusqueda.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            formBusqueda.submit();
        }
    });
    
    // ELIMINAR la sección de auto-búsqueda después de 1 segundo
    // Esto es lo que causa la búsqueda automática mientras escribes
}

// Función para guardar la edición del cliente
function guardarEdicionCliente() {
    const form = document.getElementById('formEditarCliente');
    const formData = new FormData(form);
    const btnGuardar = document.getElementById('btnGuardarEditar');
    const originalText = btnGuardar.innerHTML;
    
    // Validar campos obligatorios
    if (!formData.get('nombre')) {
        alert('El campo Nombre es obligatorio');
        return;
    }
    
    // Mostrar loading
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    // Enviar datos al servidor
    fetch('../admin/guardar_edicion_cliente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Cliente actualizado correctamente');
            document.getElementById('modalEditarCliente').style.display = 'none';
            // Recargar la página para ver los cambios
            location.reload();
        } else {
            throw new Error(data.error || 'Error al guardar los cambios');
        }
    })
    .catch(error => {
        console.error('Error guardando cliente:', error);
        alert('Error al guardar los cambios: ' + error.message);
    })
    .finally(() => {
        btnGuardar.innerHTML = originalText;
        btnGuardar.disabled = false;
    });
}

// Función para guardar la nota
function guardarNota() {
    const form = document.getElementById('formAgregarNota');
    const formData = new FormData(form);
    const btnGuardar = document.getElementById('btnGuardarNota');
    const btnOriginalText = btnGuardar.innerHTML;
    
    // Validar formulario
    if (!formData.get('gestion') || !formData.get('descripcion')) {
        alert('Por favor complete todos los campos obligatorios.');
        return;
    }
    
    // Mostrar loading en el botón
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    // Enviar datos al servidor
    fetch('../admin/guardar_nota.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal y mostrar mensaje de éxito
            document.getElementById('modalAgregarNota').style.display = 'none';
            alert('Nota guardada exitosamente');
            
            // Opcional: Recargar la página o actualizar la tabla
            // location.reload();
        } else {
            throw new Error(data.error || 'Error al guardar la nota');
        }
    })
    .catch(error => {
        console.error('Error guardando nota:', error);
        alert('Error al guardar la nota: ' + error.message);
    })
    .finally(() => {
        // Restaurar botón
        btnGuardar.innerHTML = btnOriginalText;
        btnGuardar.disabled = false;
    });
}

// Auto-submit del formulario de búsqueda al escribir (opcional)
document.querySelector('input[name="search"]').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// ========== SISTEMA DE FILTROS ==========

// Cargar opciones para los filtros
function cargarOpcionesFiltro() {
    fetch('../admin/obtener_opciones_filtro.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                opcionesFiltro = data.opciones;
                renderizarOpcionesFiltro();
                marcarFiltrosActivos(); // Marcar checkboxes según URL
            } else {
                console.error('Error cargando opciones:', data.error);
                alert('Error al cargar opciones de filtro: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al cargar opciones de filtro');
        });
}

// Renderizar las opciones en los filtros
function renderizarOpcionesFiltro() {
    // Renderizar países
    renderizarOpcionesGrupo('paises', 'filtroPaises');
    
    // Renderizar apellidos
    renderizarOpcionesGrupo('apellidos', 'filtroApellidos');
    
    // Renderizar asignados
    renderizarOpcionesGrupo('asignados', 'filtroAsignados');
    
    // Renderizar gestiones
    renderizarOpcionesGrupo('gestiones', 'filtroGestiones');
    
    // Renderizar estados
    renderizarOpcionesGrupo('estados', 'filtroEstados');
}

function renderizarOpcionesGrupo(tipo, contenedorId) {
    const contenedor = document.getElementById(contenedorId);
    const opciones = opcionesFiltro[tipo] || [];
    
    if (opciones.length === 0) {
        contenedor.innerHTML = '<div class="sin-opciones">No hay opciones disponibles</div>';
        return;
    }
    
    let html = '';
    
    // Para gestiones, agregar opción "Sin gestión" al inicio
    if (tipo === 'gestiones') {
        html += `
            <div class="opcion-filtro">
                <input type="checkbox" id="gestiones_sin_gestion" 
                       value="Sin gestión" data-tipo="gestiones">
                <label for="gestiones_sin_gestion">Sin gestión</label>
            </div>
        `;
    }
    
    opciones.forEach(opcion => {
        if (opcion && opcion.trim() !== '') {
            html += `
                <div class="opcion-filtro">
                    <input type="checkbox" id="${tipo}_${opcion.replace(/[^a-zA-Z0-9]/g, '_')}" 
                           value="${opcion}" data-tipo="${tipo}">
                    <label for="${tipo}_${opcion.replace(/[^a-zA-Z0-9]/g, '_')}">${opcion}</label>
                </div>
            `;
        }
    });
    
    contenedor.innerHTML = html;
    
    // Agregar event listeners a los checkboxes
    contenedor.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarContadores);
    });
}

// Actualizar contadores de filtros seleccionados - MEJORADO
function actualizarContadores() {
    const contadores = {
        paises: 0,
        apellidos: 0,
        asignados: 0,
        gestiones: 0,
        estados: 0
    };
    
    // Contar checkboxes seleccionados por tipo
    document.querySelectorAll('.filtro-multiple input[type="checkbox"]:checked').forEach(checkbox => {
        const tipo = checkbox.getAttribute('data-tipo');
        if (contadores.hasOwnProperty(tipo)) {
            contadores[tipo]++;
        }
    });
    
    // Actualizar contadores mini en los títulos
    document.getElementById('contadorPaisesMini').textContent = contadores.paises;
    document.getElementById('contadorApellidosMini').textContent = contadores.apellidos;
    document.getElementById('contadorAsignadosMini').textContent = contadores.asignados;
    document.getElementById('contadorGestionesMini').textContent = contadores.gestiones;
    document.getElementById('contadorEstadosMini').textContent = contadores.estados;
    
    // Actualizar resumen
    document.getElementById('resumenPaises').textContent = `${contadores.paises} países`;
    document.getElementById('resumenApellidos').textContent = `${contadores.apellidos} apellidos`;
    document.getElementById('resumenAsignados').textContent = `${contadores.asignados} asignados`;
    document.getElementById('resumenGestiones').textContent = `${contadores.gestiones} gestiones`;
    document.getElementById('resumenEstados').textContent = `${contadores.estados} estados`;
    
    // Mostrar/ocultar resumen
    const totalFiltros = Object.values(contadores).reduce((a, b) => a + b, 0);
    document.getElementById('resumenFiltros').style.display = totalFiltros > 0 ? 'block' : 'none';
    
    // Agregar indicador al botón de filtros si hay filtros activos
    const filterBtn = document.getElementById('filterBtn');
    if (totalFiltros > 0) {
        filterBtn.classList.add('has-filters');
        filterBtn.innerHTML = '<i class="fas fa-filter"></i> Filtros <span style="margin-left: 5px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">' + totalFiltros + '</span>';
    } else {
        filterBtn.classList.remove('has-filters');
        filterBtn.innerHTML = '<i class="fas fa-filter"></i> Filtrar';
    }
}

// Función para marcar los filtros activos desde la URL
function marcarFiltrosActivos() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Marcar búsqueda básica
    const busquedaBasica = urlParams.get('search');
    if (busquedaBasica) {
        document.getElementById('busquedaBasica').value = busquedaBasica;
    }
    
    // Marcar checkboxes según los parámetros URL
    ['paises', 'apellidos', 'asignados', 'gestiones', 'estados'].forEach(tipo => {
        const valoresURL = urlParams.get(tipo);
        if (valoresURL) {
            const valores = valoresURL.split(',');
            valores.forEach(valor => {
                const checkbox = document.querySelector(`input[data-tipo="${tipo}"][value="${valor}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    });
    
    // Actualizar contadores
    actualizarContadores();
}

// Función para procesar filtros desde URL al cargar la página
function procesarFiltrosURL() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('filtrado') === 'true') {
        // Mostrar que los filtros están activos
        const filterBtn = document.getElementById('filterBtn');
        filterBtn.classList.add('has-filters');
        filterBtn.innerHTML = '<i class="fas fa-filter"></i> Filtros Activos';
        
        // Opcional: Mostrar resumen de filtros aplicados
        mostrarResumenFiltrosActivos(urlParams);
    }
}

function mostrarResumenFiltrosActivos(urlParams) {
    let filtrosActivos = [];
    
    if (urlParams.get('search')) {
        filtrosActivos.push(`Búsqueda: "${urlParams.get('search')}"`);
    }
    
    ['paises', 'apellidos', 'asignados', 'gestiones', 'estados'].forEach(tipo => {
        if (urlParams.get(tipo)) {
            const valores = urlParams.get(tipo).split(',');
            filtrosActivos.push(`${tipo}: ${valores.length} seleccionados`);
        }
    });
    
    if (filtrosActivos.length > 0) {
        console.log('Filtros activos:', filtrosActivos);
    }
}

// Aplicar filtros
function aplicarFiltros() {
    const formData = new FormData(document.getElementById('formFiltros'));
    
    // Recolectar filtros
    filtrosAplicados = {
        busqueda_basica: formData.get('busqueda_basica') || ''
    };
    
    // Recolectar checkboxes seleccionados por tipo
    ['paises', 'apellidos', 'asignados', 'gestiones', 'estados'].forEach(tipo => {
        const seleccionados = Array.from(document.querySelectorAll(`input[data-tipo="${tipo}"]:checked`))
            .map(checkbox => checkbox.value)
            .filter(valor => valor && valor.trim() !== '');
        
        if (seleccionados.length > 0) {
            filtrosAplicados[tipo] = seleccionados;
        }
    });
    
    // Aplicar filtros (ahora redirige a la página con parámetros)
    filtrarLeads(filtrosAplicados);
}

// Función para filtrar leads - CORREGIDA
function filtrarLeads(filtros) {
    const btnAplicar = document.getElementById('btnAplicarFiltros');
    const originalText = btnAplicar.innerHTML;
    
    // Mostrar loading
    btnAplicar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando...';
    btnAplicar.disabled = true;
    
    // Crear parámetros URL para la recarga
    const params = new URLSearchParams();
    
    // Agregar búsqueda básica si existe
    if (filtros.busqueda_basica && filtros.busqueda_basica.trim() !== '') {
        params.append('search', filtros.busqueda_basica);
    }
    
    // Agregar filtros como parámetros URL
    ['paises', 'apellidos', 'asignados', 'gestiones', 'estados'].forEach(tipo => {
        if (filtros[tipo] && filtros[tipo].length > 0) {
            params.append(tipo, filtros[tipo].join(','));
        }
    });
    
    // Agregar indicador de que son filtros
    params.append('filtrado', 'true');
    
    // Redirigir a la misma página con los filtros aplicados
    const url = `leads.php?${params.toString()}`;
    window.location.href = url;
}

// MODIFICAR la función limpiarFiltros para que también limpie la URL
function limpiarFiltros() {
    // Limpiar checkboxes
    document.querySelectorAll('.filtro-multiple input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Limpiar búsqueda básica
    document.getElementById('busquedaBasica').value = '';
    
    // Actualizar contadores
    actualizarContadores();
    
    // Limpiar filtros aplicados
    filtrosAplicados = {};
    
    // Si hay filtros activos en la URL, redirigir a la página sin filtros
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filtrado') === 'true') {
        window.location.href = 'leads.php';
    }
    
    // Mostrar mensaje de confirmación
    setTimeout(() => {
        const resumen = document.getElementById('resumenFiltros');
        resumen.style.background = 'linear-gradient(135deg, #2ecc71 0%, #27ae60 100%)';
        setTimeout(() => {
            resumen.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }, 1000);
    }, 100);
}

// Helper function para capitalizar
function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}
</script>

</body>
</html>