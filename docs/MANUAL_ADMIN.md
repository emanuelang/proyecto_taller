# Manual administrativo de MOVEON

Este manual acompana la pantalla `public/admin/manual_admin.php`.

## Dashboard

El dashboard muestra indicadores principales y un modulo de reportes gerenciales. Los reportes permiten seleccionar fecha desde, fecha hasta y tipo de reporte.

Reportes disponibles:

- Actividad general.
- Reporte tecnico general.
- Usuarios.
- Viajes.
- Rutas mas utilizadas.
- Reportes y reclamos.

Cada reporte contiene metricas, tabla y grafico. Para agregar otro reporte se debe sumar una opcion al arreglo `$reporte_tipos` y crear un caso en el `switch` de `public/admin/dashboard.php`.

El reporte tecnico general funciona como tablero de comportamiento del sistema. Muestra usuarios suspendidos o eliminados, vehiculos registrados por estado, solicitudes de conductores, viajes, reservas, confirmaciones, reportes, soporte y notificaciones. Como no existe una tabla de auditoria administrativa, este reporte reconstruye el log operativo desde los datos reales disponibles.

## Backups

Desde el bloque Sistema se puede exportar SQL, importar backup SQL y descargar un paquete de recuperacion.

El paquete de recuperacion incluye la base de datos, plantillas de configuracion e instrucciones para levantar el proyecto ante un desastre. No reemplaza una copia del codigo fuente, que debe recuperarse desde GitHub o desde una copia completa del proyecto.

## Conductores

La administracion separa:

- Pendientes.
- Aprobados activos.
- Suspendidos.
- Eliminados permanentemente.

Las acciones de suspension o rechazo deben usarse cuando hay evidencia en reportes o incumplimiento de reglas.

## Vehiculos

Los vehiculos se separan por estado. Las imagenes se revisan desde el panel en modal para no abrir pestanas nuevas.

## Usuarios

Permite controlar cuentas activas, suspendidas y eliminadas. El administrador puede revisar imagenes de DNI para validar identidad.

## Viajes

La seccion de viajes separa activos y finalizados. En finalizados se pueden consultar reportes y confirmaciones de pasajeros.

## Reportes

Existen reportes contra conductores y pasajeros. Para el usuario reportado la denuncia funciona como anonima, pero el administrador ve quien reporto para poder auditar el caso.

## Soporte

Los tickets se organizan entre pendientes y resueltos. Cada ticket debe tener seguimiento hasta resolucion o descarte.
