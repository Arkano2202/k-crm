<?php
// ver_cliente.php - CONEXIÓN CORRECTA

// Configurar headers para JSON ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');

// Desactivar visualización de errores para que no contaminen el JSON
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Incluir la conexión a la base de datos - MISMA CONEXIÓN QUE HEADER.PHP
    $database_path = '../config/database.php';
    
    if (!file_exists($database_path)) {
        throw new Exception('Archivo de conexión no encontrado: ' . $database_path);
    }
    
    require_once $database_path;
    
    // Crear la instancia de Database igual que en header.php
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar si la variable $db existe después de incluir el archivo
    if (!isset($db)) {
        throw new Exception('La variable $db no está definida después de crear la instancia de Database');
    }
    
    // Verificar si la conexión es válida
    if (!($db instanceof PDO)) {
        throw new Exception('La variable $db no es una instancia válida de PDO');
    }
    
    // Verificar si se recibió el parámetro TP
    if (!isset($_GET['tp']) || empty(trim($_GET['tp']))) {
        throw new Exception('Parámetro TP no especificado o vacío');
    }
    
    $tp = trim($_GET['tp']);
    
    // Obtener TODOS los campos necesarios para edición (MODIFICADO)
    $query_cliente = "SELECT 
                        TP, 
                        Nombre, 
                        Apellido, 
                        Numero, 
                        Correo, 
                        Estado, 
                        Pais,
                        Auxiliar, 
                        Asignado
                      FROM clientes WHERE TP = :tp";
    $stmt_cliente = $db->prepare($query_cliente);
    $stmt_cliente->bindValue(':tp', $tp, PDO::PARAM_STR);
    $stmt_cliente->execute();
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        throw new Exception('Cliente con TP "' . $tp . '" no encontrado en la base de datos');
    }
    
    // Obtener todas las notas del cliente
    $query_notas = "SELECT * FROM notas WHERE TP = :tp ORDER BY FechaUltimaGestion DESC";
    $stmt_notas = $db->prepare($query_notas);
    $stmt_notas->bindValue(':tp', $tp, PDO::PARAM_STR);
    $stmt_notas->execute();
    $notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar respuesta exitosa
    $response = [
        'success' => true,
        'cliente' => $cliente,
        'notas' => $notas,
        'total_notas' => count($notas)
    ];
    
    // Enviar respuesta JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Error específico de base de datos
    $error_response = [
        'success' => false, 
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ];
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Error general
    $error_response = [
        'success' => false, 
        'error' => $e->getMessage()
    ];
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}

// Asegurarse de que no se envía nada más después del JSON
exit();
?>