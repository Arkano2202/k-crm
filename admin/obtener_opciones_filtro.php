<?php
// obtener_opciones_filtro.php

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

    // Obtener opciones únicas para los filtros
    $opciones = [];
    
    // Países únicos
    $query_paises = "SELECT DISTINCT Pais FROM clientes WHERE Pais IS NOT NULL AND Pais != '' ORDER BY Pais";
    $stmt_paises = $db->query($query_paises);
    $opciones['paises'] = $stmt_paises->fetchAll(PDO::FETCH_COLUMN);
    
    // Apellidos únicos
    $query_apellidos = "SELECT DISTINCT Apellido FROM clientes WHERE Apellido IS NOT NULL AND Apellido != '' ORDER BY Apellido";
    $stmt_apellidos = $db->query($query_apellidos);
    $opciones['apellidos'] = $stmt_apellidos->fetchAll(PDO::FETCH_COLUMN);
    
    // Asignados únicos
    $query_asignados = "SELECT DISTINCT Asignado FROM clientes WHERE Asignado IS NOT NULL AND Asignado != '' ORDER BY Asignado";
    $stmt_asignados = $db->query($query_asignados);
    $opciones['asignados'] = $stmt_asignados->fetchAll(PDO::FETCH_COLUMN);
    
    // Últimas gestiones únicas
    $query_gestiones = "SELECT DISTINCT UltimaGestion FROM clientes WHERE UltimaGestion IS NOT NULL AND UltimaGestion != '' ORDER BY UltimaGestion";
    $stmt_gestiones = $db->query($query_gestiones);
    $opciones['gestiones'] = $stmt_gestiones->fetchAll(PDO::FETCH_COLUMN);
    
    // Estados únicos
    $query_estados = "SELECT DISTINCT Estado FROM clientes WHERE Estado IS NOT NULL AND Estado != '' ORDER BY Estado";
    $stmt_estados = $db->query($query_estados);
    $opciones['estados'] = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
    
    $response = [
        'success' => true,
        'opciones' => $opciones
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