# Plan de Trabajo y División de Tareas

En base al análisis previo, aquí están todas las tareas pendientes ordenadas por prioridad y divididas para que puedan trabajar en paralelo con tu compañero sin pisarse el código.

## 📋 Listado de Tareas Pendientes (Orden de Prioridad)

### Alta Prioridad (Core del Negocio)
1. **Control de Disponibilidad de Asientos:** Al reservar viaje, verificar cupo y restarlo. Al cancelar, liberar asiento.
2. **Pagos y Reservas Completas:** Formulario de tarjeta al reservar (simulado) e integración del estado de pago en el viaje.
3. **Inicio de Sesión y Panel del Administrador (Básico):** CRUD protegido para que el Admin pueda ver usuarios y viajes.
4. **Cálculo de Distancia y Hora Estimada:** Utilizar una API (como Google Maps Distance Matrix o una estimación base) al crear viajes.

### Media Prioridad (Reglas de Negocio Secundarias)
5. **Aprobación de Conductores:** El Admin debe revisar y cambiar el estado "pendiente" a "aprobado" de los conductores.
6. **Políticas de Cancelación (12hs):** Lógica en [cancelar_reserva.php](file:///c:/Users/Facun/Desktop/xamp/htdocs/Proyecto1/proyecto_taller/public/reservas/cancelar_reserva.php) que verifique la fecha del viaje y procese o deniegue el "reembolso".
7. **Modificar y Reutilizar Viajes:** Poder editar un viaje activo y usar uno anterior como plantilla para nueva fecha.
8. **Detalle completo de la publicación:** Mostrar los datos del vehículo y el conductor a los pasajeros antes de reservar.
9. **Módulo de Calificaciones al Conductor y Críticas Escritas:** Tablas y vistas para que el pasajero califique post-viaje.

### Baja Prioridad (Administración Avanzada y Extras)
10. **Reportes Anónimos:** Formulario para reportar conductores y panel en Admin para revisarlos.
11. **Estadísticas del Sistema:** Dashboard del Admin con totales (viajes, usuarios, reportes).
12. **Restricción y Suspensión de Cuentas:** Admin baneando/suspendiendo usuarios (con fecha límite o indefinido).
13. **Comisiones y Pagos al Conductor:** Simular la retención de comisión y el "depósito" a las 6hs de terminado.
14. **Envío de Correos Electrónicos:** (Opcional simulado) Confirmaciones, cancelaciones y recordatorios.
15. **Perfeccionar Registro de Conductor:** Añadir los datos faltantes (cuenta bancaria, foto de perfil, etc.).

---

## 👥 División de Trabajo Propuesta

Para evitar conflictos en el código (conflictos de Git o pisarse archivos), he dividido las tareas en dos módulos independientes.

### 🧑‍💻 Desarrollador 1: Módulo Pasajeros, Viajes y Pagos
*Se encargará de todo el flujo público, reservas, pagos y lógica de los viajes.*

1. **Gestión de Asientos:** Modificar [reservar_viaje.php](file:///c:/Users/Facun/Desktop/xamp/htdocs/Proyecto1/proyecto_taller/public/reservar_viaje.php) para validar y restar asientos, y [cancelar_reserva.php](file:///c:/Users/Facun/Desktop/xamp/htdocs/Proyecto1/proyecto_taller/public/reservas/cancelar_reserva.php) para liberarlos.
2. **Sistema de Pagos Simulados:** Crear vista de pago al reservar.
3. **Políticas de Cancelación:** Implementar la regla de 12 horas para reembolsos en las reservas.
4. **Cálculo de Distancia/Tiempo:** Integrar lógica para estimar duración y distancia al crear un viaje.
5. **Vista Detalle de Publicación:** Mejorar la UI para mostrar datos del vehículo, fotos y del conductor de forma extendida para los pasajeros.
6. **Reutilizar Viajes (Plantilla):** Opción en el panel del conductor para copiar un viaje pasado.
7. **Calificaciones al Conductor:** Crear tablas y la pantalla para que los pasajeros dejen puntajes/reseñas al terminar el trayecto.

### 👩‍💻 Desarrollador 2: Módulo Administrador, Usuarios y Moderación
*Se encargará de crear el ecosistema del Administrador y moderar a los usuarios.*

1. **Autenticación Admin & Panel:** Crear login exclusivo (`/admin/login.php`) y dashboard principal.
2. **Aprobación de Conductores:** Vista en el panel admin para ver solicitudes pendientes y aprobarlas.
3. **Gestión de Usuarios (Baneos):** Sistema para que el Admin suspenda cuentas temporal o permanentemente, impactando en el login.
4. **Moderación de Viajes:** Que el Admin pueda visualizar todos los viajes del sistema y eliminarlos si infringen reglas.
5. **Reportes Anónimos:** Crear el endpoint de envío de quejas y la vista del Admin para gestionarlas.
6. **Estadísticas del Sistema:** Mostrar KPIs (viajes totales, rentabilidad, usuarios activos) en el Dashboard Admin.
7. **Comisiones y Retenciones:** Lógica cron o simulada en el backend donde el Admin gestiona los pagos a conductores descontando el costo de uso de plataforma.
8. **Finalizar Registro Conductor:** Agregar campos físicos faltantes en la DB y [registro_conductor.php](file:///c:/Users/Facun/Desktop/xamp/htdocs/Proyecto1/proyecto_taller/public/registro_conductor.php) (CBU, Foto, Seguro).

*Sugerencia: El Desarrollador 1 trabajará mayoritariamente en la carpeta `public/` (reservas, viajes) y el Desarrollador 2 creará una nueva carpeta `public/admin/` y actualizará las tablas de base de datos (`usuarios`, `reportes`).*
