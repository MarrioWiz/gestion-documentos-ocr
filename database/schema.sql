-- Script de referencia: esta base de datos se crea automáticamente con las
-- migraciones de Laravel (`php artisan migrate`). Este archivo documenta el
-- esquema resultante de las tablas propias del sistema; no hace falta
-- ejecutarlo. (Las tablas internas de Laravel —sessions, cache, jobs,
-- password_reset_tokens— se omiten.)

CREATE DATABASE IF NOT EXISTS gestion_documentos
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    es_admin TINYINT(1) NOT NULL DEFAULT 0,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE personas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curp VARCHAR(18) NULL UNIQUE,
    nombre_completo VARCHAR(150) NULL,
    fecha_nacimiento DATE NULL,
    entidad_nacimiento VARCHAR(60) NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- tipo_documento: ine | curp | acta_nacimiento | pasaporte | cartilla_militar | licencia_conducir
-- (la lista válida vive en App\Models\Persona::TIPOS_DOCUMENTO)
CREATE TABLE documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    persona_id BIGINT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(40) NOT NULL,
    numero_documento VARCHAR(100) NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    nombre_original VARCHAR(255) NULL,
    archivo_hash VARCHAR(64) NULL,
    texto_extraido TEXT NULL,
    subido_por BIGINT UNSIGNED NULL,
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE CASCADE,
    FOREIGN KEY (subido_por) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unico_doc_persona (persona_id, tipo_documento),
    INDEX documentos_archivo_hash_index (archivo_hash)
);

-- accion: login | logout | login_fallido | subida | reemplazo | consulta | eliminacion | usuarios | reporte
CREATE TABLE historial_accesos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    accion VARCHAR(30) NOT NULL,
    descripcion TEXT NULL,
    ip VARCHAR(45) NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX historial_accesos_accion_index (accion),
    INDEX historial_accesos_fecha_index (fecha)
);
