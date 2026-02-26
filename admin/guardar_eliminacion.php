<?php
// Configurar manejo de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Incluir session primero
include '../includes/session.php';

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado. Usuario no logueado.']);
    exit;
}

// Función para registrar en el histórico (igual que en actualizar_leads.php)
function registrarHistorico($tp, $nombre_cliente, $asignado, $accion, $modulo) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Obtener usuario actual
        $usuario_actual = getCurrentUser();
        $usuario_session = $usuario_actual['usuario'] ?? 'Sistema';
        
        // Configurar zona horaria de Bogotá
        date_default_timezone_set('America/Bogota');
        $fecha_hora = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO historico 
                 (tp, nombre_cliente, asignado, usuario_session, fecha_hora, accion, modulo) 
                 VALUES (:tp, :nombre_cliente, :asignado, :usuario_session, :fecha_hora, :accion, :modulo)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tp', $tp);
        $stmt->bindParam(':nombre_cliente', $nombre_cliente);
        $stmt->bindParam(':asignado', $asignado);
        $stmt->bindParam(':usuario_session', $usuario_session);
        $stmt->bindParam(':fecha_hora', $fecha_hora);
        $stmt->bindParam(':accion', $accion);
        $stmt->bindParam(':modulo', $modulo);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error al registrar histórico: " . $e->getMessage());
        return false;
    }
}

header('Content-Type: application/json');

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos JSON válidos: ' . json_last_error_msg()]);
    exit;
}

