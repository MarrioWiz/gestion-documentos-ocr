# DocuVault — Gestión de expedientes de identidad con OCR

Sistema web en **Laravel 13** para integrar el expediente de cada persona (INE, CURP, acta de nacimiento, pasaporte, cartilla militar y licencia) a partir de **fotos o PDF de sus documentos**. El sistema **lee cada documento con OCR**, detecta qué documento es y a quién pertenece (por su CURP), y lo agrega automáticamente a su expediente.

> **Ejemplo:** una persona ya está registrada con su INE y su CURP. Si subes de golpe su INE, su CURP y su acta de nacimiento, el sistema reconoce que es la misma persona, **omite** la INE y la CURP porque ya estaban, y **agrega solo el acta**.

---

## 🚀 Cómo probarlo (5 minutos)

### Usuario para entrar

| Correo | Contraseña |
|---|---|
| `admin@gestion.test` | `Admin12345` |

> No es un correo real: es solo el usuario de acceso. La app **no envía ni recibe correos**. Esta cuenta se crea automáticamente al ejecutar `php artisan migrate --seed` (paso 4). Se puede cambiar en `.env` con `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de ese paso.

### Pasos

1. Instalar **PHP 8.3**, **Composer** y **MySQL** (por ejemplo con [Laragon](https://laragon.org)).
2. Instalar **Tesseract OCR** ([instalador para Windows](https://github.com/UB-Mannheim/tesseract/wiki)) y **mutool** para leer PDF (`winget install ArtifexSoftware.mutool`). El sistema los encuentra solo si están en la ruta normal de instalación o en el PATH.
3. Descargar el proyecto e instalar dependencias:
   ```bash
   git clone https://github.com/MarrioWiz/gestion-documentos-ocr.git
   cd gestion-documentos-ocr
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
4. Crear una base de datos vacía llamada `gestion_documentos` (si MySQL tiene contraseña, ponerla en `DB_PASSWORD` del `.env`) y ejecutar:
   ```bash
   php artisan migrate --seed
   php artisan serve
   ```
5. Abrir **http://127.0.0.1:8000** y entrar con el usuario de arriba.

### Qué probar

1. **Carga masiva** → subir `tests/Fixtures/documentos/carlos_ine.png` y `carlos_curp.pdf`: se registra a *Carlos Daniel Mendoza Ruiz* con 2 documentos.
2. **Carga masiva** otra vez → subir los tres archivos de Carlos (`carlos_ine.png`, `carlos_curp.pdf`, `carlos_acta.jpg`): la INE y la CURP aparecen como **"Ya existía"** y el acta se **agrega sola** a su expediente.
3. Subir `maria_acta_escaneada.pdf`, `maria_curp_girada.jpg` (foto de lado) y `julian_ine.webp`: se registran dos personas nuevas.
4. Revisar **Personas** (checklist y % de avance), **Panel** (estadísticas), **Historial** (auditoría) y **Reporte** (Excel).

Todos los documentos de `tests/Fixtures/documentos` son **ficticios**, generados para este proyecto.

---

## Funcionalidades

| Módulo | Qué hace |
|---|---|
| **Carga masiva** | Arrastras muchos archivos de una o varias personas; cada uno se analiza y se guarda solo en el expediente correcto. Te muestra qué se guardó, qué ya existía y qué requiere revisión. |
| **Subida asistida** | Subes un archivo, el OCR llena el formulario (CURP, nombre, fecha, entidad, tipo y número de documento) y te avisa si la persona ya existe y qué le falta. |
| **Expedientes** | Checklist por persona con % de avance, miniaturas y búsqueda/filtro de completos e incompletos. |
| **Panel** | Estadísticas: personas, documentos, expedientes completos, documentos por tipo y actividad de los últimos 14 días. |
| **Reporte Excel (CSV)** | Estado del expediente de cada persona y los documentos que le faltan. |
| **Seguridad** | Inicio de sesión obligatorio, roles (administrador / capturista), archivos privados, límite de intentos de login e historial de auditoría. |

## Cómo funciona el OCR

```
Archivo (JPG, PNG, WEBP o PDF)
   │
   ├─ PDF digital ─────────► se extrae el texto nativo (mutool), sin OCR
   ├─ PDF escaneado ───────► se convierten hasta 3 páginas a imagen de 300 DPI
   └─ Imagen
        │
        ▼
 Preparación: rotación EXIF + detección de giro con Tesseract OSD,
 ampliación, escala de grises y contraste
        │
        ▼
 Varias lecturas con Tesseract (español + inglés); se detiene en cuanto tiene los datos:
   1. bloque (psm 6)   2. texto disperso (psm 11)   3. fondo normalizado
   4. por franjas      5. fondo normalizado + disperso
        │
        ▼
 Análisis del texto
   • Tipo de documento: puntaje por frases características de cada tipo
   • CURP: patrón oficial + corrección de confusiones del OCR (O↔0, I↔1, S↔5…)
           + dígito verificador oficial de RENAPO
   • Nombre: por etiqueta; se reordena con las iniciales de la CURP
   • Fecha y entidad de nacimiento: derivadas de la CURP
   • Número: clave de elector (INE), número de acta, pasaporte, matrícula…
        │
        ▼
 Reglas de registro (app/Services/RegistroDocumentos.php)
   • Mismo archivo ya subido (huella SHA-256) ─────────► se omite
   • La persona existe y no tiene ese documento ───────► se agrega y se completan sus datos faltantes
   • La persona ya tiene ese documento ────────────────► se omite (reemplazar es manual y con confirmación)
   • CURP nueva con dígito verificador correcto ───────► se registra a la persona
   • CURP que no pasa el verificador ──────────────────► se asocia solo si difiere en 1 carácter
                                                         de una persona existente; si no, va a revisión
   • Sin CURP o sin tipo reconocido ───────────────────► revisión manual (con sugerencia por nombre)
```

