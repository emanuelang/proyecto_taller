USE carpooling;

ALTER TABLE Calificaciones
    ADD COLUMN IF NOT EXISTS ID_reserva INT NULL AFTER ID_conductor,
    ADD COLUMN IF NOT EXISTS Fecha DATETIME DEFAULT CURRENT_TIMESTAMP AFTER ID_reserva;

ALTER TABLE Calificaciones
    ADD INDEX IF NOT EXISTS idx_calificaciones_reserva (ID_reserva);
