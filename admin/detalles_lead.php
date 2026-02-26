<?php
include '../includes/session.php';
requireLogin();

$pagina_actual = 'leads';

include '../includes/header.php';
include '../includes/sidebar.php';

$usuario_actual = getCurrentUser();
$tipo_usuario = (int)($usuario_actual['tipo'] ?? 0);

/* ===== Permisos ===== */
$puede_ver_telefono = in_array($tipo_usuario, [1]);        // admin
$puede_ver_correo   = in_array($tipo_usuario, [1,4,5]);    // admin, 4, 5
$puede_ver_campana  = in_array($tipo_usuario, [1,2,4]);    // admin, 2, 4

$tp = $_GET['tp'] ?? '';
if (!$tp) die('TP no especificado');

/* ===== Navegación ===== */
$leads = $_SESSION['leads_navigation'] ?? [];
if (!is_array($leads)) $leads = [];

$index_actual = array_search($tp, $leads);
$total_leads = count($leads);
$posicion_actual = ($index_actual !== false) ? $index_actual + 1 : null;

$tp_anterior  = ($index_actual !== false && $index_actual > 0) ? $leads[$index_actual - 1] : null;
$tp_siguiente = ($index_actual !== false && $index_actual < $total_leads - 1) ? $leads[$index_actual + 1] : null;
?>

<div class="main-content">

    <div class="top-bar">
        <div class="page-title">
            <a href="leads.php" style="color:#7f8c8d;text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Leads
            </a>
            / Detalles del Lead
        </div>
    </div>

    <div class="content-area">

        <!-- INFO -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    Información del Cliente
                    <?php if ($posicion_actual): ?>
                        <span class="lead-counter">
                            Lead <?php echo $posicion_actual; ?> de <?php echo $total_leads; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <button class="btn btn-secondary" <?php if (!$tp_anterior) echo 'disabled'; ?>
                        onclick="location.href='detalles_lead.php?tp=<?php echo urlencode($tp_anterior); ?>'">
                        <i class="fas fa-arrow-left"></i>
                    </button>

                    <button class="btn btn-secondary" <?php if (!$tp_siguiente) echo 'disabled'; ?>
                        onclick="location.href='detalles_lead.php?tp=<?php echo urlencode($tp_siguiente); ?>'">
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <button class="btn btn-success" onclick="hacerLlamada()">
                        <i class="fas fa-phone"></i>
                    </button>

                    <button class="btn btn-primary" onclick="mostrarFormularioNota()">
                        <i class="fas fa-sticky-note"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div id="infoLead">
                    <i class="fas fa-spinner fa-spin"></i> Cargando información...
                </div>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="dashboard-card" style="margin-top:25px;">
            <div class="card-header">
                <div class="card-title">Historial de Notas</div>
            </div>
            <div class="card-body">
                <div id="listaNotas" class="notas-scroll"></div>
            </div>
        </div>

        <!-- NUEVA NOTA -->
        <div class="dashboard-card" id="formNota" style="margin-top:25px;display:none;">
            <div class="card-header">
                <div class="card-title">Nueva Nota</div>
            </div>

            <div class="card-body">
                <div class="nota-form-grid">

                    <div class="nota-field">
                        <label class="form-label">Tipo de Gestión</label>
                        <select id="gestionSelect" class="form-control"></select>
                    </div>

                    <div class="nota-field nota-field-full">
                        <label class="form-label">Descripción</label>
                        <textarea id="notaDescripcion"
                                  class="form-control"
                                  placeholder="Describe la gestión..."></textarea>
                    </div>

                </div>

                <div class="nota-actions">
                    <button class="btn btn-primary" onclick="guardarNota()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <button class="btn btn-secondary" onclick="cancelarNota()">Cancelar</button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= ESTILOS ================= -->
<style>
.lead-counter {
    font-size:13px;
    color:#7f8c8d;
    margin-left:10px;
}

/* INFO */
.lead-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(230px,1fr));
    gap:18px;
}
.lead-card {
    background:#fff;
    border-radius:14px;
    padding:18px 20px;
    box-shadow:0 6px 20px rgba(0,0,0,.06);
    border-left:5px solid #3498db;
}
.lead-card.estado { border-left-color:#2ecc71; }
.lead-card .label {
    font-size:11px;
    color:#95a5a6;
    text-transform:uppercase;
    font-weight:600;
}
.lead-card .value {
    font-size:16px;
    font-weight:600;
    margin-top:6px;
    user-select:none;
}

/* DATOS BLOQUEADOS */
.value[data-bloqueado="true"] {
    letter-spacing:2px;
}

/* HISTORIAL */
.notas-scroll {
    max-height:360px;
    overflow-y:auto;
    padding-right:10px;
}
.nota-item {
    background:#fff;
    border-radius:12px;
    padding:16px 20px;
    box-shadow:0 4px 15px rgba(0,0,0,.06);
    margin-bottom:18px;
    border-left:4px solid #3498db;
}
.nota-top {
    display:flex;
    justify-content:space-between;
    font-size:13px;
    margin-bottom:8px;
}
.nota-gestion {
    background:#eaf4ff;
    color:#3498db;
    padding:4px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
    margin-bottom:8px;
    display:inline-block;
}
.nota-texto {
    font-size:14px;
    color:#34495e;
    line-height:1.6;
}

/* NUEVA NOTA */
#formNota .card-body { padding:30px; }
#formNota .nota-form-grid {
    display:grid;
    grid-template-columns:1fr 2fr;
    gap:30px;
}
#formNota textarea.form-control {
    min-height:180px;
    resize:vertical;
}
#formNota .nota-actions {
    display:flex;
    justify-content:flex-end;
    gap:12px;
    margin-top:25px;
}
@media(max-width:768px){
    #formNota .nota-form-grid { grid-template-columns:1fr; }
}
</style>

