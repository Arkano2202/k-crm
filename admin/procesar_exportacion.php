<?php
// procesar_exportacion.php
include '../includes/session.php';
requireLogin();

// Verificar que sea administrador
$usuario_actual = getCurrentUser();
if ($usuario_actual['tipo'] != 1) {
    header('Location: index.php');
    exit();
}

// Incluir database
include '../config/database.php';

// Procesar exportación
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener y validar filtros del POST
    $filtros = [
        'pais' => $_POST['pais'] ?? '',
        'campania' => $_POST['campania'] ?? '',
        'asignado' => $_POST['asignado'] ?? '',
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin' => $_POST['fecha_fin'] ?? '',
        'estado' => $_POST['estado'] ?? '',
        'ultima_gestion' => $_POST['ultima_gestion'] ?? ''
    ];
    
    // Construir consulta con filtros (misma lógica que antes)
    $where_conditions = [];
    $params = [];
    
    // Filtro por país
    if (!empty($filtros['pais'])) {
        $where_conditions[] = "c.Pais = :pais";
        $params[':pais'] = $filtros['pais'];
    }
    
    // Filtro por campaña
    if (!empty($filtros['campania'])) {
        $where_conditions[] = "c.Campaña = :campania";
        $params[':campania'] = $filtros['campania'];
    }
    
    // Filtro por asignado
    if (!empty($filtros['asignado'])) {
        $where_conditions[] = "c.Asignado = :asignado";
        $params[':asignado'] = $filtros['asignado'];
    }
    
    // Filtro por estado
    if (!empty($filtros['estado'])) {
        $where_conditions[] = "c.Estado = :estado";
        $params[':estado'] = $filtros['estado'];
    }
    
    // Filtro por última gestión
    if (!empty($filtros['ultima_gestion'])) {
        $where_conditions[] = "c.UltimaGestion = :ultima_gestion";
        $params[':ultima_gestion'] = $filtros['ultima_gestion'];
    }
    
    // Filtro por fecha de creación
    if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
        $where_conditions[] = "c.FechaCreacion BETWEEN :fecha_inicio AND :fecha_fin";
        $params[':fecha_inicio'] = $filtros['fecha_inicio'] . ' 00:00:00';
        $params[':fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
    } elseif (!empty($filtros['fecha_inicio'])) {
        $where_conditions[] = "c.FechaCreacion >= :fecha_inicio";
        $params[':fecha_inicio'] = $filtros['fecha_inicio'] . ' 00:00:00';
    } elseif (!empty($filtros['fecha_fin'])) {
        $where_conditions[] = "c.FechaCreacion <= :fecha_fin";
        $params[':fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Consulta para obtener los datos
    $query = "SELECT 
                c.TP,
                c.Nombre,
                c.Apellido,
                c.Correo,
                c.Numero,
                c.Pais,
                c.Campaña,
                c.Asignado,
                c.Estado,
                c.UltimaGestion,
                c.FechaUltimaGestion,
                c.FechaCreacion
            FROM clientes c
            $where_clause
            ORDER BY c.FechaCreacion DESC";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($leads)) {
        throw new Exception('No se encontraron leads con los filtros seleccionados');
    }
    
    // Configurar headers para descarga
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="leads_exportados_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Crear contenido Excel
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    $html .= '<head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<style>';
    $html .= 'table { border-collapse: collapse; width: 100%; }';
    $html .= 'th { background-color: #3498db; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }';
    $html .= 'td { padding: 6px; border: 1px solid #ddd; }';
    $html .= '.filtros { background-color: #f8f9fa; padding: 10px; margin-bottom: 20px; border: 1px solid #ddd; }';
    $html .= '</style>';
    $html .= '</head>';
    $html .= '<body>';
    
    // Información de filtros aplicados
    $html .= '<div class="filtros">';
    $html .= '<h3>Filtros Aplicados:</h3>';
    $html .= '<table>';
    
    if (!empty($filtros['pais'])) {
        $html .= '<tr><td><strong>País:</strong></td><td>' . htmlspecialchars($filtros['pais']) . '</td></tr>';
    }
    if (!empty($filtros['campania'])) {
        $html .= '<tr><td><strong>Campaña:</strong></td><td>' . htmlspecialchars($filtros['campania']) . '</td></tr>';
    }
    if (!empty($filtros['asignado'])) {
        $html .= '<tr><td><strong>Asignado:</strong></td><td>' . htmlspecialchars($filtros['asignado']) . '</td></tr>';
    }
    if (!empty($filtros['estado'])) {
        $html .= '<tr><td><strong>Estado:</strong></td><td>' . htmlspecialchars($filtros['estado']) . '</td></tr>';
    }
    if (!empty($filtros['ultima_gestion'])) {
        $html .= '<tr><td><strong>Última Gestión:</strong></td><td>' . htmlspecialchars($filtros['ultima_gestion']) . '</td></tr>';
    }
    if (!empty($filtros['fecha_inicio']) || !empty($filtros['fecha_fin'])) {
        $html .= '<tr><td><strong>Fecha Creación:</strong></td><td>';
        if (!empty($filtros['fecha_inicio'])) {
            $html .= 'Desde: ' . htmlspecialchars($filtros['fecha_inicio']);
        }
        if (!empty($filtros['fecha_fin'])) {
            $html .= ' Hasta: ' . htmlspecialchars($filtros['fecha_fin']);
        }
        $html .= '</td></tr>';
    }
    
    $html .= '<tr><td><strong>Total Leads:</strong></td><td>' . count($leads) . '</td></tr>';
    $html .= '<tr><td><strong>Fecha Exportación:</strong></td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';
    
    // Tabla de datos
    $html .= '<table>';
    $html .= '<tr>';
    $html .= '<th>TP</th>';
    $html .= '<th>Nombre</th>';
    $html .= '<th>Apellido</th>';
    $html .= '<th>Email</th>';
    $html .= '<th>Teléfono</th>';
    $html .= '<th>País</th>';
    $html .= '<th>Campaña</th>';
    $html .= '<th>Asignado</th>';
    $html .= '<th>Estado</th>';
    $html .= '<th>Última Gestión</th>';
    $html .= '<th>Fecha Última Gestión</th>';
    $html .= '<th>Fecha Creación</th>';
    $html .= '</tr>';
    
    foreach ($leads as $lead) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($lead['TP'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Nombre'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Apellido'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Correo'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Numero'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Pais'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Campaña'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Asignado'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['Estado'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['UltimaGestion'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['FechaUltimaGestion'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($lead['FechaCreacion'] ?? '') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    $html .= '</body>';
    $html .= '</html>';
    
    echo $html;
    exit;
    
} catch (Exception $e) {
    // Si hay error, redirigir de vuelta con mensaje de error
    $_SESSION['export_error'] = 'Error al exportar: ' . $e->getMessage();
    header('Location: exportar_leads.php');
    exit();
}