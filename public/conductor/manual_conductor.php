<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

include __DIR__ . '/_nav.php';
?>

<div class="user-help-shell">
    <section class="card">
        <div id="driver-help-home" class="user-help-home">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Manual del conductor</h2>
                    <p class="text-muted" style="margin:8px 0 0;">Guia completa para administrar tu perfil, vehiculos, viajes, pasajeros, validaciones y reportes.</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline">Volver al panel</a>
            </div>

            <div class="user-help-card-grid">
                <button class="user-help-card" type="button" data-driver-help-open="perfil">
                    <div>
                        <div class="user-help-card-icon">P</div>
                        <h3>Mi perfil</h3>
                        <p>Datos de conductor, estado de cuenta, reputacion y resumen de actividad.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-driver-help-open="vehiculos">
                    <div>
                        <div class="user-help-card-icon">V</div>
                        <h3>Mis vehiculos</h3>
                        <p>Registro, aprobacion, documentacion, imagenes, estados y vehiculo activo.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-driver-help-open="viajes">
                    <div>
                        <div class="user-help-card-icon">R</div>
                        <h3>Mis viajes</h3>
                        <p>Crear viajes, separar activos e historial, editar, eliminar y reutilizar plantillas.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-driver-help-open="pasajeros">
                    <div>
                        <div class="user-help-card-icon">L</div>
                        <h3>Pasajeros</h3>
                        <p>Validacion de identidad, codigo de acceso, DNI, telefono y responsable de terceros.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-driver-help-open="cierre">
                    <div>
                        <div class="user-help-card-icon">C</div>
                        <h3>Cierre del viaje</h3>
                        <p>Confirmar que todo estuvo bien o reportar pasajeros de un viaje finalizado.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-driver-help-open="seguridad">
                    <div>
                        <div class="user-help-card-icon">S</div>
                        <h3>Seguridad</h3>
                        <p>Reportes, suspensiones, buenas practicas y cuidado de datos sensibles.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>
            </div>
        </div>

        <div id="driver-help-perfil" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">P</div>
                    <div>
                        <h2>Mi perfil</h2>
                        <p class="text-muted" style="margin:0;">Resumen de identidad, cuenta verificada, vehiculos, viajes y calificacion.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Que muestra</h3>
                    <p>El panel resume nombre, correo, estado de la cuenta, vehiculos registrados, cantidad de viajes publicados, calificacion promedio y pasajeros llevados.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Calificacion</h3>
                    <p>La puntuacion se calcula con el promedio de las calificaciones hechas por pasajeros despues de viajes finalizados.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Estado de cuenta</h3>
                    <p>Si administracion suspende o elimina la cuenta de conductor, el acceso a funciones de conductor puede quedar limitado.</p>
                </div>
            </div>
        </div>

        <div id="driver-help-vehiculos" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">V</div>
                    <div>
                        <h2>Mis vehiculos</h2>
                        <p class="text-muted" style="margin:0;">Control de autos asociados al conductor y documentacion que revisa administracion.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Registro</h3>
                    <ul>
                        <li>Marca, modelo, color, patente y cantidad de asientos.</li>
                        <li>Documentacion del auto.</li>
                        <li>Fotos del vehiculo cuando el formulario las pida.</li>
                    </ul>
                </div>
                <div class="user-help-info-box">
                    <h3>Aprobacion</h3>
                    <p>Un vehiculo pendiente debe ser aprobado por administracion antes de usarse para publicar viajes.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Estados</h3>
                    <p>Puede estar pendiente, aprobado, suspendido o eliminado/rechazado. Los suspendidos no deberian usarse para nuevas salidas.</p>
                </div>
            </div>
        </div>

        <div id="driver-help-viajes" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">R</div>
                    <div>
                        <h2>Mis viajes</h2>
                        <p class="text-muted" style="margin:0;">Gestion de publicaciones activas e historial de viajes ya finalizados.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Crear viaje</h3>
                    <p>Desde Crear viaje se carga origen, destino, punto de encuentro, fecha, hora, precio, vehiculo y datos de distancia/duracion cuando aplique.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Activos e historial</h3>
                    <p>Activos muestra viajes proximos o pendientes. Historial muestra viajes finalizados, con acceso a detalles y acciones posteriores al viaje.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Acciones utiles</h3>
                    <ul>
                        <li>Ver pasajeros / validar.</li>
                        <li>Reutilizar como plantilla.</li>
                        <li>Eliminar viaje activo si corresponde.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="driver-help-pasajeros" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">L</div>
                    <div>
                        <h2>Pasajeros y validacion</h2>
                        <p class="text-muted" style="margin:0;">Informacion necesaria para validar identidad y abordar con seguridad.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Lista de pasajeros</h3>
                    <p>En Ver pasajeros / validar se muestran pasajeros confirmados, DNI, telefono, correo y codigo de acceso.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Pasaje de terceros</h3>
                    <p>Si una reserva es para un tercero, tambien se muestra quien es el usuario responsable para poder auditar el caso.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Validacion al abordar</h3>
                    <p>Solicita el codigo de validacion y verifica que los datos coincidan. Usa esa informacion solo para seguridad del viaje.</p>
                </div>
            </div>
        </div>

        <div id="driver-help-cierre" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">C</div>
                    <div>
                        <h2>Cierre del viaje</h2>
                        <p class="text-muted" style="margin:0;">Cuando un viaje termina, el sistema puede pedir confirmacion y permitir reportes.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Todo bien</h3>
                    <p>Si no hubo inconvenientes, confirma que el viaje termino correctamente. Esa confirmacion ayuda al control administrativo.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Reportar pasajero</h3>
                    <p>Si un pasajero no se presento, no pago o tuvo una conducta inadecuada, podes reportarlo desde el viaje finalizado y elegir a quien corresponde.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Panel admin</h3>
                    <p>Las confirmaciones y reportes quedan disponibles para administracion, junto con viaje, fecha, pasajero y responsable si aplica.</p>
                </div>
            </div>
        </div>

        <div id="driver-help-seguridad" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">S</div>
                    <div>
                        <h2>Seguridad</h2>
                        <p class="text-muted" style="margin:0;">Recomendaciones para operar sin exponer informacion sensible.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-driver-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Datos del auto</h3>
                    <p>La patente, fotos y punto de encuentro se muestran a pasajeros cuando corresponde. No publiques datos sensibles fuera de la plataforma.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Reportes</h3>
                    <p>Los reportes no muestran el reportante al usuario denunciado, pero administracion si puede verlo para analizar el caso.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Soporte</h3>
                    <p>Si un problema no corresponde a un reporte de pasajero, usa soporte para dejar constancia ante administracion.</p>
                    <a href="<?= BASE_URL ?>soporte.php" class="btn" style="margin-top:8px;">Contactar soporte</a>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.querySelectorAll('[data-driver-help-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.driverHelpOpen;
            document.getElementById('driver-help-home')?.classList.add('hidden');
            document.querySelectorAll('.user-help-detail').forEach((detail) => detail.classList.add('hidden'));
            document.getElementById('driver-help-' + key)?.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    document.querySelectorAll('[data-driver-help-back]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.user-help-detail').forEach((detail) => detail.classList.add('hidden'));
            document.getElementById('driver-help-home')?.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
</script>

</body>
</html>
