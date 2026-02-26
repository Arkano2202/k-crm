<?php
// guardar_nota.php

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
    
    // Usar los datos directamente desde la sesión
    $grupo_id = $usuario_actual['grupo_id'];
    $nombre_usuario = $usuario_actual['nombre']; // Nombre completo del usuario
    $usuario = $usuario_actual['usuario']; // Username
    
    // Validar datos recibidos
    if (!isset($_POST['tp']) || empty(trim($_POST['tp']))) {
        throw new Exception('TP del cliente no especificado');
    }
    
    if (!isset($_POST['gestion']) || empty(trim($_POST['gestion']))) {
        throw new Exception('Tipo de gestión no especificado');
    }
    
    if (!isset($_POST['descripcion']) || empty(trim($_POST['descripcion']))) {
        throw new Exception('Descripción de la nota no especificada');
    }
    
    $tp = trim($_POST['tp']);
    $gestion = trim($_POST['gestion']);
    $descripcion = trim($_POST['descripcion']);
    
    // Iniciar transacción para asegurar que ambas operaciones se completen
    $db->beginTransaction();
    
    try {
        // 1. Insertar la nota en la base de datos
        $query_insert = "INSERT INTO notas (TP, UltimaGestion, FechaUltimaGestion, Descripcion, user, grupo_id) 
                         VALUES (:tp, :gestion, NOW(), :descripcion, :user, :grupo_id)";
        
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->bindValue(':tp', $tp, PDO::PARAM_STR);
        $stmt_insert->bindValue(':gestion', $gestion, PDO::PARAM_STR);
        $stmt_insert->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt_insert->bindValue(':user', $nombre_usuario, PDO::PARAM_STR); // Guardar nombre completo
        $stmt_insert->bindValue(':grupo_id', $grupo_id, PDO::PARAM_INT);
        
        if (!$stmt_insert->execute()) {
            throw new Exception('Error al insertar la nota en la base de datos');
        }
        
        $id_nota = $db->lastInsertId();
        
        // 2. Actualizar los campos en la tabla clientes
        $query_update = "UPDATE clientes 
                         SET UltimaGestion = :gestion, 
                             FechaUltimaGestion = NOW() 
                         WHERE TP = :tp";
        
        $stmt_update = $db->prepare($query_update);
        $stmt_update->bindValue(':gestion', $gestion, PDO::PARAM_STR);
        $stmt_update->bindValue(':tp', $tp, PDO::PARAM_STR);
        
        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar los datos del cliente');
        }
        
        // Confirmar la transacción
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => 'Nota guardada y cliente actualizado exitosamente',
            'id_nota' => $id_nota,
            'usuario' => $nombre_usuario,
            'grupo_id' => $grupo_id,
            'cliente_actualizado' => true
        ];
        
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        $db->rollBack();
        throw $e;
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