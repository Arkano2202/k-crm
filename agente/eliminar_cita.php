<?php
include '../includes/session.php';
requireLogin();

// Verificar que se pasó un ID
if (!isset($_GET['id'])) {
    header('Location: calendar.php?error=ID de cita no especificado');
    exit;
}

$cita_id = (int)$_GET['id'];

try {
    // Incluir la clase Database
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Primero obtener la cita para verificar permisos
    $query = "SELECT usuario_id FROM citas WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $cita_id);
    $stmt->execute();
    
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cita) {
        header('Location: calendar.php?error=Cita no encontrada');
        exit;
    }
    
    // Verificar permisos (solo admin o el dueño de la cita puede eliminar)
    $usuario_actual = getCurrentUser();
    if (!isAdmin() && $cita['usuario_id'] !== $usuario_actual['usuario']) {
        header('Location: calendar_simple.php?error=No tienes permisos para eliminar esta cita');
        exit;
    }
    
    // Eliminar la cita
    $query = "DELETE FROM citas WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $cita_id);
    
    if ($stmt->execute()) {
        header('Location: calendar.php?success=Cita eliminada exitosamente');
    } else {
        header('Location: calendar.php?error=Error al eliminar la cita');
    }
    
} catch (Exception $e) {
    header('Location: calendar.php?error=Error al eliminar cita: ' . $e->getMessage());
}