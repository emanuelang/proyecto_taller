CREATE TABLE IF NOT EXISTS ReportesPasajeros (
    ID_reporte_pasajero INT AUTO_INCREMENT PRIMARY KEY,
    ID_reserva INT NOT NULL,
    ID_usuario_reportado INT NOT NULL,
    ID_usuario_responsable INT NULL,
    ID_conductor INT NOT NULL,
    Motivo VARCHAR(80) NOT NULL,
    Descripcion TEXT NULL,
    Estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reportes_pasajeros_reserva
        FOREIGN KEY (ID_reserva) REFERENCES Reservas(ID_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_reportes_pasajeros_usuario_reportado
        FOREIGN KEY (ID_usuario_reportado) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_reportes_pasajeros_usuario_responsable
        FOREIGN KEY (ID_usuario_responsable) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL,
    CONSTRAINT fk_reportes_pasajeros_conductor
        FOREIGN KEY (ID_conductor) REFERENCES Conductores(ID_conductor) ON DELETE CASCADE
);
