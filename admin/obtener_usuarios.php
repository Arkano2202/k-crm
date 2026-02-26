<?php
// obtener_usuarios.php
header('Content-Type: application/json; charset=utf-8');

// Incluir la configuración de la base de datos primero
require_once '../config/database.php';

// Inicializar la conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Luego incluir session
include '../includes/session.php';
requireLogin();

try {
    // Consulta para obtener usuarios activos tipo 2 y 3
    $query = "SELECT id, usuario, Nombre as nombre 
              FROM users 
              WHERE tipo IN (1, 2, 3, 4, 5) 
              ORDER BY Nombre";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar si hay usuarios
    if (empty($usuarios)) {
        echo json_encode([
            'success' => true,
            'usuarios' => [],
            'message' => 'No hay usuarios de tipo 2 o 3 disponibles'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'total' => count($usuarios),
        'message' => count($usuarios) . ' usuarios tipo 2 y 3 cargados'
    ]);
    
} catch (PDOException $e) {
    // Log del error
    error_log("Error en obtener_usuarios.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error general: ' . $e->getMessage()
    ]);
}
?>