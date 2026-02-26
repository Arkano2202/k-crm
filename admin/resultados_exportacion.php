<?php
// resultados_exportacion.php
$pagina_actual = 'exportar_leads';

// Incluir archivos necesarios
include '../includes/session.php';
requireLogin();

// Verificar que sea administrador
$usuario_actual = getCurrentUser();
if ($usuario_actual['tipo'] != 1) {
    header('Location: index.php');
    exit();
}

// Incluir archivos necesarios
include '../config/database.php';

// ESTRATEGIA: Guardar filtros en SESSION cuando vienen del formulario
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['mostrar'])) {
    // Guardar filtros en SESSION cuando se envía el formulario
    $_SESSION['export_filtros'] = [
        'paises' => $_GET['paises'] ?? [],
        'asignados' => $_GET['asignados'] ?? [],
        'campanias' => $_GET['campanias'] ?? [],
        'estados' => $_GET['estados'] ?? [],
        'gestiones' => $_GET['gestiones'] ?? [],
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? ''
    ];
    
    // Redirigir sin parámetros GET para evitar problemas
    header('Location: resultados_exportacion.php');
    exit();
}

// Usar filtros de SESSION o de GET (para paginación)
if (isset($_SESSION['export_filtros'])) {
    $filtros = $_SESSION['export_filtros'];
} else {
    $filtros = [
        'paises' => [],
        'asignados' => [],
        'campanias' => [],
        'estados' => [],
        'gestiones' => [],
        'fecha_inicio' => '',
        'fecha_fin' => ''
    ];
}

// Asegurar que sean arrays
foreach (['paises', 'asignados', 'campanias', 'estados', 'gestiones'] as $campo) {
    if (!is_array($filtros[$campo]) && !empty($filtros[$campo])) {
        $filtros[$campo] = [$filtros[$campo]];
    }
    // Filtrar valores vacíos
    if (is_array($filtros[$campo])) {
        $filtros[$campo] = array_filter($filtros[$campo], function($v) {
            return !empty(trim($v));
        });
    }
}

// Función para construir consulta usando IN() para múltiples valores
function construirConsulta($filtros, $contar = false) {
    $where_conditions = [];
    $params = [];
    $param_count = 0;
    
    // Mapeo de campos del formulario a campos de la base de datos
    $campos_db = [
        'paises' => 'Pais',
        'asignados' => 'Asignado', 
        'campanias' => 'Campaña',
        'estados' => 'Estado',
        'gestiones' => 'UltimaGestion'
    ];
    
    // Procesar cada tipo de filtro
    foreach ($campos_db as $filtro_key => $db_field) {
        if (!empty($filtros[$filtro_key]) && is_array($filtros[$filtro_key])) {
            // Filtrar valores vacíos
            $valores = array_filter($filtros[$filtro_key], function($v) {
                return !empty(trim($v));
            });
            
            if (!empty($valores)) {
                // Crear placeholders para cada valor
                $placeholders = [];
                foreach ($valores as $i => $valor) {
                    $param_name = ":" . $filtro_key . "_" . $param_count++;
                    $placeholders[] = $param_name;
                    $params[$param_name] = trim($valor);
                }
                
                // Usar IN() para múltiples valores
                $where_conditions[] = "$db_field IN (" . implode(', ', $placeholders) . ")";
            }
        }
    }
    
    // Procesar fechas
    if (!empty($filtros['fecha_inicio'])) {
        $where_conditions[] = "FechaCreacion >= :fecha_inicio";
        $params[':fecha_inicio'] = date('Y-m-d', strtotime($filtros['fecha_inicio']));
    }
    
    if (!empty($filtros['fecha_fin'])) {
        $where_conditions[] = "FechaCreacion <= :fecha_fin";
        $params[':fecha_fin'] = date('Y-m-d', strtotime($filtros['fecha_fin'])) . ' 23:59:59';
    }
    
    // Construir WHERE clause
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    if ($contar) {
        $query = "SELECT COUNT(*) as total FROM clientes $where_clause";
    } else {
        $orden = "ORDER BY Nombre ASC";

        if (isset($_GET['modo']) && $_GET['modo'] === 'random') {
            $orden = "ORDER BY RAND()";
        }

        $query = "SELECT * FROM clientes $where_clause $orden";
    }
    
    return ['query' => $query, 'params' => $params];
}

// Configuración de paginación
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 10;

