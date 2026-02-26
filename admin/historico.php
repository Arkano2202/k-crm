<?php
// historico.php
$pagina_actual = 'history';

include '../includes/session.php';
requireLogin();

include '../includes/header.php';
include '../includes/sidebar.php';

// ✅ AGREGAR ESTA LÍNEA - Obtener usuario actual
$usuario_actual = getCurrentUser();

// Configuración de paginación
$registros_por_pagina = 50;

$pagina_actual_num = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual_num - 1) * $registros_por_pagina;

// Parámetro de filtro por TP
$filtro_tp = isset($_GET['tp']) ? trim($_GET['tp']) : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Construir consulta con filtro por TP usando LIKE
    $query = "SELECT * FROM historico WHERE 1=1";
    $params = [];
    
    if (!empty($filtro_tp)) {
        $query .= " AND tp LIKE :tp";
        $params[':tp'] = '%' . $filtro_tp . '%';
    }
    
    // Consulta para total
    $query_total = "SELECT COUNT(*) as total FROM historico WHERE 1=1";
    
    if (!empty($filtro_tp)) {
        $query_total .= " AND tp LIKE :tp";
    }
    
    $stmt_total = $db->prepare($query_total);
    if (!empty($filtro_tp)) {
        $stmt_total->bindValue(':tp', '%' . $filtro_tp . '%');
    }
    $stmt_total->execute();
    $total_registros = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Consulta para datos con paginación
    $query .= " ORDER BY fecha_hora DESC LIMIT $registros_por_pagina OFFSET $offset";
    $stmt = $db->prepare($query);
    /*$query .= " ORDER BY fecha_hora DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);*/
    
    if (!empty($filtro_tp)) {
        $stmt->bindValue(':tp', '%' . $filtro_tp . '%');
    }
    /*$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);*/
    $stmt->execute();
    $registros_historicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error al cargar histórico: " . $e->getMessage();
    $registros_historicos = [];
    $total_registros = 0;
    $total_paginas = 1;
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">Histórico de Movimientos</div>
        <div class="user-actions">
            <div class="user-info-top">
                <span class="user-name-top"><?php echo htmlspecialchars($usuario_actual['nombre']); ?></span>
                <span class="user-ext-top">Ext: <?php echo htmlspecialchars($usuario_actual['ext']); ?></span>
                <div class="user-avatar-top" style="background-color: #e74c3c;">
                    <?php 
                    $nombres = explode(' ', $usuario_actual['nombre']);
                    $iniciales_top = '';
                    foreach ($nombres as $nombre) {
                        if (!empty($nombre)) {
                            $iniciales_top .= substr($nombre, 0, 1);
                        }
                    }
                    echo strtoupper(substr($iniciales_top, 0, 2));
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="content-area">
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-clock"></i> Histórico de Movimientos
                    <span style="font-size: 14px; color: #7f8c8d; margin-left: 10px;">
                        (Total: <?php echo $total_registros; ?> registros)
                    </span>
                </div>
            </div>

            <!-- Filtro por TP -->
            <div style="padding: 20px; border-bottom: 1px solid #ecf0f1;">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group" style="max-width: 400px;">
                            <label>Buscar por TP:</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="tp" value="<?php echo htmlspecialchars($filtro_tp); ?>" 
                                       class="form-control" placeholder="Ej: TP-500001">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <?php if (!empty($filtro_tp)): ?>
                                    <a href="historico.php" class="btn btn-secondary">Limpiar</a>
                                <?php endif; ?>
                            </div>
                            <small style="color: #7f8c8d; margin-top: 5px;">
                                Busca por coincidencias parciales. Ej: "500" encontrará TP-500001, TP-500002, etc.
                            </small>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de Histórico -->
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>TP</th>
                            <th>Cliente</th>
                            <th>Asignado</th>
                            <th>Usuario</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($registros_historicos)): ?>
                            <?php foreach ($registros_historicos as $registro): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($registro['fecha_hora']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($registro['tp']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($registro['nombre_cliente']); ?></td>
                                    <td>
                                        <span class="badge badge-success"><?php echo htmlspecialchars($registro['asignado']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($registro['usuario_session']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($registro['modulo']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($registro['accion']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px;">
                                    <?php if (!empty($filtro_tp)): ?>
                                        No se encontraron registros para: "<?php echo htmlspecialchars($filtro_tp); ?>"
                                    <?php else: ?>
                                        No se encontraron registros históricos
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <div style="color: #7f8c8d; font-size: 14px;">
                        Mostrando <?php echo min($registros_por_pagina, $total_registros - $offset); ?> de <?php echo $total_registros; ?> registros
                        <?php if (!empty($filtro_tp)): ?>
                            para "<?php echo htmlspecialchars($filtro_tp); ?>"
                        <?php endif; ?>
                    </div>
                    <div class="pagination">
                        <?php 
                        // Construir URL base con filtro TP
                        $url_base = "historico.php?";
                        if (!empty($filtro_tp)) {
                            $url_base .= "tp=" . urlencode($filtro_tp) . "&";
                        }
                        ?>
                        
                        <?php if ($pagina_actual_num > 1): ?>
                            <a href="<?php echo $url_base; ?>pagina=1" class="pagination-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_actual_num - 1; ?>" class="pagination-btn">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagina_actual_num - 2); $i <= min($total_paginas, $pagina_actual_num + 2); $i++): ?>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $i; ?>" 
                               class="pagination-btn <?php echo $i == $pagina_actual_num ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual_num < $total_paginas): ?>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $pagina_actual_num + 1; ?>" class="pagination-btn">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>" class="pagination-btn">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.filter-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: start;
}

.filter-group {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #2c3e50;
    font-size: 14px;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success {
    background-color: #d4edda;
    color: #155724;
}

.badge-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

.badge-primary {
    background-color: #d1e7ff;
    color: #0a58ca;
}

.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px 20px;
}

.pagination {
    display: flex;
    gap: 5px;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #3498db;
    background: white;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.pagination-btn:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

.pagination-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}
</style>