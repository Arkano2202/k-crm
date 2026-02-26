<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pagina_actual = 'subir_leads';

include '../includes/session.php';
requireLogin();

include '../includes/header.php';
include '../includes/sidebar.php';
require_once '../config/database.php';

$mensaje = '';
$errores = [];
$registros_actualizados = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_actualizar'])) {

    try {

        $database = new Database();
        $db = $database->getConnection();
        $db->beginTransaction();

        date_default_timezone_set('America/Bogota');
        $fecha_hora = date('Y-m-d H:i:s');

        $usuario_actual = getCurrentUser();
        $usuario_session = $usuario_actual['usuario'] ?? 'Sistema';

        $archivo = $_FILES['archivo_actualizar'];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo.');
        }

        if ($archivo['size'] > 5 * 1024 * 1024) {
            throw new Exception('El archivo es demasiado grande.');
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new Exception('Solo se permiten archivos CSV.');
        }

        // Leer CSV
        $rows = [];
        if (($handle = fopen($archivo['tmp_name'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        if (empty($rows)) {
            throw new Exception('Archivo vacío.');
        }

        $encabezados = array_map('trim', $rows[0]);

        if (!in_array('TP', $encabezados)) {
            throw new Exception('El archivo debe contener la columna TP.');
        }

        $campos_permitidos = [
            'Nombre','Apellido','Correo','Numero','Pais',
            'Campaña','Asignado','Estado','UltimaGestion','FechaUltimaGestion'
        ];

        $campos_a_actualizar = array_values(array_intersect($encabezados, $campos_permitidos));

        if (empty($campos_a_actualizar)) {
            throw new Exception('No hay campos válidos para actualizar.');
        }

        // 🔥 Construir UPDATE con parámetros seguros
        $update_fields = [];
        $param_map = [];

        foreach ($campos_a_actualizar as $campo) {

            if ($campo === 'TP') continue;

            $param = 'p_' . preg_replace('/[^a-zA-Z0-9]/', '', $campo);
            $param_map[$campo] = $param;

            $update_fields[] = "`$campo` = :$param";
        }

        if (empty($update_fields)) {
            throw new Exception('No hay campos actualizables.');
        }

        $update_query = "
            UPDATE clientes 
            SET " . implode(', ', $update_fields) . "
            WHERE TP = :TP
        ";

        $update_stmt = $db->prepare($update_query);

        // Preparar histórico
        $historico_stmt = $db->prepare("
            INSERT INTO historico
            (tp, nombre_cliente, asignado, usuario_session, fecha_hora, accion, modulo)
            VALUES (?, ?, ?, ?, ?, 'ACTUALIZACIÓN MASIVA', 'ACTUALIZAR LEADS')
        ");

        $registros_procesados = 0;

        foreach (array_slice($rows, 1) as $fila) {

            $registros_procesados++;

            $datos = array_combine($encabezados, array_pad($fila, count($encabezados), ''));

            if (empty($datos['TP'])) {
                continue;
            }

            $params = [];

            foreach ($param_map as $campo => $param) {

                if ($campo === 'FechaUltimaGestion') {
                    $params[":$param"] = $fecha_hora;
                } else {
                    $params[":$param"] = trim($datos[$campo] ?? '');
                }
            }

            $params[':TP'] = trim($datos['TP']);

            $update_stmt->execute($params);

            if ($update_stmt->rowCount() > 0) {

                $registros_actualizados++;

                $historico_stmt->execute([
                    $params[':TP'],
                    '',
                    $datos['Asignado'] ?? '',
                    $usuario_session,
                    $fecha_hora
                ]);
            }
        }

        $db->commit();

        $mensaje = "Procesados: $registros_procesados | Actualizados: $registros_actualizados";

    } catch (Exception $e) {

        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }

        $errores[] = $e->getMessage();
    }
}
?>

<div class="main-content">
    <!-- Header Mejorado - Mismo diseño que Cargar Leads -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Actualizar Leads</h1>
                <p class="page-description">Modificar información de leads existentes en el sistema</p>
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
                    <i class="fas fa-sync-alt"></i>
                    <h2>Actualizar Leads</h2>
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
                            <?php if ($registros_actualizados > 0): ?>
                                <p class="note" style="margin-top: 8px; font-size: 0.9rem;">
                                    <i class="fas fa-history"></i> 
                                    Todos los cambios han sido registrados en el histórico del sistema.
                                </p>
                            <?php endif; ?>
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
                
                <?php if (!empty($registros_no_encontrados) && isset($resultados_file)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <strong>Registros No Procesados</strong>
                            <p>Se generó archivo con <?php echo count($registros_no_encontrados); ?> registros no procesados</p>
                            <a href="uploads/<?php echo $resultados_file; ?>" download class="download-btn">
                                <i class="fas fa-download"></i>
                                Descargar Resultados
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Diseño de dos columnas -->
                <div class="upload-layout">
                    <!-- Columna izquierda: Formulario de carga -->
                    <div class="upload-column">
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <div class="file-upload-area" id="fileUploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-file-csv"></i>
                                </div>
                                <div class="upload-content">
                                    <h3>Arrastra tu archivo CSV aquí</h3>
                                    <p>O haz clic para seleccionar</p>
                                    <span class="file-types">Solo archivos CSV - TP obligatorio - Delimitador: Punto y coma (;)</span>
                                </div>
                                <input type="file" id="archivo_actualizar" name="archivo_actualizar" accept=".csv" required class="file-input">
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
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Iniciar Actualización</span>
                                </button>
                                <button type="button" id="descargar-plantilla" class="btn btn-secondary btn-large">
                                    <i class="fas fa-download"></i>
                                    <span>Descargar Plantilla</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Columna derecha: Información de campos -->
                    <div class="info-column">
                        <div class="upload-info">
                            <h3>¿Cómo actualizar leads?</h3>
                            <p>Sube un archivo CSV con el <strong>TP obligatorio</strong> y los campos que deseas actualizar.</p>
                            
                            <div class="fields-grid">
                                <div class="field-item required">
                                    <span class="field-name">TP</span>
                                    <span class="field-badge">Obligatorio</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Nombre</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Apellido</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Correo</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Numero</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">País</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Campaña</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Asignado</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                                <div class="field-item">
                                    <span class="field-name">Estado</span>
                                    <span class="field-badge">Opcional</span>
                                </div>
                            </div>
                            
                            <div class="feature-highlight">
                                <i class="fas fa-history"></i>
                                <div class="highlight-content">
                                    <strong>Registro en Histórico</strong>
                                    <p>Todos los cambios se registrarán automáticamente en el histórico del sistema.</p>
                                </div>
                            </div>
                            
                            <p class="note"><strong>Nota:</strong> El campo <strong>TP es obligatorio</strong> para identificar los leads. Los demás campos son opcionales. <strong>Usar punto y coma (;) como delimitador.</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ESTILOS IDÉNTICOS A SUBIR_LEADS.PHP */

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
    max-width: 100%;
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
}

.preview-header {
    display: flex;
    align-items: center;
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

/* NUEVO DISEÑO DE DOS COLUMNAS */
.upload-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    align-items: start;
}

.upload-column {
    display: flex;
    flex-direction: column;
}

.info-column {
    display: flex;
    flex-direction: column;
}

/* Grid de campos mejorado */
.fields-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin: 20px 0;
}

.field-item {
    background: #f8f9fa;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    padding: 15px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.field-item:hover {
    background: #e3f2fd;
    border-color: #3498db;
    transform: translateY(-2px);
}

.field-item.required {
    background: #fdf2f2;
    border-color: #e74c3c;
}

.field-item.required .field-name {
    color: #e74c3c;
    font-weight: 600;
}

.field-name {
    font-weight: 500;
    color: #2c3e50;
    font-size: 0.95rem;
}

.field-badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
}

