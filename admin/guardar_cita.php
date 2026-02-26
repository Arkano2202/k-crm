<?php
// guardar_cita.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

include '../includes/session.php';
requireLogin();

function sendResponse($success, $data = [], $error = '') {
    $response = ['success' => $success];
    
    if ($success) {
        $response = array_merge($response, $data);
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener usuario actual
    $usuario_actual = getCurrentUser();
    
    if (!$usuario_actual || !isset($usuario_actual['usuario'])) {
        sendResponse(false, [], 'No se pudo obtener información del usuario');
    }
    
    // Validar datos recibidos
    $required_fields = ['tp', 'nombre', 'fecha', 'hora', 'minutos'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $errors[] = ucfirst($field) . ' no especificado';
        }
    }
    
    if (!empty($errors)) {
        sendResponse(false, [], implode(', ', $errors));
    }
    
    // Sanitizar y validar datos
    $tp = trim($_POST['tp']);
    $nombre = trim($_POST['nombre']);
    $fecha = trim($_POST['fecha']);
    $hora = trim($_POST['hora']);
    $minutos = trim($_POST['minutos']);
    $usuario_id = $usuario_actual['usuario'];
    
    // Validar fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        sendResponse(false, [], 'Formato de fecha inválido');
    }
    
    // Validar hora
    if (!preg_match('/^\d{2}$/', $hora) || $hora < 8 || $hora > 20) {
        sendResponse(false, [], 'Hora no válida (debe ser entre 08 y 20)');
    }
    
    // Validar minutos
    $minutos_validos = ['00', '10', '20', '30', '40', '50'];
    if (!in_array($minutos, $minutos_validos)) {
        sendResponse(false, [], 'Minutos no válidos');
    }
    
    // Construir hora completa
    $hora_completa = $hora . ':' . $minutos . ':00';
    
    // Sanitizar para SQL
    $tp = htmlspecialchars($tp, ENT_QUOTES, 'UTF-8');
    $nombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $usuario_id = htmlspecialchars($usuario_id, ENT_QUOTES, 'UTF-8');
    
    // Obtener el grupo del usuario desde la tabla users
    $query_grupo = "SELECT grupo FROM users WHERE usuario = :usuario_login LIMIT 1";
    $stmt_grupo = $db->prepare($query_grupo);
    $stmt_grupo->bindParam(':usuario_login', $usuario_id, PDO::PARAM_STR);
    
    if (!$stmt_grupo->execute()) {
        throw new Exception('Error al obtener información del grupo del usuario');
    }
    
    $grupo_data = $stmt_grupo->fetch(PDO::FETCH_ASSOC);
    
    if (!$grupo_data) {
        throw new Exception('Usuario no encontrado en la base de datos');
    }
    
    $grupo = $grupo_data['grupo'] ?? '';
    
    // Insertar cita
    $query_insert = "INSERT INTO citas (
                        titulo, 
                        descripcion, 
                        fecha, 
                        hora, 
                        usuario_id, 
                        grupo, 
                        notificado, 
                        creado_en
                     ) VALUES (
                        :titulo, 
                        :descripcion, 
                        :fecha, 
                        :hora, 
                        :usuario_id, 
                        :grupo, 
                        0, 
                        NOW()
                     )";
    
    $stmt_insert = $db->prepare($query_insert);
    
    $titulo = "Cita con " . $nombre;
    
    $stmt_insert->bindParam(':titulo', $titulo, PDO::PARAM_STR);
    $stmt_insert->bindParam(':descripcion', $tp, PDO::PARAM_STR);
    $stmt_insert->bindParam(':fecha', $fecha, PDO::PARAM_STR);
    $stmt_insert->bindParam(':hora', $hora_completa, PDO::PARAM_STR);
    $stmt_insert->bindParam(':usuario_id', $usuario_id, PDO::PARAM_STR);
    $stmt_insert->bindParam(':grupo', $grupo, PDO::PARAM_STR);
    
    if (!$stmt_insert->execute()) {
        $errorInfo = $stmt_insert->errorInfo();
        throw new Exception('Error al guardar la cita: ' . ($errorInfo[2] ?? 'Error desconocido'));
    }
    
    $id_cita = $db->lastInsertId();
    
    $response_data = [
        'message' => 'Cita asignada exitosamente',
        'id_cita' => $id_cita,
        'cliente' => $nombre,
        'tp' => $tp,
        'fecha' => $fecha,
        'hora' => $hora_completa,
        'usuario' => $usuario_id,
        'grupo' => $grupo,
        'notificado' => 0
    ];
    
    sendResponse(true, $response_data);
    
} catch (PDOException $e) {
    sendResponse(false, [], 'Error de base de datos: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, [], $e->getMessage());
}

exit();
?>