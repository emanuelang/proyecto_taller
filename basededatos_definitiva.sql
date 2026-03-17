CREATE DATABASE IF NOT EXISTS carpooling;
USE carpooling;

-- 1. Tabla principal de usuarios
CREATE TABLE Usuarios (
    ID_usuario INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Apellido VARCHAR(100) NOT NULL,
    DNI VARCHAR(20) UNIQUE NOT NULL,
    Correo VARCHAR(150) UNIQUE NOT NULL,
    Telefono VARCHAR(20),
    Contraseña VARCHAR(255) NOT NULL
);

-- 2. Subtipos de usuarios
CREATE TABLE Pasajeros (
    ID_pasajero INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE Conductores (
    ID_conductor INT AUTO_INCREMENT PRIMARY KEY,
    LicenciaConducir VARCHAR(100) NOT NULL,
    SeguroVehiculo VARCHAR(100) NOT NULL,
    CuentaBancaria VARCHAR(100) NOT NULL,
    Estado VARCHAR(50) DEFAULT 'Pendiente',
    FechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ID_usuario INT NOT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE Administradores (
    ID_administrador INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

-- 3. Entidades Adicionales
CREATE TABLE Vehiculos (
    ID_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
    CantidadAsientos INT NOT NULL,
    Color VARCHAR(50) NOT NULL,
    Modelo VARCHAR(100) NOT NULL,
    Marca VARCHAR(100) NOT NULL,
    Foto VARCHAR(255)
);

CREATE TABLE Publicaciones (
    ID_publicacion INT AUTO_INCREMENT PRIMARY KEY,
    CiudadOrigen VARCHAR(100) NOT NULL,
    CiudadDestino VARCHAR(100) NOT NULL,
    HoraSalida DATETIME NOT NULL,
    Precio DECIMAL(10,2) NOT NULL,
    Estado VARCHAR(50) NOT NULL DEFAULT 'Activa',
    ID_vehiculo INT NOT NULL,
    FOREIGN KEY (ID_vehiculo) REFERENCES Vehiculos(ID_vehiculo)
);

CREATE TABLE Reservas (
    ID_reserva INT AUTO_INCREMENT PRIMARY KEY,
    Estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    FechaReserva DATETIME DEFAULT CURRENT_TIMESTAMP,
    ID_publicacion INT NOT NULL,
    FOREIGN KEY (ID_publicacion) REFERENCES Publicaciones(ID_publicacion) ON DELETE CASCADE
);

CREATE TABLE Pagos (
    ID_pago INT AUTO_INCREMENT PRIMARY KEY,
    Monto DECIMAL(10,2) NOT NULL,
    Estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    Fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    ID_reserva INT NOT NULL,
    FOREIGN KEY (ID_reserva) REFERENCES Reservas(ID_reserva) ON DELETE CASCADE
);

CREATE TABLE Calificaciones (
    ID_calificacion INT AUTO_INCREMENT PRIMARY KEY,
    Comentario TEXT,
    Puntuacion INT CHECK(Puntuacion >= 1 AND Puntuacion <= 5),
    ID_pasajero INT NOT NULL,
    ID_conductor INT NOT NULL,
    FOREIGN KEY (ID_pasajero) REFERENCES Pasajeros(ID_pasajero) ON DELETE CASCADE,
    FOREIGN KEY (ID_conductor) REFERENCES Conductores(ID_conductor) ON DELETE CASCADE
);

CREATE TABLE Reportes (
    ID_reporte INT AUTO_INCREMENT PRIMARY KEY,
    Hora TIME NOT NULL,
    Fecha DATE NOT NULL,
    Descripcion TEXT NOT NULL,
    ID_conductor INT NOT NULL,
    FOREIGN KEY (ID_conductor) REFERENCES Conductores(ID_conductor) ON DELETE CASCADE
);

-- 4. Tablas Intermedias (Relaciones N:M)
CREATE TABLE ConductorVehiculo (
    ID_conductor INT,
    ID_vehiculo INT,
    PRIMARY KEY (ID_conductor, ID_vehiculo),
    FOREIGN KEY (ID_conductor) REFERENCES Conductores(ID_conductor) ON DELETE CASCADE,
    FOREIGN KEY (ID_vehiculo) REFERENCES Vehiculos(ID_vehiculo) ON DELETE CASCADE
);

CREATE TABLE ConductorPublicacion (
    ID_conductor INT,
    ID_publicacion INT,
    PRIMARY KEY (ID_conductor, ID_publicacion),
    FOREIGN KEY (ID_conductor) REFERENCES Conductores(ID_conductor) ON DELETE CASCADE,
    FOREIGN KEY (ID_publicacion) REFERENCES Publicaciones(ID_publicacion) ON DELETE CASCADE
);

CREATE TABLE PasajerosReservas (
    ID_pasajero INT,
    ID_reserva INT,
    PRIMARY KEY (ID_pasajero, ID_reserva),
    FOREIGN KEY (ID_pasajero) REFERENCES Pasajeros(ID_pasajero) ON DELETE CASCADE,
    FOREIGN KEY (ID_reserva) REFERENCES Reservas(ID_reserva) ON DELETE CASCADE
);

CREATE TABLE AdministradorUsuario (
    ID_administrador INT,
    ID_usuario INT,
    PRIMARY KEY (ID_administrador, ID_usuario),
    FOREIGN KEY (ID_administrador) REFERENCES Administradores(ID_administrador) ON DELETE CASCADE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE AdministradorPublicacion (
    ID_administrador INT,
    ID_publicacion INT,
    PRIMARY KEY (ID_administrador, ID_publicacion),
    FOREIGN KEY (ID_administrador) REFERENCES Administradores(ID_administrador) ON DELETE CASCADE,
    FOREIGN KEY (ID_publicacion) REFERENCES Publicaciones(ID_publicacion) ON DELETE CASCADE
);
