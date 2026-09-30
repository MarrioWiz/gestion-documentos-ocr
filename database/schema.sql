-- Script de referencia: esta base de datos ya fue creada automáticamente
-- por las migraciones de Laravel (database/migrations/*.php).
-- Este archivo documenta el esquema resultante; no hace falta ejecutarlo
-- si ya corriste `php artisan migrate`.

CREATE DATABASE IF NOT EXISTS gestion_documentos
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

CREATE TABLE personas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curp VARCHAR(18) UNIQUE NULL,
    nombre_completo VARCHAR(150) NULL,
    fecha_nacimiento DATE NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE documentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    persona_id INT UNSIGNED NOT NULL,
    tipo_documento ENUM('ine','pasaporte','cartilla_militar','curp','licencia_conducir') NOT NULL,
    numero_documento VARCHAR(100) NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    texto_extraido TEXT NULL,
    fecha_carga DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    UNIQUE KEY unico_doc_persona (persona_id, tipo_documento)
);