<!-- ================= JS ================= -->
<script>
const tp = "<?php echo htmlspecialchars($tp); ?>";
const extensionUsuario = "<?php echo htmlspecialchars($usuario_actual['ext']); ?>";

const PERMISOS = {
    verTelefono: <?php echo $puede_ver_telefono ? 'true' : 'false'; ?>,
    verCorreo: <?php echo $puede_ver_correo ? 'true' : 'false'; ?>,
    verCampana: <?php echo $puede_ver_campana ? 'true' : 'false'; ?>
};

let numeroLead = "";

fetch(`ver_cliente.php?tp=${encodeURIComponent(tp)}`)
.then(r => r.json())
.then(data => {
    const c = data.cliente;

    // 🔑 SIEMPRE guardar el número real
    numeroLead = c.Numero;

    document.getElementById('infoLead').innerHTML = `
        <div class="lead-grid">
            <div class="lead-card"><span class="label">TP</span><span class="value">${c.TP}</span></div>
            <div class="lead-card"><span class="label">Nombre</span><span class="value">${c.Nombre} ${c.Apellido ?? ''}</span></div>

            <div class="lead-card">
                <span class="label">Teléfono</span>
                <span class="value" data-bloqueado="${PERMISOS.verTelefono ? 'false' : 'true'}">
                    ${PERMISOS.verTelefono ? c.Numero : '********'}
                </span>
            </div>

            <div class="lead-card">
                <span class="label">Email</span>
                <span class="value" data-bloqueado="${PERMISOS.verCorreo ? 'false' : 'true'}">
                    ${PERMISOS.verCorreo ? c.Correo : '********'}
                </span>
            </div>

            <div class="lead-card"><span class="label">País</span><span class="value">${c.Pais}</span></div>

            ${PERMISOS.verCampana ? `
            <div class="lead-card">
                <span class="label">Campaña</span>
                <span class="value">${c.Campaña ?? '—'}</span>
            </div>
            ` : ``}

            <div class="lead-card"><span class="label">Asignado</span><span class="value">${c.Asignado}</span></div>
            <div class="lead-card estado"><span class="label">Estado</span><span class="value">${c.Estado}</span></div>
        </div>
    `;

    renderNotas(data.notas || []);
});

function renderNotas(notas){
    const cont = document.getElementById('listaNotas');
    if(!notas.length){ cont.innerHTML = '<em>No hay notas</em>'; return; }

    notas.sort((a,b)=>new Date(b.FechaUltimaGestion)-new Date(a.FechaUltimaGestion));

    cont.innerHTML = notas.map(n=>`
        <div class="nota-item">
            <div class="nota-top">
                <strong>${n.user}</strong>
                <span>${n.FechaUltimaGestion}</span>
            </div>
            <div class="nota-gestion">${n.UltimaGestion}</div>
            <div class="nota-texto">${n.Descripcion}</div>
        </div>
    `).join('');
}

function hacerLlamada(){
    if(!numeroLead || !extensionUsuario) return;
    fetch(`llamada.php?numero=${encodeURIComponent(numeroLead)}&extension=${encodeURIComponent(extensionUsuario)}`);
}

function mostrarFormularioNota(){
    document.getElementById('formNota').style.display = 'block';
    cargarEstados();
}
function cancelarNota(){
    document.getElementById('formNota').style.display = 'none';
}
function cargarEstados(){
    fetch('obtener_estados.php')
    .then(r=>r.json())
    .then(d=>{
        const s=document.getElementById('gestionSelect');
        s.innerHTML='<option value="">Seleccionar gestión</option>';
        d.estados.forEach(e=>{
            const o=document.createElement('option');
            o.value=e.Estado;
            o.textContent=e.Estado;
            s.appendChild(o);
        });
    });
}
function guardarNota(){
    const g=document.getElementById('gestionSelect').value;
    const d=document.getElementById('notaDescripcion').value.trim();
    if(!g||!d){ alert('Completa los campos'); return; }

    const fd=new FormData();
    fd.append('tp',tp);
    fd.append('gestion',g);
    fd.append('descripcion',d);

    fetch('guardar_nota.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(x=>{
        if(x.success) location.reload();
        else alert(x.error);
    });
}
</script>

</body>
</html>
