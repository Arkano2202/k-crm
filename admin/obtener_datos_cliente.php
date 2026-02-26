<?php
// obtener_datos_cliente.php
include '../config/database.php';
include '../includes/session.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Obtener TP del cliente
$tp = $_GET['tp'] ?? '';
if (empty($tp)) {
    echo json_encode(['success' => false, 'error' => 'TP no especificado']);
    exit;
}

try {
    // Obtener datos actualizados del cliente
    $query = "SELECT 
                TP,
                Nombre,
                Apellido,
                Estado,
                Pais,
                UltimaGestion,
                FechaUltimaGestion,
                Asignado
              FROM clientes 
              WHERE TP = :tp";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':tp', $tp);
    $stmt->execute();
    
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente) {
        echo json_encode([
            'success' => true,
            'cliente' => $cliente
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Cliente no encontrado'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>