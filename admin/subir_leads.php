<?php
// subir_leads.php (en la raíz)
$pagina_actual = 'subir_leads';

// Incluir archivos necesarios
include '../includes/session.php';
requireLogin();

include '../includes/header.php';
include '../includes/sidebar.php';

// Procesar archivo subido
$mensaje = '';
$errores = [];
$registros_rechazados = [];

// Función para registrar en el histórico
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

// Funcion generar archivos rechazados
function generar_archivo_rechazados($rechazados) {

    $filename = 'rechazados_' . date('Y-m-d_H-i-s') . '.csv';
    $ruta = __DIR__ . '/uploads';

    if (!is_dir($ruta)) {
        mkdir($ruta, 0755, true);
    }

    $filepath = $ruta . '/' . $filename;

    $fp = fopen($filepath, 'w');

    // Encabezados profesionales
    fputcsv($fp, [
        'Nombre',
        'Apellido',
        'Correo',
        'Numero',
        'Pais',
        'Campaña',
        'Motivo'
    ]);

    foreach ($rechazados as $row) {
        fputcsv($fp, [
            $row['nombre'] ?? '',
            $row['apellido'] ?? '',
            $row['correo'] ?? '',
            $row['numero'] ?? '',
            $row['pais'] ?? '',
            $row['campania'] ?? '',
            $row['razon'] ?? ''
        ]);
    }

    fclose($fp);

    return $filename;
}