// Validar y limitar el número de registros por página
$max_por_pagina = 5000; // Límite máximo
$min_por_pagina = 1;    // Límite mínimo

if ($por_pagina < $min_por_pagina) {
    $por_pagina = $min_por_pagina;
} elseif ($por_pagina > $max_por_pagina) {
    $por_pagina = $max_por_pagina;
}

$pagina_actual_num = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual_num - 1) * $por_pagina;

// Validar página actual
$pagina_actual_num = max(1, $pagina_actual_num);

// Obtener datos
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener total de registros
    $consulta_total = construirConsulta($filtros, true);
    $stmt_total = $db->prepare($consulta_total['query']);
    
    foreach ($consulta_total['params'] as $param => $value) {
        $stmt_total->bindValue($param, $value);
    }
    
    $stmt_total->execute();
    $total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = $total_registros > 0 ? ceil($total_registros / $por_pagina) : 1;
    
    // Ajustar página si es necesario
    if ($pagina_actual_num > $total_paginas && $total_paginas > 0) {
        $pagina_actual_num = $total_paginas;
        $offset = ($pagina_actual_num - 1) * $por_pagina;
    }
    
    // Obtener datos paginados
    $consulta_datos = construirConsulta($filtros, false);
    $consulta_datos['query'] .= " LIMIT :limit OFFSET :offset";
    
    $stmt_datos = $db->prepare($consulta_datos['query']);
    
    // Bind de parámetros de filtros
    foreach ($consulta_datos['params'] as $param => $value) {
        $stmt_datos->bindValue($param, $value);
    }
    
    // Bind de parámetros de paginación
    $stmt_datos->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt_datos->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt_datos->execute();
    $leads = $stmt_datos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error en consulta: " . $e->getMessage());
    $leads = [];
    $total_registros = 0;
    $total_paginas = 0;
}

// Función para exportar a Excel
function exportarLeadsExcel($leads) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="leads_exportados_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>TP</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Teléfono</th>";
    echo "<th>País</th><th>Campaña</th><th>Asignado</th><th>Estado</th>";
    echo "<th>Última Gestión</th><th>Fecha Última Gestión</th><th>Fecha Creación</th>";
    echo "</tr>";
    
    foreach ($leads as $lead) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($lead['TP'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Nombre'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Apellido'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Correo'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Numero'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Pais'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Campaña'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Asignado'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['Estado'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['UltimaGestion'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['FechaUltimaGestion'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($lead['FechaCreacion'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table></body></html>";
    exit;
}

// PROCESAR EXPORTACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    $leads_seleccionados = $_POST['leads_seleccionados'] ?? [];
    
    // Usar los filtros guardados en SESSION
    $filtros_export = $_SESSION['export_filtros'] ?? $filtros;
    
    if (empty($leads_seleccionados)) {
        // Exportar todos según filtros
        $consulta_export = construirConsulta($filtros_export, false);
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare($consulta_export['query']);
            
            foreach ($consulta_export['params'] as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            $leads_a_exportar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en exportación: " . $e->getMessage());
            $leads_a_exportar = $leads;
        }
    } else {
        // Exportar solo seleccionados
        try {
        $database = new Database();
        $db = $database->getConnection();

        // Crear placeholders dinámicos
        $placeholders = [];
        $params = [];

        foreach ($leads_seleccionados as $index => $tp) {
            $param = ":tp_$index";
            $placeholders[] = $param;
            $params[$param] = $tp;
        }

        $query = "SELECT * FROM clientes WHERE TP IN (" . implode(',', $placeholders) . ")";
        $stmt = $db->prepare($query);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();
        $leads_a_exportar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Error exportando seleccionados: " . $e->getMessage());
        $leads_a_exportar = [];
    }
    }
    
    if (!empty($leads_a_exportar)) {
        exportarLeadsExcel($leads_a_exportar);
    } else {
        $_SESSION['export_error'] = "No hay leads para exportar con los filtros seleccionados.";
        header('Location: exportar_leads.php');
        exit();
    }
    exit;
}

