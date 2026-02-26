<?php
// obtener_cliente_edicion.php

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
    
    // Verificar si se recibió el parámetro TP
    if (!isset($_GET['tp']) || empty(trim($_GET['tp']))) {
        throw new Exception('Parámetro TP no especificado');
    }
    
    $tp = trim($_GET['tp']);
    
    // Obtener datos del cliente para edición
    $query_cliente = "SELECT TP, Nombre, Apellido, Numero, Correo, Pais 
                      FROM clientes 
                      WHERE TP = :tp";
    $stmt_cliente = $db->prepare($query_cliente);
    $stmt_cliente->bindValue(':tp', $tp, PDO::PARAM_STR);
    $stmt_cliente->execute();
    
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        throw new Exception('Cliente con TP "' . $tp . '" no encontrado');
    }
    
    $response = [
        'success' => true,
        'cliente' => $cliente
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