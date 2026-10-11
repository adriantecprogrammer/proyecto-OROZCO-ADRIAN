CREATE TABLE IF NOT EXISTS libros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    anio_publicacion SMALLINT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'disponible',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_estado CHECK (estado IN ('disponible', 'prestado')),
    CONSTRAINT chk_anio CHECK (anio_publicacion BETWEEN 1000 AND 2100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO libros (titulo, autor, isbn, anio_publicacion, estado) VALUES
('Cien años de soledad', 'Gabriel García Márquez', '978-0307474728', 1967, 'disponible'),
('El Aleph', 'Jorge Luis Borges', '978-0142437889', 1949, 'prestado'),
('Pedro Páramo', 'Juan Rulfo', '978-8437604947', 1955, 'disponible');