$mensaje = '';
$errores = [];
$registros_rechazados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_leads'])) {

    try {

        $database = new Database();
        $db = $database->getConnection();
        $db->beginTransaction();

        date_default_timezone_set('America/Bogota');
        $fecha_hora = date('Y-m-d H:i:s');

        $usuario_actual = getCurrentUser();
        $usuario_session = $usuario_actual['usuario'] ?? 'Sistema';

        $archivo = $_FILES['archivo_leads'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo.');
        }

        if ($archivo['size'] > 5 * 1024 * 1024) {
            throw new Exception('Archivo demasiado grande (5MB máximo).');
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('Solo se permiten archivos CSV.');
        }

        // =========================
        // LEER CSV
        // =========================
        $rows = [];
        if (($handle = fopen($archivo['tmp_name'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        if (empty($rows) || count($rows) < 2) {
            throw new Exception('El archivo está vacío o no tiene datos.');
        }

        // =========================
        // OBTENER MAX TP CORRECTAMENTE
        // =========================
        $stmt_max = $db->prepare("
            SELECT MAX(CAST(SUBSTRING(TP,4) AS UNSIGNED)) 
            FROM clientes 
            WHERE TP LIKE 'TP-%'
        ");
        $stmt_max->execute();
        $max_numero = (int)$stmt_max->fetchColumn();
        $nuevo_numero = $max_numero + 1;

        // =========================
        // VALIDAR DUPLICADOS EN BLOQUE
        // =========================
        $numeros_csv = [];

        foreach (array_slice($rows, 1) as $fila) {
            if (!empty($fila[3])) {
                $numeros_csv[] = trim($fila[3]);
            }
        }

        $numeros_csv = array_unique($numeros_csv);
        $numeros_existentes = [];

        if (!empty($numeros_csv)) {
            $placeholders = implode(',', array_fill(0, count($numeros_csv), '?'));
            $stmt_existentes = $db->prepare("
                SELECT Numero FROM clientes 
                WHERE Numero IN ($placeholders)
            ");
            $stmt_existentes->execute($numeros_csv);
            $numeros_existentes = array_flip(
                $stmt_existentes->fetchAll(PDO::FETCH_COLUMN)
            );
        }

        // =========================
        // PREPARAR INSERT
        // =========================
        $insert_stmt = $db->prepare("
            INSERT INTO clientes 
            (Nombre, Apellido, Correo, Numero, Pais, TP, Campaña, grupo_id, Asignado, Estado, FechaCreacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Admin', 'New', NOW())
        ");

        // =========================
        // PREPARAR HISTÓRICO
        // =========================
        $historico_stmt = $db->prepare("
            INSERT INTO historico 
            (tp, nombre_cliente, asignado, usuario_session, fecha_hora, accion, modulo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $registros_procesados = 0;
        $registros_insertados = 0;

        foreach (array_slice($rows, 1) as $fila) {

            if (count($fila) < 6) {
                continue;
            }

            $nombre   = trim($fila[0]);
            $apellido = trim($fila[1]);
            $correo   = trim($fila[2]);
            $numero   = trim($fila[3]);
            $pais     = trim($fila[4]);
            $campania = trim($fila[5]);

            if (empty($nombre) || empty($numero)) {
                continue;
            }

            $registros_procesados++;

            if (isset($numeros_existentes[$numero])) {
                $registros_rechazados[] = [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'numero' => $numero,
                    'razon'  => 'Número duplicado'
                ];
                continue;
            }

            $nuevo_tp = 'TP-' . str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
            $nuevo_numero++;

            if ($insert_stmt->execute([
                $nombre,
                $apellido,
                $correo,
                $numero,
                $pais,
                $nuevo_tp,
                $campania
            ])) {

                $registros_insertados++;

                $nombre_completo = $nombre . ' ' . $apellido;

                $historico_stmt->execute([
                    $nuevo_tp,
                    $nombre_completo,
                    'Admin',
                    $usuario_session,
                    $fecha_hora,
                    'CREACION',
                    'SUBIR_LEADS'
                ]);
            }
        }

        // HISTÓRICO RESUMEN
        if ($registros_insertados > 0) {
            $historico_stmt->execute([
                'RESUMEN_CARGA',
                "Carga masiva: $registros_insertados clientes nuevos",
                'Admin',
                $usuario_session,
                $fecha_hora,
                'CARGA_MASIVA',
                'SUBIR_LEADS'
            ]);
        }

        $db->commit();

        if (!empty($registros_rechazados)) {
            $rechazados_file = generar_archivo_rechazados($registros_rechazados);
        }

        $mensaje = "Procesados: $registros_procesados | Insertados: $registros_insertados | Rechazados: " . count($registros_rechazados);

    } catch (Exception $e) {

        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }

        $errores[] = $e->getMessage();
    }
}
?>

<div class="main-content">
    <!-- Header Mejorado -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Cargar Nuevos Leads</h1>
                <p class="page-description">Agregar nuevos leads al sistema desde archivo CSV</p>
            </div>
            <div class="header-actions">
                <div class="user-welcome">
                    <span class="welcome-text">Bienvenido,</span>
                    <span class="username"><?php echo getCurrentUsername(); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="content-wrapper">
        <div class="dashboard-card">
            <div class="card-header-enhanced">
                <div class="card-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h2>Cargar Nuevos Leads</h2>
                </div>
                <div class="card-badge">
                    <span class="badge success">CSV</span>
                </div>
            </div>
            
            <div class="card-content">
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div class="alert-content">
                            <strong>¡Éxito!</strong>
                            <p><?php echo $mensaje; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="alert-content">
                            <strong>Error</strong>
                            <?php foreach ($errores as $error): ?>
                                <p><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($registros_rechazados) && isset($rechazados_file)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <strong>Registros Rechazados</strong>
                            <p>Se generó archivo con <?php echo count($registros_rechazados); ?> registros rechazados</p>
                            <a href="uploads/<?php echo $rechazados_file; ?>" download class="download-btn">
                                <i class="fas fa-download"></i>
                                Descargar Rechazados
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div class="upload-content">
                            <h3>Arrastra tu archivo CSV aquí</h3>
                            <p>O haz clic para seleccionar</p>
                            <span class="file-types">Solo archivos CSV</span>
                        </div>
                        <input type="file" id="archivo_leads" name="archivo_leads" accept=".csv" required class="file-input">
                    </div>
                    
                    <div class="file-preview" id="filePreview" style="display: none;">
                        <div class="preview-header">
                            <i class="fas fa-file"></i>
                            <span class="file-name" id="fileName"></span>
                            <button type="button" class="remove-file" id="removeFile">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-actions-enhanced">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-upload"></i>
                            <span>Iniciar Carga de Leads</span>
                        </button>
                        <button type="button" id="descargar-plantilla" class="btn btn-secondary btn-large">
                            <i class="fas fa-download"></i>
                            <span>Descargar Plantilla</span>
                        </button>
                    </div>
                </form>
                
                <div class="upload-info">
                    <h3>Estructura del archivo CSV:</h3>
                    <p>El archivo debe contener las siguientes columnas en este orden:</p>
                    <div class="csv-structure">
                        <div class="csv-column">Nombre</div>
                        <div class="csv-column">Apellido</div>
                        <div class="csv-column">Correo</div>
                        <div class="csv-column">Numero</div>
                        <div class="csv-column">Pais</div>
                        <div class="csv-column">Campaña</div>
                    </div>
                    <p class="note"><strong>Nota:</strong> El TP se genera automáticamente de forma secuencial.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos Generales Mejorados - Colores armonizados con sidebar */
.main-content {
    background: #f8f9fa;
    min-height: 100vh;
}

/* Header Mejorado - Colores del sidebar */
.page-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px 40px 20px 40px;
}

.header-title h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: white;
}

.page-description {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
    color: #bdc3c7;
}

.user-welcome {
    text-align: right;
}

.welcome-text {
    display: block;
    opacity: 0.8;
    font-size: 0.9rem;
    margin-bottom: 4px;
    color: #bdc3c7;
}

.username {
    font-weight: 600;
    font-size: 1.1rem;
    color: white;
}

/* Contenedor Principal */
.content-wrapper {
    padding: 40px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Tarjetas Mejoradas - Colores que combinan con sidebar */
.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
    border: 1px solid #e1e8ed;
}

.card-header-enhanced {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.card-title i {
    font-size: 1.8rem;
    color: #2c3e50;
}

.card-title h2 {
    margin: 0;
    font-size: 1.6rem;
    color: #2c3e50;
    font-weight: 600;
}

.card-badge .badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge.success {
    background: #27ae60;
    color: white;
}

.badge.info {
    background: #3498db;
    color: white;
}

/* Contenido de la Tarjeta */
.card-content {
    padding: 30px;
}

/* Alertas Mejoradas */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    border-left: 4px solid;
}

.alert-success {
    background: #d5f4e6;
    border-left-color: #27ae60;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border-left-color: #e74c3c;
    color: #721c24;
}

.alert-warning {
    background: #fff3cd;
    border-left-color: #f39c12;
    color: #856404;
}

.alert i {
    font-size: 1.3rem;
    margin-top: 2px;
}

.alert-content strong {
    display: block;
    font-size: 1rem;
    margin-bottom: 4px;
}

.alert-content p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.download-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #f39c12;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.85rem;
    margin-top: 8px;
    transition: background 0.3s ease;
}

.download-btn:hover {
    background: #e67e22;
}

/* Formulario de Carga Mejorado */
.upload-form {
    max-width: 600px;
    margin: 0 auto; /* Centrar el formulario */
}

.file-upload-area {
    border: 2px dashed #bdc3c7;
    border-radius: 12px;
    padding: 50px 30px;
    text-align: center;
    background: #f8f9fa;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center; /* Centrar contenido horizontalmente */
    justify-content: center; /* Centrar contenido verticalmente */
}

.file-upload-area:hover {
    border-color: #3498db;
    background: #e3f2fd;
}

.file-upload-area.dragover {
    border-color: #3498db;
    background: #bbdefb;
}

.upload-icon {
    font-size: 2.5rem;
    color: #95a5a6;
    margin-bottom: 16px;
}

.upload-content {
    display: flex;
    flex-direction: column;
    align-items: center; /* Centrar contenido horizontalmente */
    justify-content: center; /* Centrar contenido verticalmente */
    text-align: center; /* Asegurar que el texto esté centrado */
}

.upload-content h3 {
    margin: 0 0 8px 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.upload-content p {
    margin: 0 0 12px 0;
    color: #7f8c8d;
    font-size: 0.95rem;
}

.file-types {
    font-size: 0.85rem;
    color: #95a5a6;
}

.file-input {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}

/* Vista Previa de Archivo */
.file-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    margin-top: 20px;
    border: 1px solid #e1e8ed;
    text-align: center; /* Centrar contenido */
}

.preview-header {
    display: flex;
    align-items: center;
    justify-content: center; /* Centrar contenido horizontalmente */
    gap: 12px;
}

.preview-header i {
    color: #3498db;
    font-size: 1.3rem;
}

.file-name {
    flex: 1;
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.95rem;
    text-align: center; /* Centrar texto */
}

.remove-file {
    background: none;
    border: none;
    color: #95a5a6;
    cursor: pointer;
    padding: 6px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.remove-file:hover {
    color: #e74c3c;
    background: #fdf2f2;
}

/* Botones Mejorados - Colores del sidebar */
.form-actions-enhanced {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
    justify-content: center; /* Centrar botones */
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-large {
    padding: 16px 32px;
    font-size: 1rem;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

.btn-secondary {
    background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
}

/* Información de estructura CSV */
.upload-info {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #3498db;
    text-align: center; /* Centrar contenido */
}

.upload-info h3 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.upload-info p {
    margin: 0 0 15px 0;
    color: #5d6d7e;
    font-size: 0.95rem;
}

.csv-structure {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
    justify-items: center; /* Centrar columnas */
}

.csv-column {
    background: white;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
    color: #2c3e50;
    border: 1px solid #dee2e6;
    font-size: 0.9rem;
    width: 120px; /* Ancho fijo para mejor alineación */
}

.note {
    font-size: 0.85rem;
    color: #7f8c8d;
    font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 16px;
        padding: 20px;
    }
    
    .content-wrapper {
        padding: 20px;
    }
    
    .card-header-enhanced,
    .card-content {
        padding: 20px;
    }
    
    .form-actions-enhanced {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .file-upload-area {
        padding: 30px 20px;
    }
    
    .csv-structure {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de la subida de archivos
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('archivo_leads');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const removeFile = document.getElementById('removeFile');
    
    // Drag and Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, () => {
            fileUploadArea.classList.add('dragover');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, () => {
            fileUploadArea.classList.remove('dragover');
        }, false);
    });
    
    fileUploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleFiles(files);
    }
    
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            fileName.textContent = file.name;
            filePreview.style.display = 'block';
            fileUploadArea.style.display = 'none';
        }
    }
    
    removeFile.addEventListener('click', function() {
        fileInput.value = '';
        filePreview.style.display = 'none';
        fileUploadArea.style.display = 'block';
    });
    
    // Descargar plantilla
    document.getElementById('descargar-plantilla')?.addEventListener('click', function() {
        const csvContent = "Nombre,Apellido,Correo,Numero,Pais,Campaña\nJuan,Perez,juan@email.com,123456789,Mexico,Nova\nMaria,Garcia,maria@email.com,987654321,Peru,Nova";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'plantilla_leads.csv';
        
        // Efecto visual
        const originalHTML = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check"></i><span>¡Plantilla Descargada!</span>';
        this.style.background = 'linear-gradient(135deg, #27ae60 0%, #229954 100%)';
        
        setTimeout(() => {
            this.innerHTML = originalHTML;
            this.style.background = '';
        }, 2000);
        
        a.click();
        window.URL.revokeObjectURL(url);
    });
    
    // Efectos hover para botones
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>