// Simular función si no existe
if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        return "Administrador";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de Exportación - CRM Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #95a5a6;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gray: #7f8c8d;
            --border: #e1e8ed;
            --shadow: 0 2px 15px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Layout completo */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a2530 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            color: #bdc3c7;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-menu i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }

        /* Contenido principal */
        .main-content {
            flex: 1;
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .main-header {
            background: white;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #eaeaea;
            flex-shrink: 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
        }

        .page-title h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .page-title p {
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details .welcome {
            color: #7f8c8d;
            font-size: 0.85rem;
        }

        .user-details .username {
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
            display: block;
        }

        /* Contenido */
        .content-wrapper {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            max-width: 100%;
        }

        /* Tarjeta principal */
        .main-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
            width: 100%;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h2 {
            font-size: 1.3rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            font-size: 1.5rem;
        }

        .results-count {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }

        /* Filtros aplicados */
        .applied-filters {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin: 20px;
        }

        .filters-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: #495057;
            font-weight: 600;
            font-size: 1rem;
        }

        .filters-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-tag {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.04);
        }

        .filter-tag .filter-name {
            color: #495057;
            font-weight: 500;
        }

        .filter-tag .filter-value {
            color: #6c757d;
        }

        .filter-tag i {
            color: #3498db;
            font-size: 0.9rem;
        }

        /* Controles */
        .controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-size-select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px 0 0 6px;
            font-size: 0.9rem;
            width: 80px;
            text-align: center;
            background: white;
            outline: none;
        }

        #btnAplicarPagina {
            padding: 8px 15px;
            border-radius: 0 6px 6px 0;
            margin-left: -1px;
            border-left: none;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Botones */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(149, 165, 166, 0.2);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        /* Tabla */
        .table-section {
            padding: 0 20px 20px;
        }

        .table-wrapper {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            background: white;
            max-height: 500px;
            overflow-y: auto;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        .leads-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        .leads-table thead {
            position: sticky;
            top: 0;
            background: #2c3e50;
            z-index: 10;
        }

        .leads-table th {
            padding: 15px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: none;
            white-space: nowrap;
        }

        .leads-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            color: #495057;
            white-space: nowrap;
        }

        .leads-table tbody tr {
            transition: background-color 0.2s;
        }

        .leads-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .leads-table tbody tr:nth-child(even) {
            background-color: #fcfdfe;
        }

        .leads-table tbody tr:nth-child(even):hover {
            background-color: #f8fafc;
        }

        /* Checkbox */
        .checkbox-cell {
            width: 50px;
            text-align: center;
        }

        .select-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3498db;
        }

        /* Estilos para el input de número */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            opacity: 1;
            height: 30px;
        }

        .page-size-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        /* Sin datos */
        .no-data {
            padding: 60px 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 1rem;
            font-style: italic;
        }

        .no-data i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
            display: block;
        }

        /* Paginación */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            gap: 15px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .page-link {
            padding: 8px 14px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
            font-weight: 500;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
            transform: translateY(-1px);
        }

        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
            font-weight: 600;
        }

        .page-info {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge.asignado {
            background: #e8f4fd;
            color: #3498db;
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* Scrollbar */
        .table-wrapper::-webkit-scrollbar,
        .table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .table-wrapper::-webkit-scrollbar-track,
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .table-wrapper::-webkit-scrollbar-thumb,
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover,
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 992px) {
            .content-wrapper {
                padding: 20px;
            }
            
            .controls-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .pagination-controls,
            .export-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .export-buttons {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 0 20px;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            .applied-filters {
                margin: 15px;
            }
            
            .filters-grid {
                flex-direction: column;
            }
            
            .table-section {
                padding: 0 15px 15px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .page-info {
                text-align: center;
            }
            
            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .page-size-select {
                width: 70px;
            }
        }

        @media (max-width: 480px) {
            .page-title h1 {
                font-size: 1.4rem;
            }
            
            .results-count {
                font-size: 0.8rem;
                padding: 6px 15px;
            }
            
            .leads-table th,
            .leads-table td {
                padding: 10px;
                font-size: 0.8rem;
            }
            
            .page-link {
                padding: 6px 10px;
                font-size: 0.8rem;
                min-width: 35px;
            }
        }
    </style>
</head>
<body>
    <!-- Botón para móvil -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>CRM Pro</h2>
            <p>Sistema de Gestión</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="leads.php"><i class="fas fa-users"></i> Leads</a>
            <a href="calendario.php"><i class="fas fa-calendar"></i> Calendario</a>
            <a href="subir_leads.php"><i class="fas fa-upload"></i> Subir Leads</a>
            <a href="eliminar_leads.php"><i class="fas fa-trash"></i> Eliminar Leads</a>
            <a href="usuarios.php"><i class="fas fa-user-cog"></i> Usuarios</a>
            <a href="asignar_usuarios.php"><i class="fas fa-user-check"></i> Asignar Usuarios</a>
            <a href="historico.php"><i class="fas fa-history"></i> Histórico</a>
            <a href="exportar_leads.php" class="active"><i class="fas fa-file-export"></i> Exportar Leads</a>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="app-container">
        <div class="main-content">
            <header class="main-header">
                <div class="header-content">
                    <div class="page-title">
                        <h1>Resultados de Exportación</h1>
                        <p>Selecciona los leads que deseas exportar</p>
                    </div>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr(getCurrentUsername(), 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <span class="welcome">Bienvenido,</span>
                            <span class="username"><?php echo getCurrentUsername(); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Tarjeta principal -->
                <div class="main-card">
                    <div class="card-header">
                        <h2><i class="fas fa-search"></i> Resultados de Búsqueda</h2>
                        <div class="results-count">
                            <?php echo number_format($total_registros, 0, ',', '.'); ?> leads encontrados
                        </div>
                    </div>

                    <!-- Filtros aplicados -->
                    <div class="applied-filters">
                        <div class="filters-title">
                            <i class="fas fa-filter"></i>
                            <span>Filtros aplicados:</span>
                        </div>
                        <div class="filters-grid">
                            <?php 
                            $hay_filtros = false;
                            foreach ($filtros as $nombre => $valores):
                                if (is_array($valores) && !empty($valores)):
                                    $hay_filtros = true;
                                    ?>
                                    <div class="filter-tag">
                                        <i class="fas fa-tag"></i>
                                        <span class="filter-name"><?php echo ucfirst($nombre); ?>:</span>
                                        <span class="filter-value"><?php echo htmlspecialchars(implode(', ', $valores)); ?></span>
                                    </div>
                                <?php elseif (!empty($valores)): 
                                    $hay_filtros = true;
                                    ?>
                                    <div class="filter-tag">
                                        <i class="fas fa-tag"></i>
                                        <span class="filter-name"><?php echo ucfirst($nombre); ?>:</span>
                                        <span class="filter-value"><?php echo htmlspecialchars($valores); ?></span>
                                    </div>
                                <?php endif;
                            endforeach;
                            
                            if (!$hay_filtros): ?>
                                <div class="filter-tag">
                                    <i class="fas fa-info-circle"></i>
                                    <span class="filter-value">Mostrando todos los leads (sin filtros aplicados)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Controles -->
                    <div class="controls-bar">
                        <div class="pagination-controls">
                            <label for="por_pagina">Mostrar:</label>
                            <input type="number" 
                                   id="por_pagina" 
                                   class="page-size-select"
                                   value="<?php echo $por_pagina; ?>"
                                   min="1" 
                                   max="5000"
                                   step="1"
                                   title="Escribe el número de registros por página (1-5000)">
                            <button type="button" id="btnAplicarPagina" class="btn btn-primary" style="padding: 8px 15px;">
                                <i class="fas fa-check"></i>
                            </button>
                            <span>registros por página</span>
                        </div>

                        <div class="export-buttons">
                            <button type="button" id="btnExportar" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i>
                                <span>Descargar Seleccionados</span>
                            </button>
                            <a href="exportar_leads.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <span>Volver a Filtros</span>
                            </a>
                            <button type="button" id="btnLimpiarFiltros" class="btn btn-danger">
                                <i class="fas fa-times"></i>
                                <span>Limpiar Filtros</span>
                            </button>
                            <a href="?modo=random&por_pagina=<?php echo $por_pagina; ?>" class="btn btn-danger">
                                <i class="fas fa-random"></i>
                                <span>Ordenar Random</span>
                            </a>
                            <a href="?por_pagina=<?php echo $por_pagina; ?>" class="btn btn-secondary">
                                <i class="fas fa-sort-numeric-down"></i>
                                <span>Orden Normal</span>
                            </a>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="table-section">
                        <div class="table-wrapper">
                            <div class="table-container">
                                <form method="POST" id="formSeleccion">
                                    <input type="hidden" name="exportar" value="1">
                                    <!-- Mantener filtros ocultos -->
                                    <?php foreach ($filtros as $key => $values): ?>
                                        <?php if (is_array($values)): ?>
                                            <?php foreach ($values as $value): ?>
                                                <?php if (!empty($value)): ?>
                                                    <input type="hidden" name="<?php echo $key; ?>[]" 
                                                           value="<?php echo htmlspecialchars($value); ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php elseif (!empty($values)): ?>
                                            <input type="hidden" name="<?php echo $key; ?>" 
                                                   value="<?php echo htmlspecialchars($values); ?>">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                    <table class="leads-table">
                                        <thead>
                                            <tr>
                                                <th class="checkbox-cell">
                                                    <input type="checkbox" id="seleccionar-todos" class="select-checkbox">
                                                </th>
                                                <th>TP</th>
                                                <th>Nombre</th>
                                                <th>Apellido</th>
                                                <th>Email</th>
                                                <th>Teléfono</th>
                                                <th>País</th>
                                                <th>Campaña</th>
                                                <th>Asignado</th>
                                                <th>Estado</th>
                                                <th>Última Gestión</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyLeads">
                                            <?php if (empty($leads)): ?>
                                                <tr>
                                                    <td colspan="11" class="no-data">
                                                        <i class="fas fa-inbox"></i>
                                                        <p>No se encontraron leads con los filtros aplicados</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leads as $index => $lead): ?>
                                                    <tr data-tp="<?php echo htmlspecialchars($lead['TP']); ?>">
                                                        <td class="checkbox-cell">
                                                            <input type="checkbox" name="leads_seleccionados[]" 
                                                                   value="<?php echo htmlspecialchars($lead['TP']); ?>" 
                                                                   class="select-checkbox checkbox-lead">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($lead['TP']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Nombre']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Apellido']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Correo']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Numero']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Pais']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['Campaña']); ?></td>
                                                        <td>
                                                            <span class="badge asignado">
                                                                <?php echo htmlspecialchars($lead['Asignado']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($lead['Estado']); ?></td>
                                                        <td><?php echo htmlspecialchars($lead['UltimaGestion']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination">
                                <?php if ($pagina_actual_num > 1): ?>
                                    <a href="?pagina=1&por_pagina=<?php echo $por_pagina; ?>" class="page-link" title="Primera">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="?pagina=<?php echo $pagina_actual_num - 1; ?>&por_pagina=<?php echo $por_pagina; ?>" class="page-link" title="Anterior">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php 
                                $inicio = max(1, $pagina_actual_num - 2);
                                $fin = min($total_paginas, $pagina_actual_num + 2);
                                
                                for ($i = $inicio; $i <= $fin; $i++): ?>
                                    <a href="?pagina=<?php echo $i; ?>&por_pagina=<?php echo $por_pagina; ?>" 
                                       class="page-link <?php echo $i == $pagina_actual_num ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($pagina_actual_num < $total_paginas): ?>
                                    <a href="?pagina=<?php echo $pagina_actual_num + 1; ?>&por_pagina=<?php echo $por_pagina; ?>" class="page-link" title="Siguiente">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="?pagina=<?php echo $total_paginas; ?>&por_pagina=<?php echo $por_pagina; ?>" class="page-link" title="Última">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="page-info">
                                Página <?php echo $pagina_actual_num; ?> de <?php echo $total_paginas; ?>
                                • Total: <?php echo number_format($total_registros, 0, ',', '.'); ?> leads
                                • Mostrando: <?php echo $por_pagina; ?> por página
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        
        // Toggle sidebar en móvil
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1200 && 
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) && 
                !mobileMenuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        
        // Seleccionar todos los leads
        const seleccionarTodos = document.getElementById('seleccionar-todos');
        const checkboxesLeads = document.querySelectorAll('.checkbox-lead');
        
        if (seleccionarTodos && checkboxesLeads.length > 0) {
            seleccionarTodos.addEventListener('change', function() {
                const isChecked = this.checked;
                checkboxesLeads.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                actualizarContadorSeleccionados();
            });
            
            // Manejar selección individual
            checkboxesLeads.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const todosSeleccionados = Array.from(checkboxesLeads).every(cb => cb.checked);
                    if (seleccionarTodos) {
                        seleccionarTodos.checked = todosSeleccionados;
                    }
                    actualizarContadorSeleccionados();
                });
                
                // Hacer clic en toda la fila para seleccionar
                const row = checkbox.closest('tr');
                if (row) {
                    row.addEventListener('click', function(e) {
                        if (e.target.type !== 'checkbox' && !e.target.classList.contains('btn')) {
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    });
                }
            });
        }
        
        // Actualizar contador de seleccionados
        function actualizarContadorSeleccionados() {
            const seleccionados = document.querySelectorAll('.checkbox-lead:checked').length;
            const btnExportar = document.getElementById('btnExportar');
            if (btnExportar) {
                if (seleccionados > 0) {
                    btnExportar.innerHTML = '<i class="fas fa-file-excel"></i> Descargar (' + seleccionados + ')';
                } else {
                    btnExportar.innerHTML = '<i class="fas fa-file-excel"></i> Descargar Seleccionados';
                }
            }
        }
        
        // Cambiar número de leads por página (input manual)
        const inputPorPagina = document.getElementById('por_pagina');
        const btnAplicarPagina = document.getElementById('btnAplicarPagina');
        
        if (inputPorPagina && btnAplicarPagina) {
            // Aplicar al cambiar valor
            btnAplicarPagina.addEventListener('click', aplicarCambioPagina);
            
            // Aplicar al presionar Enter
            inputPorPagina.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    aplicarCambioPagina();
                }
            });
            
            // Validar al cambiar
            inputPorPagina.addEventListener('change', function() {
                validarInputPagina(this);
            });
            
            // Validar al escribir
            inputPorPagina.addEventListener('input', function() {
                validarInputPagina(this);
            });
            
            // Agregar tooltip para explicar la función
            inputPorPagina.title = "Escribe cualquier número entre 1 y 5000. Presiona Enter o clic en el botón ✓ para aplicar.";
        }
        
        function aplicarCambioPagina() {
            const valor = parseInt(inputPorPagina.value);
            const min = 1;
            const max = 5000;
            
            if (isNaN(valor) || valor < min || valor > max) {
                alert(`Por favor, ingrese un número entre ${min} y ${max}`);
                inputPorPagina.value = min;
                inputPorPagina.focus();
                return;
            }
            
            // Mostrar indicador de carga
            const originalHTML = btnAplicarPagina.innerHTML;
            btnAplicarPagina.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btnAplicarPagina.disabled = true;
            
            // Construir URL con los parámetros actuales
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('por_pagina', valor);
            urlParams.set('pagina', 1); // Volver a la primera página
            
            // Redirigir
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }
        
        function validarInputPagina(input) {
            const valor = parseInt(input.value);
            const min = 1;
            const max = 5000;
            
            if (isNaN(valor)) {
                input.value = min;
            } else if (valor < min) {
                input.value = min;
            } else if (valor > max) {
                input.value = max;
            }
        }
        
        // Exportar leads
        const btnExportar = document.getElementById('btnExportar');
        if (btnExportar) {
            btnExportar.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.checkbox-lead:checked');
                if (checkboxes.length === 0) {
                    if (!confirm('No ha seleccionado ningún lead. ¿Desea exportar TODOS los leads de esta búsqueda?')) {
                        return;
                    }
                }
                
                // Mostrar indicador de carga
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                this.disabled = true;
                
                setTimeout(() => {
                    document.getElementById('formSeleccion').submit();
                }, 500);
                
                // Restaurar botón después de 5 segundos (por si hay error)
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }, 5000);
            });
        }
        
        // Limpiar filtros
        const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiarFiltros) {
            btnLimpiarFiltros.addEventListener('click', function() {
                if (confirm('¿Está seguro de que desea limpiar los filtros y volver a la página de búsqueda?')) {
                    window.location.href = 'exportar_leads.php?limpiar=1';
                }
            });
        }
        
        // Accesibilidad con teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+E para exportar
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                if (btnExportar) btnExportar.click();
            }
            
            // Ctrl+A para seleccionar todos
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                if (seleccionarTodos) {
                    seleccionarTodos.checked = !seleccionarTodos.checked;
                    seleccionarTodos.dispatchEvent(new Event('change'));
                }
            }
            
            // Ctrl+L para enfocar el input de paginación
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                if (inputPorPagina) {
                    inputPorPagina.focus();
                    inputPorPagina.select();
                }
            }
            
            // Escape para cerrar sidebar en móvil
            if (e.key === 'Escape' && window.innerWidth <= 1200) {
                sidebar.classList.remove('active');
            }
        });
        
        // Inicializar contador
        actualizarContadorSeleccionados();
    });
    </script>
</body>
</html>