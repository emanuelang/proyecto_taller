USE carpooling;

ALTER TABLE Publicaciones
    ADD COLUMN IF NOT EXISTS DistanciaKM INT NULL AFTER Estado,
    ADD COLUMN IF NOT EXISTS DuracionMinutos INT NULL AFTER DistanciaKM;
