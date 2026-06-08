ALTER TABLE Reportes
    ADD COLUMN IF NOT EXISTS ID_usuario_reportante INT NULL AFTER ID_publicacion;

ALTER TABLE Reportes
    ADD CONSTRAINT fk_reportes_usuario_reportante
        FOREIGN KEY (ID_usuario_reportante) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL;