## Requisitos

- PHP 8.3 con las extensiones `gd`, `exif`, `fileinfo`, `pdo_mysql` (y `pdo_sqlite` para las pruebas)
- MySQL 8 (Laragon, XAMPP, etc.)
- Composer
- [Tesseract OCR 5](https://github.com/UB-Mannheim/tesseract/wiki). Los datos de idioma `spa`, `eng` y `osd` ya vienen en `storage/tessdata`.
- [MuPDF / mutool](https://mupdf.com/releases) para PDF (en Windows: `winget install ArtifexSoftware.mutool`)

## Instalación

```bash
git clone <url-del-repositorio> gestion-documentos-app
cd gestion-documentos-app
composer install
cp .env.example .env
php artisan key:generate
```

Edita `.env`:

```dotenv
DB_DATABASE=gestion_documentos
DB_USERNAME=root
DB_PASSWORD=

# Opcionales: solo si Tesseract o mutool no están en su ruta normal ni en el PATH
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
MUTOOL_PATH=

ADMIN_EMAIL=admin@gestion.test
ADMIN_PASSWORD=Admin12345
```

Crea la base de datos `gestion_documentos` y ejecuta:

```bash
php artisan migrate --seed     # tablas + usuario administrador
php artisan serve              # o usa el host virtual de Laragon
```

Entra con el correo y la contraseña de `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

## Documentos de ejemplo (ficticios)

En `tests/Fixtures/documentos` hay documentos **ficticios** para probar el sistema sin usar datos reales:

| Archivo | Qué prueba |
|---|---|
| `carlos_ine.png` | INE con fondo de color y patrón de seguridad |
| `carlos_curp.pdf` | Constancia de CURP en PDF digital (texto nativo) |
| `carlos_acta.jpg` | Acta de nacimiento (nombre antes que apellidos) |
| `maria_acta_escaneada.pdf` | Acta en PDF escaneado (solo imagen) |
| `maria_curp_girada.jpg` | CURP fotografiada de lado (90°) |
| `julian_ine.webp` | INE en WEBP, nacido en Querétaro (clave `QT`) |

**Demostración sugerida:** en *Carga masiva* sube primero `carlos_ine.png` y `carlos_curp.pdf` (se crea la persona). Después sube los tres archivos de Carlos: la INE y la CURP se omiten y **solo se agrega el acta**.

Para regenerarlos: `php artisan documentos:generar-ejemplos`

## Pruebas automáticas

```bash
php artisan test                        # 42 pruebas (incluye OCR real)
php artisan test --exclude-group=ocr    # sin OCR real (más rápido)
```

Cubren: validación y corrección de CURP, clasificación de documentos, la regla "agregar solo lo que falta", archivos duplicados, datos faltantes, login, permisos por rol, archivos privados, reporte CSV y el OCR real sobre los documentos de ejemplo.

> Las pruebas usan SQLite en memoria. Si ves `could not find driver`, activa `extension=pdo_sqlite` en tu `php.ini`.

## Estructura principal

```
app/
├── Services/
│   ├── DocumentoOcrService.php     # lectura OCR (imágenes, PDF, giro, varias pasadas) y extracción de datos
│   ├── ClasificadorDocumentos.php  # decide el tipo de documento por puntaje
│   ├── Curp.php                    # reglas oficiales de la CURP y corrección de errores de OCR
│   ├── RegistroDocumentos.php      # a qué persona pertenece cada documento y si se guarda
│   └── Historial.php               # bitácora de auditoría
├── Http/Controllers/               # Documentos, Personas, Panel, Login, Usuarios, Historial
├── Models/                         # Persona, Documento, HistorialAcceso, User
└── Console/Commands/               # generar documentos de ejemplo, limpiar temporales
resources/views/                    # Blade + Bootstrap 5 (tema oscuro)
tests/                              # Unit, Feature y Fixtures/documentos
```

## Seguridad y privacidad

- Todas las rutas requieren sesión. No hay registro público: el administrador crea las cuentas.
- Los documentos se guardan en `storage/app/private` y solo se sirven a usuarios con sesión; cada consulta queda registrada.
- Máximo 5 intentos de inicio de sesión por minuto.
- Historial de inicios de sesión, intentos fallidos, subidas, reemplazos, consultas y eliminaciones, con IP.
- El repositorio **no incluye** `.env`, bases de datos ni documentos subidos.

## Tecnologías

Laravel 13 · PHP 8.3 · MySQL · Blade · Bootstrap 5 · Tesseract OCR 5 · MuPDF · PHPUnit
