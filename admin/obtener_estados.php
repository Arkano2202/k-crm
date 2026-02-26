<?php
// obtener_estados.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// INCLUIR EL SISTEMA DE SESIÓN EXISTENTE
include '../includes/session.php';
requireLogin();

try {
    // Incluir la conexión a la base de datos
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener usuario actual usando tu función existente
    $usuario_actual = getCurrentUser();
    
    // Verificar que tenemos los datos necesarios
    if (!$usuario_actual) {
        throw new Exception('No se pudo obtener información del usuario');
    }
    
    // Usar el grupo_id directamente desde la sesión (ya está disponible)
    $grupo_id = $usuario_actual['grupo_id'];
    $usuario = $usuario_actual['usuario'];
    
    // Obtener los estados que corresponden al grupo_id del usuario
    $query_estados = "SELECT Estado FROM estados WHERE grupo_id = :grupo_id ORDER BY Estado";
    $stmt_estados = $db->prepare($query_estados);
    $stmt_estados->bindValue(':grupo_id', $grupo_id, PDO::PARAM_INT);
    $stmt_estados->execute();
    
    $estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'estados' => $estados,
        'grupo_id' => $grupo_id,
        'usuario' => $usuario
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $error_response = [
        'success' => false, 
        'error' => $e->getMessage()
    ];
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}

exit();
?>