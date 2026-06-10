<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/header.php';
?>

<div class="user-help-shell">
    <section class="card">
        <div id="help-home" class="user-help-home">
            <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <h1 class="page-title">Manual de ayuda</h1>
                    <p class="page-subtitle" style="margin-bottom:0;">Guia para usar MOVEON como pasajero: buscar, reservar, ver reservas, confirmar llegada, calificar y pedir soporte.</p>
                </div>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-outline">Ir a buscar viajes</a>
            </div>

            <div class="user-help-card-grid">
                <button class="user-help-card" type="button" data-help-open="registro">
                    <div>
                        <div class="user-help-card-icon">R</div>
                        <h3>Registro y cuenta</h3>
                        <p>Creacion de cuenta, datos personales, DNI, telefono y seguridad del perfil.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-help-open="buscar">
                    <div>
                        <div class="user-help-card-icon">B</div>
                        <h3>Buscar viajes</h3>
                        <p>Filtros por origen, destino, fecha, disponibilidad y ordenamiento profesional.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-help-open="reservar">
                    <div>
                        <div class="user-help-card-icon">P</div>
                        <h3>Reservar pasaje</h3>
                        <p>Reserva para vos o para un tercero, codigo de validacion y datos visibles.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-help-open="reservas">
                    <div>
                        <div class="user-help-card-icon">M</div>
                        <h3>Mis reservas</h3>
                        <p>Viajes activos, historial, cancelaciones, detalle del conductor y estado del viaje.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-help-open="postviaje">
                    <div>
                        <div class="user-help-card-icon">C</div>
                        <h3>Despues del viaje</h3>
                        <p>Confirmar llegada, calificar al conductor y reportar problemas si corresponde.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>

                <button class="user-help-card" type="button" data-help-open="seguridad">
                    <div>
                        <div class="user-help-card-icon">S</div>
                        <h3>Seguridad y soporte</h3>
                        <p>Proteccion de datos sensibles, reportes, soporte y recomendaciones de uso.</p>
                    </div>
                    <span>Abrir guia</span>
                </button>
            </div>
        </div>

        <div id="help-registro" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">R</div>
                    <div>
                        <h2>Registro y cuenta</h2>
                        <p class="text-muted" style="margin:0;">Todo usuario empieza como pasajero y puede completar su perfil para operar con mas confianza.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Datos necesarios</h3>
                    <ul>
                        <li>Nombre y apellido reales.</li>
                        <li>DNI y telefono valido para identificacion.</li>
                        <li>Correo electronico unico.</li>
                        <li>Foto de DNI frente y dorso cuando el registro lo solicite.</li>
                    </ul>
                </div>
                <div class="user-help-info-box">
                    <h3>Perfil</h3>
                    <p>Desde Mi perfil podes ver la informacion guardada, foto, descripcion y preferencias. Para cambiar datos se usa Editar perfil y se confirma antes de guardar.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Buenas practicas</h3>
                    <p>Usa datos reales, no compartas tu clave y manten actualizado el telefono porque el conductor puede necesitar verificar identidad al abordar.</p>
                </div>
            </div>
        </div>

        <div id="help-buscar" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">B</div>
                    <div>
                        <h2>Buscar viajes</h2>
                        <p class="text-muted" style="margin:0;">La pantalla de inicio muestra viajes disponibles sin exponer datos sensibles antes de reservar.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Filtros</h3>
                    <ul>
                        <li>Ciudad de salida.</li>
                        <li>Ciudad de llegada.</li>
                        <li>Orden por precio, fecha o disponibilidad segun la pantalla.</li>
                    </ul>
                </div>
                <div class="user-help-info-box">
                    <h3>Informacion visible</h3>
                    <p>Antes de iniciar sesion o reservar, solo se muestran datos generales: ruta, fecha, hora, precio y asientos. El punto de encuentro, patente e imagenes del auto se protegen.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Consejo</h3>
                    <p>Si no aparece una ruta, prueba buscar solo por origen o solo por destino. Esas busquedas ayudan a detectar demanda para futuros viajes.</p>
                </div>
            </div>
        </div>

        <div id="help-reservar" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">P</div>
                    <div>
                        <h2>Reservar pasaje</h2>
                        <p class="text-muted" style="margin:0;">La reserva confirma tu lugar y habilita la informacion sensible necesaria para viajar.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Para mi</h3>
                    <p>Si el pasaje es para vos, el sistema usa tus datos de registro. No se pueden editar desde la reserva para mantener coherencia de identidad.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Para un tercero</h3>
                    <p>Si reservas para otra persona, debes cargar sus datos. Vos seguis figurando como responsable de la reserva ante el conductor y administracion.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Codigo de validacion</h3>
                    <p>Al reservar se genera un codigo que debe mostrarse al conductor al abordar. Sirve para validar que la persona corresponde a la reserva.</p>
                </div>
            </div>
        </div>

        <div id="help-reservas" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">M</div>
                    <div>
                        <h2>Mis reservas</h2>
                        <p class="text-muted" style="margin:0;">Organiza tus viajes activos y tu historial con el mismo estilo de navegacion por pestañas.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Reservas activas</h3>
                    <p>Muestra viajes proximos o vigentes, estado de la reserva, conductor, ruta y acciones disponibles como ver detalle o cancelar si corresponde.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Historial</h3>
                    <p>Lista viajes finalizados. Desde ahi podes calificar al conductor, ver acompanantes si aplica y reportar problemas de viajes ya realizados.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Notificaciones</h3>
                    <p>El sistema avisa cancelaciones, viajes finalizados y acciones pendientes. Cada notificacion puede tener botones para confirmar llegada, calificar o reportar.</p>
                </div>
            </div>
        </div>

        <div id="help-postviaje" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">C</div>
                    <div>
                        <h2>Despues del viaje</h2>
                        <p class="text-muted" style="margin:0;">El cierre del viaje ayuda a mantener reputacion, seguridad y control administrativo.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Confirmar llegada</h3>
                    <p>Cuando un viaje finaliza, podes confirmar que llegaste correctamente. Esa confirmacion queda visible para administracion en viajes finalizados.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Calificar</h3>
                    <p>La calificacion del conductor se promedia con otras puntuaciones y luego aparece como reputacion en sus viajes publicados.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Reportar</h3>
                    <p>Si hubo un problema, podes reportar al conductor. Tu identidad no se muestra al conductor, pero administracion si la ve para auditar el caso.</p>
                </div>
            </div>
        </div>

        <div id="help-seguridad" class="user-help-detail hidden">
            <div class="user-help-detail-head">
                <div class="user-help-detail-title">
                    <div class="user-help-detail-icon">S</div>
                    <div>
                        <h2>Seguridad y soporte</h2>
                        <p class="text-muted" style="margin:0;">La plataforma limita informacion sensible y centraliza reclamos para revision administrativa.</p>
                    </div>
                </div>
                <button class="btn btn-outline" type="button" data-help-back>Volver al manual</button>
            </div>
            <div class="user-help-detail-grid">
                <div class="user-help-info-box">
                    <h3>Datos protegidos</h3>
                    <p>Punto de encuentro, patente e imagenes del auto se muestran solo cuando corresponde, principalmente despues de reservar.</p>
                </div>
                <div class="user-help-info-box">
                    <h3>Soporte</h3>
                    <p>Usa soporte para problemas de cuenta, errores de reserva, dudas de seguridad o inconvenientes que no entren como reporte de viaje.</p>
                    <a href="<?= BASE_URL ?>soporte.php" class="btn" style="margin-top:8px;">Contactar soporte</a>
                </div>
                <div class="user-help-info-box">
                    <h3>Recomendaciones</h3>
                    <ul>
                        <li>Verifica ruta, fecha y horario antes de reservar.</li>
                        <li>No compartas datos sensibles por fuera de la plataforma si no es necesario.</li>
                        <li>Reporta conductas peligrosas o incumplimientos.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.querySelectorAll('[data-help-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.helpOpen;
            document.getElementById('help-home')?.classList.add('hidden');
            document.querySelectorAll('.user-help-detail').forEach((detail) => detail.classList.add('hidden'));
            document.getElementById('help-' + key)?.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    document.querySelectorAll('[data-help-back]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.user-help-detail').forEach((detail) => detail.classList.add('hidden'));
            document.getElementById('help-home')?.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
</script>

</body>
</html>
