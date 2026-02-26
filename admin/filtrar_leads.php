<?php
// filtrar_leads.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

include '../includes/session.php';
requireLogin();

try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $usuario_actual = getCurrentUser();
    $grupo_id = $usuario_actual['grupo_id'];

    // Obtener parámetros de filtro
    $input = json_decode(file_get_contents('php://input'), true);
    $filtros = $input['filtros'] ?? [];
    
    // Construir consulta con filtros
    $where_conditions = [];
    $params = [];
    
    // Búsqueda básica (nombre, teléfono, correo)
    if (!empty($filtros['busqueda_basica'])) {
        $where_conditions[] = "(c.Nombre LIKE :busqueda OR c.Numero LIKE :busqueda OR c.Correo LIKE :busqueda)";
        $params[':busqueda'] = '%' . $filtros['busqueda_basica'] . '%';
    }
    
    // Filtro por países (múltiple)
    if (!empty($filtros['paises']) && is_array($filtros['paises'])) {
        $placeholders = [];
        foreach ($filtros['paises'] as $index => $pais) {
            $key = ':pais_' . $index;
            $placeholders[] = $key;
            $params[$key] = $pais;
        }
        $where_conditions[] = "c.Pais IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por apellidos (múltiple)
    if (!empty($filtros['apellidos']) && is_array($filtros['apellidos'])) {
        $placeholders = [];
        foreach ($filtros['apellidos'] as $index => $apellido) {
            $key = ':apellido_' . $index;
            $placeholders[] = $key;
            $params[$key] = $apellido;
        }
        $where_conditions[] = "c.Apellido IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por asignados (múltiple)
    if (!empty($filtros['asignados']) && is_array($filtros['asignados'])) {
        $placeholders = [];
        foreach ($filtros['asignados'] as $index => $asignado) {
            $key = ':asignado_' . $index;
            $placeholders[] = $key;
            $params[$key] = $asignado;
        }
        $where_conditions[] = "c.Asignado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por últimas gestiones (múltiple)
    if (!empty($filtros['gestiones']) && is_array($filtros['gestiones'])) {
        $placeholders = [];
        foreach ($filtros['gestiones'] as $index => $gestion) {
            $key = ':gestion_' . $index;
            $placeholders[] = $key;
            $params[$key] = $gestion;
        }
        $where_conditions[] = "c.UltimaGestion IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por estados (múltiple)
    if (!empty($filtros['estados']) && is_array($filtros['estados'])) {
        $placeholders = [];
        foreach ($filtros['estados'] as $index => $estado) {
            $key = ':estado_' . $index;
            $placeholders[] = $key;
            $params[$key] = $estado;
        }
        $where_conditions[] = "c.Estado IN (" . implode(',', $placeholders) . ")";
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Consulta para obtener los leads filtrados
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
              ORDER BY c.Nombre
              LIMIT 1000"; // Límite para no sobrecargar
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'leads' => $leads,
        'total' => count($leads),
        'filtros_aplicados' => $filtros
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>