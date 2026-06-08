ALTER TABLE Reservas
    ADD COLUMN IF NOT EXISTS TipoPasaje ENUM('propio','tercero') NOT NULL DEFAULT 'propio' AFTER CodigoAcceso,
    ADD COLUMN IF NOT EXISTS PasajeroNombre VARCHAR(100) NULL AFTER TipoPasaje,
    ADD COLUMN IF NOT EXISTS PasajeroApellido VARCHAR(100) NULL AFTER PasajeroNombre,
    ADD COLUMN IF NOT EXISTS PasajeroDNI VARCHAR(20) NULL AFTER PasajeroApellido,
    ADD COLUMN IF NOT EXISTS PasajeroTelefono VARCHAR(20) NULL AFTER PasajeroDNI,
    ADD COLUMN IF NOT EXISTS PasajeroCorreo VARCHAR(150) NULL AFTER PasajeroTelefono,
    ADD COLUMN IF NOT EXISTS ID_usuario_responsable INT NULL AFTER PasajeroCorreo;
