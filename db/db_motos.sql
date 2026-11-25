-- ============================================================================
-- PROYECTO INTEGRADOR DE FIN DE CICLO (2026)
-- IES. MISIONEROS MONFORTIANOS - PROGRAMACIÓN WEB
-- Caso 4: Sistema Web para el Control de Fichas Técnicas e Inventario de Motos
-- Base de Datos: db_motos
-- ============================================================================

SET NAMES utf8mb4;  -- para que tildes y ñ se guarden bien

-- 1. CREACIÓN DE LA BASE DE DATOS
DROP DATABASE IF EXISTS db_motos;
CREATE DATABASE db_motos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_motos;

-- ============================================================================
-- 2. ESTRUCTURA DE TABLAS (Motor InnoDB para claves foráneas y cascada)
-- ============================================================================

-- Tabla: usuarios (administradores = backend, clientes = frontend)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(100) NOT NULL UNIQUE,            -- correo con el que inicia sesión
    password VARCHAR(255) NOT NULL,                  -- hash bcrypt (password_hash)
    telefono VARCHAR(20) NULL,
    rol ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla: marcas_moto (Catálogo de fabricantes de motocicletas)
CREATE TABLE marcas_moto (
    id_marca INT AUTO_INCREMENT PRIMARY KEY,
    nombre_marca VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Tabla: motos (Inventario de unidades, ficha técnica y relación con marca)
CREATE TABLE motos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vin_chasis VARCHAR(30) NOT NULL UNIQUE,
    modelo VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'naked',   -- naked | deportiva | scooter | trail
    cilindrada_cc INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    id_marca INT NOT NULL,
    foto VARCHAR(100) NULL,
    descripcion VARCHAR(500) NULL,
    estado ENUM('aprobada','pendiente','rechazada') NOT NULL DEFAULT 'aprobada',
    observacion VARCHAR(255) NULL,                   -- motivo cuando el admin rechaza
    id_usuario INT NULL,                             -- NULL = unidad del concesionario; si no, cliente que la publicó
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_motos_marca
        FOREIGN KEY (id_marca)
        REFERENCES marcas_moto(id_marca)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_motos_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 3. DATOS INICIALES PRECARGADOS (SEEDERS)
-- ============================================================================

-- Usuarios de prueba. El hash bcrypt corresponde a la contraseña 'password' en ambos.
INSERT INTO usuarios (id, nombre, usuario, password, telefono, rol) VALUES
(1, 'Administrador', 'admin@motos.pe',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '987654321', 'admin'),
(2, 'Carlos Quispe', 'cliente@motos.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '912345678', 'cliente');

-- Marcas comerciales (ID 1: Honda, ID 2: Yamaha, ID 3: Bajaj, ID 4: Suzuki, ID 5: TVS)
INSERT INTO marcas_moto (id_marca, nombre_marca) VALUES
(1, 'Honda'),
(2, 'Yamaha'),
(3, 'Bajaj'),
(4, 'Suzuki'),
(5, 'TVS');

-- Motos de demostración. Las fotos reales van en la carpeta uploads/ con estos nombres .jpg
-- Precios referenciales para la demo.
INSERT INTO motos (id, vin_chasis, modelo, tipo, cilindrada_cc, precio, id_marca, foto) VALUES
(1,  'CH-2026-X1',  'Pulsar NS200',      'naked',     200, 11500.00, 3, 'bajaj-pulsar-ns200.jpg'),
(2,  'CH-2026-X2',  'Pulsar RS200',      'deportiva', 200, 14900.00, 3, 'bajaj-pulsar-rs200.jpg'),
(3,  'CH-2026-X3',  'Boxer CT100',       'naked',     100,  4990.00, 3, 'bajaj-boxer-ct100.jpg'),
(4,  'CH-2026-X4',  'Dominar 400',       'deportiva', 373, 19900.00, 3, 'bajaj-dominar-400.jpg'),
(5,  'HN-2026-A1',  'CB190R',            'naked',     184, 10990.00, 1, 'honda-cb190r.jpg'),
(6,  'HN-2026-A2',  'XR150L',            'trail',     149,  9990.00, 1, 'honda-xr150l.jpg'),
(7,  'HN-2026-A3',  'Navi 110',          'scooter',   109,  6490.00, 1, 'honda-navi-110.jpg'),
(8,  'HN-2026-A4',  'CBR250R',           'deportiva', 249, 21900.00, 1, 'honda-cbr250r.jpg'),
(9,  'YM-2026-B1',  'MT-15',             'naked',     155, 13990.00, 2, 'yamaha-mt15.jpg'),
(10, 'YM-2026-B2',  'YZF-R15 V4',        'deportiva', 155, 15990.00, 2, 'yamaha-r15.jpg'),
(11, 'YM-2026-B3',  'XTZ150',            'trail',     149, 11490.00, 2, 'yamaha-xtz150.jpg'),
(12, 'YM-2026-B4',  'NMAX 155',          'scooter',   155, 12990.00, 2, 'yamaha-nmax-155.jpg'),
(13, 'SZ-2026-C1',  'Gixxer SF 250',     'deportiva', 249, 15490.00, 4, 'suzuki-gixxer-sf250.jpg'),
(14, 'TV-2026-D1',  'Apache RTR 200 4V', 'naked',     197,  9490.00, 5, 'tvs-apache-rtr200.jpg');

-- Motos publicadas por el cliente de prueba (una aprobada y una pendiente de revisión)
INSERT INTO motos (id, vin_chasis, modelo, tipo, cilindrada_cc, precio, id_marca, foto, descripcion, estado, id_usuario) VALUES
(15, 'CL-2024-0001', 'Pulsar NS200 (usada)', 'naked', 200, 8200.00, 3, NULL,
     'Año 2024, 9 000 km, único dueño, mantenimientos en concesionario.', 'aprobada', 2),
(16, 'CL-2023-0002', 'XTZ150 (usada)', 'trail', 149, 7500.00, 2, NULL,
     'Año 2023, 15 000 km, llantas nuevas, papeles en regla.', 'pendiente', 2);

-- ============================================================================
-- 4. CONSULTAS CLAVE DEL SISTEMA (Para uso en el CRUD y reportes PHP/PDO)
-- ============================================================================

-- A. Listado general de inventario con el nombre de su marca (INNER JOIN)
SELECT 
    m.id,
    m.vin_chasis,
    ma.nombre_marca AS marca,
    m.modelo,
    m.tipo,
    m.cilindrada_cc,
    m.precio,
    m.foto
FROM motos m
INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
WHERE m.estado = 'aprobada'
ORDER BY m.id DESC;

-- B. Control de inventario filtrado por marca específica (ej. Bajaj)
SELECT 
    m.id,
    m.vin_chasis,
    ma.nombre_marca AS marca,
    m.modelo,
    m.cilindrada_cc,
    m.precio
FROM motos m
INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
WHERE ma.id_marca = 3;

-- C. Control de inventario filtrado por rango de cilindrada (ej. de 150cc a 250cc)
SELECT 
    m.id,
    m.vin_chasis,
    ma.nombre_marca AS marca,
    m.modelo,
    m.cilindrada_cc,
    m.precio
FROM motos m
INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
WHERE m.cilindrada_cc BETWEEN 150 AND 250
ORDER BY m.cilindrada_cc ASC;

-- D. Resumen de cantidad de unidades y valorización por marca
SELECT 
    ma.id_marca,
    ma.nombre_marca,
    COUNT(m.id) AS total_unidades,
    COALESCE(SUM(m.precio), 0.00) AS valor_inventario
FROM marcas_moto ma
LEFT JOIN motos m ON ma.id_marca = m.id_marca AND m.estado = 'aprobada'
GROUP BY ma.id_marca, ma.nombre_marca
ORDER BY ma.nombre_marca ASC;

-- E. Catálogo: unidades por categoría (tipo)
SELECT tipo, COUNT(*) AS total, MIN(precio) AS desde
FROM motos
WHERE estado = 'aprobada'
GROUP BY tipo
ORDER BY total DESC;

-- F. Solicitudes de clientes pendientes de aprobación (panel admin)
SELECT m.id, m.modelo, m.precio, u.nombre AS cliente, u.telefono, m.creado_en
FROM motos m
INNER JOIN usuarios u ON m.id_usuario = u.id
WHERE m.estado = 'pendiente'
ORDER BY m.creado_en ASC;

-- G. Motos publicadas por un cliente (zona "Mis motos")
SELECT m.id, m.modelo, m.precio, m.estado, m.observacion
FROM motos m
WHERE m.id_usuario = 2
ORDER BY m.id DESC;