if (!isset($input['tp_ids']) || empty($input['tp_ids'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron clientes para eliminar']);
    exit;
}

$tp_ids = $input['tp_ids'];

try {
    // DEBUG: Registrar inicio del proceso
    error_log("Iniciando eliminación de clientes: " . implode(', ', $tp_ids));
    
    // Incluir database.php
    include '../config/database.php';
    
    // Crear instancia de Database
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar conexión
    if (!$db) {
        throw new Exception('No se pudo establecer conexión con la base de datos');
    }
    
    // Probar la conexión
    $test = $db->query('SELECT 1');
    if (!$test) {
        throw new Exception('Error al probar conexión a la base de datos');
    }
    
    error_log("Conexión a BD establecida correctamente");

    // Iniciar transacción
    $db->beginTransaction();
    
    $clientes_eliminados = 0;
    $errores = [];
    
    foreach ($tp_ids as $tp) {
        try {
            error_log("Procesando cliente: $tp");
            
            // PASO 1: Obtener todos los datos del cliente
            $query_select = "SELECT * FROM clientes WHERE TP = :tp";
            $stmt_select = $db->prepare($query_select);
            $stmt_select->bindValue(':tp', $tp, PDO::PARAM_STR);
            $stmt_select->execute();
            
            $cliente = $stmt_select->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                $errores[] = "Cliente con TP $tp no encontrado";
                error_log("Cliente $tp no encontrado");
                continue;
            }
            
            error_log("Cliente encontrado: " . $cliente['Nombre'] . " " . $cliente['Apellido']);
            
            // REGISTRAR EN HISTÓRICO ANTES DE ELIMINAR
            $nombre_cliente = ($cliente['Nombre'] ?? '') . ' ' . ($cliente['Apellido'] ?? '');
            $asignado = $cliente['Asignado'] ?? 'No asignado';
            $accion = 'ELIMINACIÓN';
            $modulo = 'ELIMINAR LEADS';
            
            $registro_historico = registrarHistorico($tp, $nombre_cliente, $asignado, $accion, $modulo);
            
            if (!$registro_historico) {
                error_log("Advertencia: No se pudo registrar en histórico para TP $tp");
                // No detenemos el proceso por un error en el histórico
            } else {
                error_log("Registro en histórico exitoso para TP $tp");
            }
            
            // Verificar si la tabla clientes_eliminados existe
            $check_table = $db->query("SHOW TABLES LIKE 'clientes_eliminados'");
            if ($check_table->rowCount() === 0) {
                throw new Exception('La tabla clientes_eliminados no existe en la base de datos');
            }
            
            // PASO 2: Insertar en clientes_eliminados (SIN EL CAMPO ID)
            $query_insert = "INSERT INTO clientes_eliminados (
                Nombre, Apellido, Correo, Numero, Auxiliar, Pais, TP, 
                Campaña, grupo_id, Asignado, FechaCreacion, FechaAsignacion, 
                Estado, UltimaGestion, FechaUltimaGestion, fecha_eliminacion
            ) VALUES (
                :nombre, :apellido, :correo, :numero, :auxiliar, :pais, :tp,
                :campania, :grupo_id, :asignado, :fecha_creacion, :fecha_asignacion,
                :estado, :ultima_gestion, :fecha_ultima_gestion, NOW()
            )";
            
            $stmt_insert = $db->prepare($query_insert);
            
            // Ahora usamos bindParam para cada valor individualmente
            $stmt_insert->bindValue(':nombre', $cliente['Nombre'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':apellido', $cliente['Apellido'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':correo', $cliente['Correo'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':numero', $cliente['Numero'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':auxiliar', $cliente['Auxiliar'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':pais', $cliente['Pais'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':tp', $cliente['TP'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':campania', $cliente['Campaña'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':grupo_id', $cliente['grupo_id'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':asignado', $cliente['Asignado'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':fecha_creacion', $cliente['FechaCreacion'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':fecha_asignacion', $cliente['FechaAsignacion'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':estado', $cliente['Estado'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':ultima_gestion', $cliente['UltimaGestion'] ?? null, PDO::PARAM_STR);
            $stmt_insert->bindValue(':fecha_ultima_gestion', $cliente['FechaUltimaGestion'] ?? null, PDO::PARAM_STR);
            
            $result_insert = $stmt_insert->execute();
            
            if (!$result_insert) {
                $errorInfo = $stmt_insert->errorInfo();
                throw new Exception("Error al insertar en clientes_eliminados para TP $tp: " . $errorInfo[2]);
            }
            
            error_log("Cliente $tp insertado en clientes_eliminados");
            
            // PASO 3: Eliminar de la tabla clientes
            $query_delete = "DELETE FROM clientes WHERE TP = :tp";
            $stmt_delete = $db->prepare($query_delete);
            $stmt_delete->bindValue(':tp', $tp, PDO::PARAM_STR);
            $result_delete = $stmt_delete->execute();
            
            if (!$result_delete) {
                throw new Exception("Error al eliminar de clientes para TP $tp");
            }
            
            error_log("Cliente $tp eliminado de clientes");
            
            $clientes_eliminados++;
            
        } catch (PDOException $e) {
            $error_msg = "Error procesando cliente $tp: " . $e->getMessage();
            $errores[] = $error_msg;
            error_log($error_msg);
            continue;
        } catch (Exception $e) {
            $error_msg = "Error procesando cliente $tp: " . $e->getMessage();
            $errores[] = $error_msg;
            error_log($error_msg);
            continue;
        }
    }
    
    // Confirmar transacción
    $db->commit();
    
    error_log("Transacción completada. Clientes eliminados: $clientes_eliminados");
    
    if ($clientes_eliminados > 0) {
        $response = [
            'success' => true,
            'data' => [
                'clientes_eliminados' => $clientes_eliminados,
                'total_procesados' => count($tp_ids),
                'registros_historicos' => $clientes_eliminados
            ]
        ];
        
        if (!empty($errores)) {
            $response['advertencias'] = $errores;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'No se pudo eliminar ningún cliente. Errores: ' . implode('; ', $errores)
        ]);
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("Transacción revertida debido a error");
    }
    
    $error_message = "Error general en guardar_eliminacion.php: " . $e->getMessage();
    error_log($error_message);
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}