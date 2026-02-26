<?php
// guardar_consulta.php - VERSIÓN MODIFICADA
session_start();
require_once '../includes/session.php';

// Incluir archivo de conexión según tu configuración
if (file_exists('../config/database.php')) {
    require_once '../config/database.php';
} elseif (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} 

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['user_id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $filtros = $_POST['filtros'];
    $tipo = $_POST['tipo'] ?? 'exportacion'; // NUEVO: tipo por defecto 'exportacion'
    
    // Determinar desde qué página viene (para redirección)
    $pagina_origen = 'exportar_leads.php'; // Por defecto
    
    // Si el tipo es 'asignacion', viene de asignar_individual.php
    if ($tipo === 'asignacion') {
        $pagina_origen = 'asignar_individual.php';
    }
    
    // Validaciones básicas
    if (empty($nombre) || empty($filtros)) {
        $_SESSION['error'] = 'Nombre y filtros son requeridos';
        header('Location: ' . $pagina_origen);
        exit();
    }
    
    try {
        // Método 1: PDO (MODIFICADO)
        if (isset($conn) && $conn instanceof PDO) {
            $query = "INSERT INTO consultas_guardadas (usuario_id, nombre, descripcion, filtros, tipo) 
                      VALUES (:usuario_id, :nombre, :descripcion, :filtros, :tipo)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':filtros', $filtros);
            $stmt->bindParam(':tipo', $tipo);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Consulta guardada correctamente';
            } else {
                $_SESSION['error'] = 'Error al guardar la consulta';
            }
        }
        // Método 2: mysqli (MODIFICADO)
        elseif (isset($conn) && $conn instanceof mysqli) {
            $nombre = $conn->real_escape_string($nombre);
            $descripcion = $conn->real_escape_string($descripcion);
            $filtros = $conn->real_escape_string($filtros);
            $tipo = $conn->real_escape_string($tipo);
            
            $query = "INSERT INTO consultas_guardadas (usuario_id, nombre, descripcion, filtros, tipo) 
                      VALUES ('$usuario_id', '$nombre', '$descripcion', '$filtros', '$tipo')";
            
            if ($conn->query($query)) {
                $_SESSION['mensaje'] = 'Consulta guardada correctamente';
            } else {
                $_SESSION['error'] = 'Error al guardar la consulta: ' . $conn->error;
            }
        }
        // Método 3: Clase Database
        elseif (class_exists('Database')) {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "INSERT INTO consultas_guardadas (usuario_id, nombre, descripcion, filtros, tipo) 
                      VALUES (:usuario_id, :nombre, :descripcion, :filtros, :tipo)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':filtros', $filtros);
            $stmt->bindParam(':tipo', $tipo);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Consulta guardada correctamente';
            } else {
                $_SESSION['error'] = 'Error al guardar la consulta';
            }
        } else {
            $_SESSION['error'] = 'No se pudo establecer conexión con la base de datos';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    // Redirigir a la página de origen
    header('Location: ' . $pagina_origen);
    exit();
}
?>