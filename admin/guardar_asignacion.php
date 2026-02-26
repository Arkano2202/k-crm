<?php
header('Content-Type: application/json; charset=utf-8');

include '../includes/session.php';
requireLogin();

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['tp_ids']) || !isset($input['nuevo_asignado'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$tp_ids = $input['tp_ids'];
$nuevo_asignado = $input['nuevo_asignado'];

if (empty($tp_ids)) {
    echo json_encode(['success' => false, 'error' => 'No se seleccionaron clientes']);
    exit;
}

function obtenerTipoUsuario($valor, $db) {
    $query = "SELECT tipo FROM users 
              WHERE nombre = :valor OR usuario = :valor 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':valor', $valor);
    $stmt->execute();
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ? (int)$res['tipo'] : null;
}

try {

    $db->beginTransaction(); // 🔥 TRANSACCIÓN GLOBAL

    $tipo_usuario_destino = obtenerTipoUsuario($nuevo_asignado, $db);

    if ($tipo_usuario_destino === null) {
        throw new Exception("Usuario destino no encontrado.");
    }

    $placeholders = implode(',', array_fill(0, count($tp_ids), '?'));

    // Obtener clientes antes
    $query_select = "SELECT TP, Nombre, Apellido, UltimaGestion 
                     FROM clientes 
                     WHERE TP IN ($placeholders)";
    $stmt_select = $db->prepare($query_select);
    $stmt_select->execute($tp_ids);
    $clientes_antes = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

    if (!$clientes_antes) {
        $clientes_antes = [];
    }

    $filas_afectadas = 0;

    // =============================
    // 🔥 ACTUALIZACIÓN OPTIMIZADA
    // =============================

    if ($tipo_usuario_destino == 1) {

        // Separar por estado para hacer solo 2 UPDATE máximo
        $tps_new = [];
        $tps_reciclado = [];

        foreach ($clientes_antes as $cliente) {
            if (!empty($cliente['UltimaGestion'])) {
                $tps_reciclado[] = $cliente['TP'];
            } else {
                $tps_new[] = $cliente['TP'];
            }
        }

        if (!empty($tps_new)) {
            $ph = implode(',', array_fill(0, count($tps_new), '?'));
            $query = "UPDATE clientes 
                      SET Asignado = ?, Estado = 'New'
                      WHERE TP IN ($ph)";
            $stmt = $db->prepare($query);
            $stmt->execute(array_merge([$nuevo_asignado], $tps_new));
            $filas_afectadas += $stmt->rowCount();
        }

        if (!empty($tps_reciclado)) {
            $ph = implode(',', array_fill(0, count($tps_reciclado), '?'));
            $query = "UPDATE clientes 
                      SET Asignado = ?, Estado = 'Reciclado'
                      WHERE TP IN ($ph)";
            $stmt = $db->prepare($query);
            $stmt->execute(array_merge([$nuevo_asignado], $tps_reciclado));
            $filas_afectadas += $stmt->rowCount();
        }

    } else {

        if (in_array($tipo_usuario_destino, [3,5])) {
            $estado_nuevo = 'Convertido';
        } else {
            $estado_nuevo = 'Asignado';
        }

        $query_update = "UPDATE clientes 
                         SET Asignado = ?, Estado = ?
                         WHERE TP IN ($placeholders)";

        $params = array_merge([$nuevo_asignado, $estado_nuevo], $tp_ids);
        $stmt_update = $db->prepare($query_update);
        $stmt_update->execute($params);

        $filas_afectadas = $stmt_update->rowCount();
    }

    // =============================
    // 🔥 HISTÓRICO OPTIMIZADO
    // =============================

    $usuario_actual = getCurrentUser();
    $usuario_session = $usuario_actual['usuario'] ?? 'Sistema';

    date_default_timezone_set('America/Bogota');
    $fecha_hora = date('Y-m-d H:i:s');

    $historico_stmt = $db->prepare("
        INSERT INTO historico 
        (tp, nombre_cliente, asignado, usuario_session, fecha_hora, accion, modulo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $historico_registros = 0;

    foreach ($clientes_antes as $cliente) {

        $nombre_completo = trim($cliente['Nombre'] . ' ' . $cliente['Apellido']);

        if ($historico_stmt->execute([
            $cliente['TP'],
            $nombre_completo,
            $nuevo_asignado,
            $usuario_session,
            $fecha_hora,
            'ASIGNACIÓN SEGÚN TIPO ' . $tipo_usuario_destino,
            'Asignar Individual'
        ])) {
            $historico_registros++;
        }
    }

    $db->commit(); // 🔥 CONFIRMAR TODO JUNTO

    echo json_encode([
        'success' => true,
        'data' => [
            'clientes_asignados' => $filas_afectadas,
            'registros_historico' => $historico_registros
        ]
    ]);

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