.field-item:not(.required) .field-badge {
    background: #95a5a6;
    color: white;
}

.field-item.required .field-badge {
    background: #e74c3c;
    color: white;
}

.upload-info {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 25px;
    border: 1px solid #e1e8ed;
    height: fit-content;
}

.upload-info h3 {
    color: #2c3e50;
    margin: 0 0 15px 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.upload-info p {
    color: #5d6d7e;
    margin: 0 0 15px 0;
    line-height: 1.5;
}

/* Feature Highlight */
.feature-highlight {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #e8f5e8;
    border: 1px solid #27ae60;
    border-radius: 8px;
    padding: 16px;
    margin: 20px 0;
}

.feature-highlight i {
    color: #27ae60;
    font-size: 1.2rem;
    margin-top: 2px;
}

.highlight-content strong {
    display: block;
    color: #155724;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.highlight-content p {
    margin: 0;
    color: #155724;
    font-size: 0.85rem;
    opacity: 0.9;
}

.note {
    font-size: 0.9rem;
    color: #7f8c8d;
    font-style: italic;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e1e8ed;
}

/* Responsive para dos columnas */
@media (max-width: 968px) {
    .upload-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .upload-column {
        order: 1;
    }
    
    .info-column {
        order: 2;
    }
    
    .fields-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

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
    
    .fields-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .fields-grid {
        grid-template-columns: 1fr;
    }
    
    .upload-info {
        padding: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de la subida de archivos - Mismo código que subir_leads.php
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('archivo_actualizar');
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
           fileUploadArea
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
    
    // Descargar plantilla para ACTUALIZAR con punto y coma
    document.getElementById('descargar-plantilla')?.addEventListener('click', function() {
        const csvContent = "TP;Nombre;Apellido;Correo;Numero;Pais;Campaña;Asignado;Estado\nTP-000001;Juan;Perez;juan@email.com;123456789;Colombia;Campaña A;Usuario A;Activo\nTP-000002;Maria;Garcia;maria@email.com;987654321;México;Campaña B;Usuario B;En Proceso";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'plantilla_actualizacion.csv';
        
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