<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
?>

<style>
    .help-home {
        display: block;
    }

    .help-home.hidden,
    .help-detail.hidden {
        display: none;
    }

    .help-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 18px;
        margin-top: 20px;
    }

    .help-card {
        min-height: 230px;
        aspect-ratio: 1 / 1;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        background: #fff;
        box-shadow: var(--shadow);
        padding: 22px;
        text-align: left;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 18px;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .help-card:hover {
        transform: translateY(-2px);
        border-color: rgba(93, 113, 143, .22);
        background: #fbfdff;
        box-shadow: 0 16px 30px rgba(15, 23, 42, .08);
    }

    .help-card-icon {
        width: 62px;
        height: 62px;
        border-radius: 22px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #e8f1ff 0%, #dffbee 100%);
        color: var(--primary);
        font-size: 28px;
        font-weight: 900;
    }

    .help-card h3 {
        margin: 0 0 8px;
        font-size: 22px;
    }

    .help-card p {
        margin: 0;
        color: var(--text-muted);
        line-height: 1.45;
    }

    .help-card span {
        color: var(--primary);
        font-weight: 900;
    }

    .help-detail {
        display: grid;
        gap: 18px;
    }

    .help-detail-head {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }

    .help-detail-title {
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .help-detail-icon {
        width: 74px;
        height: 74px;
        border-radius: 26px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--primary) 0%, #0aa373 100%);
        color: #fff;
        font-size: 32px;
        font-weight: 900;
        box-shadow: 0 18px 34px rgba(37, 99, 235, .18);
    }

    .help-detail-title h2 {
        margin: 0 0 6px;
        font-size: 34px;
    }

    .help-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
    }

    .help-info-box {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 20px;
    }

    .help-info-box h3 {
        margin: 0 0 10px;
        font-size: 20px;
    }

    .help-info-box p {
        margin: 0 0 10px;
        color: var(--text-muted);
        line-height: 1.55;
    }

    .help-info-box ul,
    .help-info-box ol {
        margin: 0;
        padding-left: 20px;
        line-height: 1.75;
    }

    .help-detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    @media (max-width: 760px) {
        .help-card {
            aspect-ratio: auto;
            min-height: 190px;
        }

        .help-detail-head {
            flex-direction: column;
        }
    }
</style>

