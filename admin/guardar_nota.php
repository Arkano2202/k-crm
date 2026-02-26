<?php
// guardar_nota.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// ESTABLECER ZONA HORARIA DE COLOMBIA
date_default_timezone_set('America/Bogota');

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

    if (!$usuario_actual) {
        throw new Exception('No se pudo obtener información del usuario');
    }

    $grupo_id = $usuario_actual['grupo_id'];
    $nombre_usuario = $usuario_actual['nombre'];
    $usuario = $usuario_actual['usuario'];

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

    // Hora actual en Colombia
    $fecha_colombia = date('Y-m-d H:i:s');

    // 🔍 1. OBTENER id_cliente DESDE LA TABLA CLIENTES
    $query_cliente = "SELECT id FROM clientes WHERE TP = :tp LIMIT 1";
    $stmt_cliente = $db->prepare($query_cliente);
    $stmt_cliente->bindValue(':tp', $tp, PDO::PARAM_STR);
    $stmt_cliente->execute();

    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new Exception('No existe un cliente con ese TP');
    }

    $id_cliente = $cliente['id']; // <<--- requerido por el INSERT

    // Iniciar transacción
    $db->beginTransaction();

    try {

        // 2️⃣ INSERT con id_cliente agregado
        $query_insert = "INSERT INTO notas 
            (TP, UltimaGestion, FechaUltimaGestion, Descripcion, user, grupo_id, id_cliente) 
            VALUES 
            (:tp, :gestion, :fecha_colombia, :descripcion, :user, :grupo_id, :id_cliente)";

        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
        $stmt_insert->bindValue(':tp', $tp, PDO::PARAM_STR);
        $stmt_insert->bindValue(':gestion', $gestion, PDO::PARAM_STR);
        $stmt_insert->bindValue(':fecha_colombia', $fecha_colombia, PDO::PARAM_STR);
        $stmt_insert->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt_insert->bindValue(':user', $nombre_usuario, PDO::PARAM_STR);
        $stmt_insert->bindValue(':grupo_id', $grupo_id, PDO::PARAM_INT);

        if (!$stmt_insert->execute()) {
            throw new Exception('Error al insertar la nota en la base de datos');
        }

        $id_nota = $db->lastInsertId();

        // 3️⃣ Actualizar cliente
        $query_update = "UPDATE clientes 
                         SET UltimaGestion = :gestion,
                             FechaUltimaGestion = :fecha_colombia
                         WHERE TP = :tp";

        $stmt_update = $db->prepare($query_update);
        $stmt_update->bindValue(':gestion', $gestion, PDO::PARAM_STR);
        $stmt_update->bindValue(':fecha_colombia', $fecha_colombia, PDO::PARAM_STR);
        $stmt_update->bindValue(':tp', $tp, PDO::PARAM_STR);

        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar los datos del cliente');
        }

        $db->commit();

        $response = [
            'success' => true,
            'message' => 'Nota guardada y cliente actualizado exitosamente',
            'id_nota' => $id_nota,
            'usuario' => $nombre_usuario,
            'grupo_id' => $grupo_id,
            'cliente_actualizado' => true,
            'fecha_guardada' => $fecha_colombia
        ];

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit();
?>
