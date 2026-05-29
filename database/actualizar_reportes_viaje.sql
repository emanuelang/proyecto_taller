ALTER TABLE Reportes
    ADD COLUMN IF NOT EXISTS ID_publicacion INT NULL AFTER ID_conductor;

ALTER TABLE Reportes
    ADD CONSTRAINT fk_reportes_publicacion
        FOREIGN KEY (ID_publicacion) REFERENCES Publicaciones(ID_publicacion) ON DELETE SET NULL;
