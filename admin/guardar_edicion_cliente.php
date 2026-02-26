<?php
// guardar_edicion_cliente.php

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
    
    // Validar datos recibidos
    $camposRequeridos = ['tp', 'nombre', 'apellido', 'numero', 'correo', 'pais', 'auxiliar'];
    
    foreach ($camposRequeridos as $campo) {
        if (!isset($_POST[$campo]) || empty(trim($_POST[$campo]))) {
            throw new Exception('El campo ' . $campo . ' es requerido');
        }
    }
    
    $tp = trim($_POST['tp']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $numero = trim($_POST['numero']);
    $correo = trim($_POST['correo']);
    $pais = trim($_POST['pais']);
    $auxiliar = trim($_POST['auxiliar']);
    
    // Validar formato de email
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del email no es válido');
    }
    
    // Actualizar el cliente en la base de datos
    $query_update = "UPDATE clientes 
                     SET Nombre = :nombre, 
                         Apellido = :apellido, 
                         Numero = :numero, 
                         Correo = :correo, 
                         Pais = :pais,
                         Auxiliar = :auxiliar
                     WHERE TP = :tp";
    
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt_update->bindValue(':apellido', $apellido, PDO::PARAM_STR);
    $stmt_update->bindValue(':numero', $numero, PDO::PARAM_STR);
    $stmt_update->bindValue(':correo', $correo, PDO::PARAM_STR);
    $stmt_update->bindValue(':pais', $pais, PDO::PARAM_STR);
    $stmt_update->bindValue(':auxiliar', $auxiliar, PDO::PARAM_STR);
    $stmt_update->bindValue(':tp', $tp, PDO::PARAM_STR);
    
    if ($stmt_update->execute()) {
        $response = [
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'tp' => $tp
        ];
    } else {
        throw new Exception('Error al ejecutar la actualización en la base de datos');
    }
    
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