<div class="admin-grid">
    <section class="admin-panel">
        <div id="help-home" class="help-home">
            <div class="admin-panel-head">
                <div>
                    <h2>Manual de uso del panel administrativo</h2>
                    <p class="text-muted" style="margin:6px 0 0;">Elegi un sector para abrir una guia completa de sus funciones, controles y criterios de uso.</p>
                </div>
            </div>

            <div class="help-card-grid">
                <button class="help-card" type="button" data-help-open="dashboard">
                    <div>
                        <div class="help-card-icon">D</div>
                        <h3>Dashboard</h3>
                        <p>Indicadores, reporte PDF, reservas del mes, backups y accesos rapidos.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="conductores">
                    <div>
                        <div class="help-card-icon">C</div>
                        <h3>Conductores</h3>
                        <p>Revision de solicitudes, aprobaciones, suspensiones y eliminaciones.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="vehiculos">
                    <div>
                        <div class="help-card-icon">V</div>
                        <h3>Vehiculos</h3>
                        <p>Estados, imagenes, documentacion y acciones sobre autos registrados.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="usuarios">
                    <div>
                        <div class="help-card-icon">U</div>
                        <h3>Usuarios</h3>
                        <p>Cuentas activas, suspendidas, eliminadas y validacion de identidad.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="viajes">
                    <div>
                        <div class="help-card-icon">R</div>
                        <h3>Viajes</h3>
                        <p>Publicaciones activas, finalizadas, pasajeros, confirmaciones y reportes.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="reportes">
                    <div>
                        <div class="help-card-icon">!</div>
                        <h3>Reportes</h3>
                        <p>Reclamos contra conductores y pasajeros, resolucion y sanciones.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="soporte">
                    <div>
                        <div class="help-card-icon">?</div>
                        <h3>Soporte</h3>
                        <p>Tickets pendientes y resueltos para organizar la atencion a usuarios.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="help-card" type="button" data-help-open="backups">
                    <div>
                        <div class="help-card-icon">B</div>
                        <h3>Backups</h3>
                        <p>Exportacion, importacion y recuperacion de la base de datos.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>
            </div>
        </div>

        <div id="help-dashboard" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">D</div>
                    <div>
                        <h2>Dashboard</h2>
                        <p class="text-muted" style="margin:0;">Pantalla principal para entender el estado operativo del sistema.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Que muestra</h3>
                    <ul>
                        <li>Usuarios activos habilitados.</li>
                        <li>Viajes registrados en el sistema.</li>
                        <li>Conductores pendientes de revision.</li>
                        <li>Reservas confirmadas.</li>
                        <li>Comparacion de reservas del mes actual contra el anterior.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Reporte PDF integral</h3>
                    <p>Permite elegir fecha desde y hasta para descargar un informe administrativo completo sin salir de la pantalla.</p>
                    <ul>
                        <li>Incluye usuarios, conductores, pasajeros, viajes, reportes, soporte, busquedas y volumen potencial.</li>
                        <li>La rentabilidad es referencial porque los pagos estan desactivados.</li>
                        <li>Sirve para presentar estadisticas generales al profesor o al equipo.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Backups desde Sistema</h3>
                    <p>El bloque Sistema permite exportar e importar backups SQL. Debe usarse antes de cambios importantes o pruebas masivas.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="dashboard.php">Ir al dashboard</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-conductores" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">C</div>
                    <div>
                        <h2>Conductores</h2>
                        <p class="text-muted" style="margin:0;">Gestion de solicitudes y control de conductores aprobados.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Pendientes</h3>
                    <ul>
                        <li>Lista solicitudes nuevas para ser conductor.</li>
                        <li>Permite aprobar si la documentacion y los datos son validos.</li>
                        <li>Permite rechazar si la informacion no corresponde o falta seguridad.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Aprobados</h3>
                    <ul>
                        <li>Activos: pueden operar si tienen vehiculo aprobado.</li>
                        <li>Suspendidos: tienen bloqueo temporal y se ve hasta cuando.</li>
                        <li>Eliminados: quedan fuera del sistema como conductor.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Criterios de accion</h3>
                    <p>Antes de sancionar conviene mirar reportes, vehiculos, viajes finalizados y cantidad de reclamos. La suspension temporal sirve para casos revisables; la eliminacion permanente para incumplimientos graves.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="conductores.php">Ir a conductores</a>
                        <a class="btn btn-outline" href="reportes.php?tipo=conductores">Ver reportes</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-vehiculos" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">V</div>
                    <div>
                        <h2>Vehiculos</h2>
                        <p class="text-muted" style="margin:0;">Revision de autos registrados por conductores.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Estados disponibles</h3>
                    <ul>
                        <li>Pendientes: requieren revision administrativa.</li>
                        <li>Aprobados: pueden utilizarse en viajes.</li>
                        <li>Suspendidos: quedan temporalmente fuera de circulacion.</li>
                        <li>Eliminados o rechazados: no pueden usarse.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Imagenes y documentos</h3>
                    <p>Las imagenes se abren en modal para revisar frente, costado, parte trasera y documentacion sin salir del panel. Esto ayuda a validar que el auto sea real y coherente con los datos cargados.</p>
                </article>
                <article class="help-info-box">
                    <h3>Efecto operativo</h3>
                    <p>Si se elimina o rechaza un vehiculo con viajes asociados, esos viajes pueden cancelarse y los pasajeros reciben notificacion. Por eso conviene revisar antes el historial del auto y del conductor.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="vehiculos.php">Ir a vehiculos</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-usuarios" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">U</div>
                    <div>
                        <h2>Usuarios</h2>
                        <p class="text-muted" style="margin:0;">Control de cuentas, identidad y sanciones.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Filtros</h3>
                    <ul>
                        <li>Activos: cuentas habilitadas.</li>
                        <li>Suspendidos: cuentas bloqueadas temporalmente.</li>
                        <li>Eliminados: cuentas anonimizadas o dadas de baja.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Datos sensibles</h3>
                    <p>El admin puede revisar DNI frente/dorso, telefono, correo y datos de reservas. Esta informacion solo debe usarse para validacion, reportes o seguridad.</p>
                </article>
                <article class="help-info-box">
                    <h3>Sanciones</h3>
                    <p>Una suspension debe estar relacionada con reportes, problemas de identidad o comportamiento. Si el caso se resuelve, se puede reactivar la cuenta.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="usuarios.php">Ir a usuarios</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-viajes" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">R</div>
                    <div>
                        <h2>Viajes</h2>
                        <p class="text-muted" style="margin:0;">Supervision de publicaciones activas y finalizadas.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Activos</h3>
                    <ul>
                        <li>Son viajes disponibles o futuros.</li>
                        <li>Permiten revisar ruta, precio, conductor y vehiculo.</li>
                        <li>El admin puede eliminar viajes que rompan reglas.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Finalizados</h3>
                    <ul>
                        <li>Muestran viajes cerrados por fecha o estado.</li>
                        <li>Permiten ver reportes asociados.</li>
                        <li>Permiten revisar confirmaciones de pasajeros.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Uso recomendado</h3>
                    <p>Usar esta seccion para investigar conflictos: relacionar viaje, conductor, pasajero, reserva, confirmaciones y reportes antes de decidir una sancion.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="viajes.php">Ir a viajes</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-reportes" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">!</div>
                    <div>
                        <h2>Reportes y reclamos</h2>
                        <p class="text-muted" style="margin:0;">Gestion de conflictos entre conductores y pasajeros.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>A conductores</h3>
                    <p>Los cargan pasajeros desde viajes finalizados. El admin ve conductor reportado, reportante, fecha, motivo y viaje asociado si existe.</p>
                    <ul>
                        <li>Descartar si es falso o insuficiente.</li>
                        <li>Suspender temporalmente si requiere sancion revisable.</li>
                        <li>Rechazar permanentemente ante faltas graves.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>A pasajeros</h3>
                    <p>Los carga el conductor. Muestran pasajero, responsable si era pasaje para tercero, DNI, telefono, motivo y viaje.</p>
                    <ul>
                        <li>Marcar resuelto si se toma una decision.</li>
                        <li>Descartar si no corresponde.</li>
                    </ul>
                </article>
                <article class="help-info-box">
                    <h3>Privacidad</h3>
                    <p>Para el denunciado la queja no muestra quien reporto, pero para el administrador si es visible. Eso permite auditar sin exponer innecesariamente a las partes.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="reportes.php">Ir a reportes</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-soporte" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">?</div>
                    <div>
                        <h2>Soporte</h2>
                        <p class="text-muted" style="margin:0;">Atencion de tickets enviados por usuarios.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Pendientes</h3>
                    <p>Casos que requieren respuesta o analisis. Conviene priorizar accesos, reservas, seguridad, reportes y problemas de identidad.</p>
                </article>
                <article class="help-info-box">
                    <h3>Resueltos</h3>
                    <p>Historial de casos cerrados. Mantener esta separacion evita tener que revisar todo el soporte para saber que falta responder.</p>
                </article>
                <article class="help-info-box">
                    <h3>Relaciones</h3>
                    <p>Si un ticket revela un incumplimiento, revisar Usuarios, Viajes o Reportes para tomar acciones con contexto.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="soporte.php">Ir a soporte</a>
                    </div>
                </article>
            </div>
        </div>

        <div id="help-backups" class="help-detail hidden">
            <div class="help-detail-head">
                <div class="help-detail-title">
                    <div class="help-detail-icon">B</div>
                    <div>
                        <h2>Backups y recuperacion</h2>
                        <p class="text-muted" style="margin:0;">Exportacion e importacion de la base de datos.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="help-detail-grid">
                <article class="help-info-box">
                    <h3>Exportar</h3>
                    <p>Exportar SQL genera un archivo con estructura y datos de la base. Se recomienda hacerlo antes de cambios importantes y guardarlo fuera del servidor local.</p>
                </article>
                <article class="help-info-box">
                    <h3>Paquete de recuperacion</h3>
                    <p>Descarga un paquete comprimido con SQL, plantillas de configuracion, manifiesto tecnico e instrucciones para reconstruir MOVEON desde cero.</p>
                </article>
                <article class="help-info-box">
                    <h3>Importar y restaurar</h3>
                    <p>Importar Backup restaura un SQL generado por MOVEON. Para recuperacion completa tambien hace falta el codigo fuente y copiar las configuraciones indicadas en el paquete.</p>
                    <div class="help-detail-actions">
                        <a class="btn btn-outline" href="dashboard.php">Ir al dashboard</a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</div>

<script>
const helpHome = document.getElementById('help-home');
const helpDetails = Array.from(document.querySelectorAll('.help-detail'));

function showHelpSection(id) {
    helpHome.classList.add('hidden');
    helpDetails.forEach((section) => section.classList.add('hidden'));
    const target = document.getElementById('help-' + id);
    if (target) {
        target.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function showHelpHome() {
    helpDetails.forEach((section) => section.classList.add('hidden'));
    helpHome.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('[data-help-open]').forEach((button) => {
    button.addEventListener('click', () => showHelpSection(button.dataset.helpOpen));
});

document.querySelectorAll('[data-help-back]').forEach((button) => {
    button.addEventListener('click', showHelpHome);
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
