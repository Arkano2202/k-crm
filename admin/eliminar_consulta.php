<?php
// eliminar_consulta.php - VERSIÓN MODIFICADA
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

if (isset($_GET['id'])) {
    $consulta_id = $_GET['id'];
    $usuario_id = $_SESSION['user_id'];
    $tipo = $_GET['tipo'] ?? 'exportacion'; // NUEVO: obtener tipo
    
    try {
        // Método 1: PDO
        if (isset($conn) && $conn instanceof PDO) {
            $query = "DELETE FROM consultas_guardadas WHERE id = :id AND usuario_id = :usuario_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $consulta_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Consulta eliminada correctamente';
            } else {
                $_SESSION['error'] = 'Error al eliminar la consulta';
            }
        }
        // Método 2: mysqli
        elseif (isset($conn) && $conn instanceof mysqli) {
            $consulta_id = $conn->real_escape_string($consulta_id);
            $usuario_id = $conn->real_escape_string($usuario_id);
            
            $query = "DELETE FROM consultas_guardadas WHERE id = '$consulta_id' AND usuario_id = '$usuario_id'";
            
            if ($conn->query($query)) {
                $_SESSION['mensaje'] = 'Consulta eliminada correctamente';
            } else {
                $_SESSION['error'] = 'Error al eliminar la consulta: ' . $conn->error;
            }
        }
        // Método 3: Clase Database
        elseif (class_exists('Database')) {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "DELETE FROM consultas_guardadas WHERE id = :id AND usuario_id = :usuario_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $consulta_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = 'Consulta eliminada correctamente';
            } else {
                $_SESSION['error'] = 'Error al eliminar la consulta';
            }
        } else {
            $_SESSION['error'] = 'No se pudo establecer conexión con la base de datos';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Determinar a dónde redirigir basado en el tipo
$pagina_destino = 'exportar_leads.php'; // Por defecto
if ($tipo === 'asignacion') {
    $pagina_destino = 'asignar_individual.php';
}

// Redirigir según el tipo
header('Location: ' . $pagina_destino);
exit();
?>