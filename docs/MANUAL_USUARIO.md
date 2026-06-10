# Manual de usuario

## Administrador

El administrador controla la operacion general de MOVEON.

Funciones disponibles:

- Ver indicadores y reportes gerenciales en el dashboard.
- Aprobar, suspender o eliminar conductores.
- Revisar vehiculos pendientes, aprobados y eliminados.
- Gestionar usuarios activos, suspendidos y eliminados.
- Revisar viajes activos y finalizados.
- Ver reportes contra conductores o pasajeros.
- Responder tickets de soporte.
- Exportar e importar backups SQL.

Flujo habitual:

1. Entrar al panel de administracion.
2. Revisar alertas y pendientes.
3. Atender conductores, vehiculos, reportes y soporte.
4. Usar reportes gerenciales para controlar actividad por periodo.
5. Exportar backup antes de cambios importantes.

Preguntas frecuentes:

- Donde veo reclamos: en `Reportes`.
- Donde veo viajes con conflictos: en `Viajes`, filtro finalizados, columna reportes.
- Donde hago backups: en el dashboard, bloque Sistema.

## Conductor

El conductor publica viajes y gestiona pasajeros.

Funciones disponibles:

- Editar perfil y foto.
- Registrar vehiculos.
- Crear viajes.
- Ver viajes activos e historial.
- Ver pasajeros de cada viaje.
- Validar o cancelar pasajeros segun reglas del sistema.
- Recibir notificaciones al finalizar viajes.
- Confirmar si el viaje termino correctamente o reportar pasajeros.

Flujo habitual:

1. Completar perfil y solicitud de conductor.
2. Esperar aprobacion del administrador.
3. Registrar vehiculo.
4. Crear viaje.
5. Revisar pasajeros.
6. Al finalizar, confirmar llegada o reportar incidentes.

## Pasajero

El pasajero busca y reserva viajes.

Funciones disponibles:

- Buscar viajes.
- Ver informacion publica del viaje.
- Reservar para si mismo o para un tercero.
- Ver reservas activas e historial.
- Confirmar llegada al finalizar.
- Calificar conductor.
- Reportar problemas.

Flujo habitual:

1. Registrarse con datos personales y DNI.
2. Buscar viaje.
3. Reservar.
4. Acceder a datos sensibles solo despues de reservar.
5. Confirmar llegada o reportar un problema al finalizar.

## Seguridad de datos

Antes de iniciar sesion no se muestran datos sensibles de conductores. Despues de iniciar sesion, datos como punto de encuentro, patente o imagenes del vehiculo se muestran solo cuando existe reserva asociada.
