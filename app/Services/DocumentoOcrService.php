<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentoOcrService
{
    // Imágenes se leen directo; a los PDF primero se les intenta sacar el
    // texto "nativo" (PDF digitales, p. ej. la constancia de CURP descargada
    // de gob.mx) y, si son escaneos, se convierten a imagen con mutool.
    public const EXTENSIONES_SOPORTADAS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    // Un PDF con menos caracteres que esto en su texto nativo se considera
    // escaneado (solo trae la imagen) y se pasa por OCR.
    private const MIN_CARACTERES_TEXTO_NATIVO = 80;

    private const MAX_PAGINAS_PDF = 3;

    // Cada pasada rescata texto distinto en fotos difíciles (fondos de
    // colores, hologramas, sombras). Se ejecutan en orden y se detienen en
    // cuanto ya se tienen CURP verificada, tipo y nombre, así que una foto
    // buena solo paga la primera.
    //  gris        escala de grises + contraste, bloque uniforme (psm 6)
    //  disperso    misma imagen, texto disperso (psm 11): no depende de columnas
    //  normalizada fondo uniformado (ver normalizarFondo) + psm 6
    //  bandas      imagen partida en franjas horizontales, cada una psm 6
    private const PASADAS = [
        ['imagen' => 'gris', 'psm' => 6],
        ['imagen' => 'gris', 'psm' => 11],
        ['imagen' => 'normalizada', 'psm' => 6],
        ['imagen' => 'bandas', 'psm' => 6],
        ['imagen' => 'normalizada', 'psm' => 11],
    ];

    // Palabras de encabezado/etiquetas que NO son parte del nombre de la
    // persona; se usan para filtrar el respaldo heurístico de extraerNombre
    // cuando la etiqueta "NOMBRE" no se pudo leer.
    public const RUIDO_NOMBRE = [
        'INSTITUTO', 'NACIONAL', 'ELECTORAL', 'MEXICO', 'CREDENCIAL', 'VOTAR', 'VOTER', 'PARA',
        'DOMICILIO', 'ADDRESS', 'CURP', 'CLAVE', 'ELECTOR', 'SEXO', 'SEX', 'FECHA', 'DESDE',
        'NACIMIENTO', 'BIRTH', 'DATE', 'PLACE', 'VIGENCIA', 'EXPIRY', 'EMISION', 'EMISIÓN', 'ISSUE',
        'ESTADO', 'STATE', 'REGISTRO', 'MUNICIPIO', 'LOCALIDAD', 'SECCION', 'SECCIÓN', 'FIRMA',
        'MUESTRA', 'SAMPLE', 'EXTRANJERO', 'ABROAD', 'UNITED', 'STATES', 'NOMBRE', 'NAME', 'EDAD',
        'ACTA', 'CIVIL', 'ESTADOS', 'UNIDOS', 'MEXICANOS', 'CONSTANCIA', 'UNICA', 'ÚNICA', 'POBLACION',
        'POBLACIÓN', 'PRIMER', 'SEGUNDO', 'APELLIDO', 'APELLIDOS', 'NOMBRES', 'EJEMPLO', 'FICTICIO', 'VALIDEZ', 'OFICIAL',
        'PRESENTE', 'SECRETARIA', 'SECRETARÍA', 'GOBERNACION', 'GOBERNACIÓN', 'TRAMITE', 'TRÁMITE', 'GRATUITO',
        'CERTIFICADA', 'VERIFICADA', 'ENTIDAD', 'RENAPO', 'TELCURP',
    ];

    // Etiquetas que marcan el fin del bloque del nombre.
    private const FIN_BLOQUE_NOMBRE = '/DOMICILIO|CURP|CLAVE|SEXO|FECHA|VIGENCIA|REGISTRO|ESTADO|NACIONALIDAD|LUGAR|PADRE|MADRE|ENTIDAD|MUNICIPIO/i';

    /** @var string[] archivos temporales creados durante una extracción */
    private array $temporales = [];

    public function __construct(private readonly ClasificadorDocumentos $clasificador) {}

    public function soportaOcr(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONES_SOPORTADAS, true);
    }

    /**
     * @return array{texto: string, curp: ?string, curp_verificada: bool, nombre_completo: ?string, numero_documento: ?string, fecha_nacimiento: ?string, entidad_nacimiento: ?string, tipo_documento: ?string, puntaje_tipo: float, metodo: string, error?: string}
     */
    public function extraer(string $rutaArchivoAbsoluta): array
    {
        $this->temporales = [];

        try {
            if (! $this->esPdf($rutaArchivoAbsoluta)) {
                return $this->leerImagenes([$rutaArchivoAbsoluta]) + ['metodo' => 'ocr_imagen'];
            }

            $textoNativo = $this->textoNativoPdf($rutaArchivoAbsoluta);

            if (mb_strlen(preg_replace('/\s+/', '', $textoNativo)) >= self::MIN_CARACTERES_TEXTO_NATIVO) {
                return $this->analizarTexto($textoNativo) + ['metodo' => 'pdf_texto'];
            }

            $paginas = $this->rasterizarPdf($rutaArchivoAbsoluta);

            if ($paginas === []) {
                return $this->analizarTexto('') + [
                    'metodo' => 'pdf_escaneado_ocr',
                    'error' => 'No se pudo convertir el PDF a imagen. Revisa que mutool esté instalado y configurado (MUTOOL_PATH).',
                ];
            }

            return $this->leerImagenes($paginas) + ['metodo' => 'pdf_escaneado_ocr'];
        } finally {
            foreach ($this->temporales as $temporal) {
                @unlink($temporal);
            }
            $this->temporales = [];
        }
    }

    /**
     * Analiza un texto ya reconocido (sin volver a correr el OCR). Es público
     * para poder probar la extracción con textos de ejemplo.
     *
     * @return array{texto: string, curp: ?string, curp_verificada: bool, nombre_completo: ?string, numero_documento: ?string, fecha_nacimiento: ?string, entidad_nacimiento: ?string, tipo_documento: ?string, puntaje_tipo: float}
     */
    public function analizarTexto(string $texto): array
    {
        return $this->combinar([$texto]);
    }

    // ------------------------------------------------------------------
    // Lectura de imágenes
    // ------------------------------------------------------------------

    /**
     * @param  string[]  $rutas  una imagen por página
     */
    private function leerImagenes(array $rutas): array
    {
        $paginas = array_map(fn (string $ruta) => ['gris' => $this->prepararImagen($ruta)], $rutas);
        $variantes = [];
        $resultado = $this->combinar([]);

        foreach (self::PASADAS as $pasada) {
            $textos = [];

            foreach ($paginas as &$pagina) {
                if ($pagina['gris'] === null) {
                    continue;
                }

                $textos[] = match ($pasada['imagen']) {
                    'bandas' => $this->leerPorBandas($pagina['gris'], $pasada['psm']),
                    'normalizada' => $this->ejecutarTesseract($pagina['normalizada'] ??= $this->normalizarFondo($pagina['gris']), $pasada['psm']),
                    default => $this->ejecutarTesseract($pagina['gris'], $pasada['psm']),
                };
            }
            unset($pagina);

            $texto = trim(implode("\n\n", $textos));

            if ($texto !== '') {
                $variantes[] = $texto;
                $resultado = $this->combinar($variantes);
            }

            // Solo se deja de leer cuando el nombre ya cuadra con la CURP: un
            // nombre "por etiqueta" puede ser basura (p. ej. el domicilio).
            if ($resultado['curp_verificada'] && $resultado['tipo_documento'] && $resultado['nombre_verificado']) {
                break;
            }
        }

        return $resultado;
    }

    private function ejecutarTesseract(string $ruta, int $psm): string
    {
        try {
            return (new TesseractOCR($ruta))
                ->executable(config('services.tesseract.executable'))
                ->tessdataDir(config('services.tesseract.tessdata_dir'))
                ->lang('spa', 'eng')
                ->psm($psm)
                ->run();
        } catch (\Throwable $e) {
            // Tesseract falla con imágenes en las que no encuentra ningún
            // texto; para nosotros eso es simplemente "no leyó nada".
            return '';
        }
    }

    /**
     * Carga la imagen, corrige orientación (EXIF y giros de 90/180/270
     * grados), la escala y la pasa a gris con más contraste. Devuelve la ruta
     * de un PNG temporal o null si GD no puede abrirla.
     */
    private function prepararImagen(string $ruta): ?string
    {
        $datos = @file_get_contents($ruta);
        $imagen = $datos !== false ? @imagecreatefromstring($datos) : false;

        if ($imagen === false) {
            return null;
        }

        $imagen = $this->corregirOrientacionExif($imagen, $ruta);

        // Las credenciales traen texto pequeño: se amplía la imagen (sin
        // pasarse, porque Tesseract se vuelve muy lento con imágenes enormes).
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $anchoObjetivo = min(max($ancho * 2, 1600), 3200);
        $factor = $anchoObjetivo / $ancho;

        $escalada = imagecreatetruecolor((int) round($ancho * $factor), (int) round($alto * $factor));
        imagecopyresampled($escalada, $imagen, 0, 0, 0, 0, imagesx($escalada), imagesy($escalada), $ancho, $alto);
        imagedestroy($imagen);

        imagefilter($escalada, IMG_FILTER_GRAYSCALE);
        imagefilter($escalada, IMG_FILTER_CONTRAST, -40);

        return $this->corregirGiro($escalada);
    }

    /**
     * Si la imagen está de lado o de cabeza, Tesseract devuelve basura.
     * Primero se pregunta al detector de orientación de Tesseract (OSD); si
     * no está seguro, se lee la imagen tal cual y, si casi no trae palabras
     * reconocibles, se prueban los otros tres giros y gana el que lee más.
     */
    private function corregirGiro(\GdImage $imagen): string
    {
        $ruta = $this->guardarTemporal($imagen, false);
        $giro = $this->detectarGiro($ruta);

        if ($giro !== 0) {
            // OSD dice cuántos grados girar en sentido horario;
            // imagerotate gira en sentido antihorario.
            $girada = imagerotate($imagen, 360 - $giro, imagecolorallocate($imagen, 255, 255, 255));

            if ($girada !== false) {
                imagedestroy($imagen);

                return $this->guardarTemporal($girada);
            }
        }

        $mejorRuta = $ruta;
        $mejorPuntaje = $this->puntajeLectura($this->ejecutarTesseract($mejorRuta, 6));

        foreach ([90, 270, 180] as $grados) {
            if ($mejorPuntaje >= 8) {
                break;
            }

            $girada = imagerotate($imagen, $grados, imagecolorallocate($imagen, 255, 255, 255));

            if ($girada === false) {
                continue;
            }

            $rutaGirada = $this->guardarTemporal($girada);
            $puntaje = $this->puntajeLectura($this->ejecutarTesseract($rutaGirada, 6));

            if ($puntaje > $mejorPuntaje) {
                [$mejorRuta, $mejorPuntaje] = [$rutaGirada, $puntaje];
            }
        }

        imagedestroy($imagen);

        return $mejorRuta;
    }

    /**
     * Grados (0, 90, 180 o 270, sentido horario) que hay que girar la imagen
     * según el detector de orientación de Tesseract (--psm 0). Devuelve 0 si
     * no hay suficiente confianza o si OSD no está disponible.
     */
    private function detectarGiro(string $ruta): int
    {
        try {
            $resultado = Process::timeout(60)->run([
                config('services.tesseract.executable'), $ruta, 'stdout',
                '--psm', '0', '--tessdata-dir', config('services.tesseract.tessdata_dir'),
            ]);
        } catch (\Throwable $e) {
            return 0;
        }

        $salida = $resultado->output().$resultado->errorOutput();

        if (! preg_match('/Rotate:\s*(\d+)/', $salida, $giro) || ! preg_match('/Orientation confidence:\s*([\d.]+)/', $salida, $confianza)) {
            return 0;
        }

        return (float) $confianza[1] >= 2.0 && in_array((int) $giro[1], [90, 180, 270], true) ? (int) $giro[1] : 0;
    }

    /**
     * Cuenta palabras "reales" (3+ letras seguidas): un texto de lado se
     * reconoce como símbolos sueltos y casi no tiene ninguna.
     */
    private function puntajeLectura(string $texto): int
    {
        return (int) preg_match_all('/\b[A-ZÁÉÍÓÚÑa-záéíóúñ]{3,}\b/u', $texto);
    }

    /**
     * Las fotos tomadas con celular casi siempre guardan la rotación como
     * metadato EXIF en vez de rotar los píxeles de verdad; si no se corrige,
     * Tesseract recibe el texto de lado y no reconoce prácticamente nada.
     */
    private function corregirOrientacionExif(\GdImage $imagen, string $ruta): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($ruta) : false;

        if ($exif === false || empty($exif['Orientation'])) {
            return $imagen;
        }

        $rotada = match ($exif['Orientation']) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        if ($rotada === false) {
            return $imagen;
        }

        imagedestroy($imagen);

        return $rotada;
    }

    /**
     * Prepara fotos con fondo de colores, hologramas y sombras:
     * 1) toma el canal más oscuro de cada píxel (el texto es oscuro en todos
     *    los canales; los fondos de color no),
     * 2) estima el fondo (imagen muy reducida y vuelta a ampliar) y divide
     *    entre él, lo que elimina degradados y sombras.
     * Se trabaja a un máximo de 2000 px porque el recorrido píxel a píxel en
     * PHP es lento.
     */
    private function normalizarFondo(string $rutaGris): string
    {
        $origen = @imagecreatefrompng($rutaGris);

        if ($origen === false) {
            return $rutaGris;
        }

        $factor = min(1, 2000 / max(imagesx($origen), imagesy($origen)));
        $w = (int) round(imagesx($origen) * $factor);
        $h = (int) round(imagesy($origen) * $factor);
        $imagen = imagescale($origen, $w, $h, IMG_BICUBIC) ?: $origen;

        if ($imagen !== $origen) {
            imagedestroy($origen);
        }

        $lado = max(16, (int) round(max($w, $h) / 40));
        $pequena = imagescale($imagen, max(8, intdiv($w, $lado)), max(8, intdiv($h, $lado)), IMG_BILINEAR_FIXED);
        $fondo = imagescale($pequena, $w, $h, IMG_BICUBIC);
        imagedestroy($pequena);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $pixel = imagecolorat($imagen, $x, $y);
                $minimo = min(($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255);
                $claridadFondo = max(1, imagecolorat($fondo, $x, $y) & 255);
                $valor = min(255, (int) ($minimo / $claridadFondo * 235));
                imagesetpixel($imagen, $x, $y, ($valor << 16) | ($valor << 8) | $valor);
            }
        }

        imagedestroy($fondo);

        return $this->guardarTemporal($imagen);
    }

    /**
     * El análisis de página completa falla con fondos recargados, pero lee
     * bien franjas pequeñas. Las franjas se solapan para no partir una
     * línea de texto a la mitad.
     */
    private function leerPorBandas(string $rutaGris, int $psm): string
    {
        $imagen = @imagecreatefrompng($rutaGris);

        if ($imagen === false) {
            return '';
        }

        $w = imagesx($imagen);
        $h = imagesy($imagen);
        $textos = [];

        foreach ([[0.0, 0.45], [0.3, 0.75], [0.55, 1.0]] as [$desde, $hasta]) {
            $y = (int) round($h * $desde);
            $franja = imagecrop($imagen, ['x' => 0, 'y' => $y, 'width' => $w, 'height' => (int) round($h * $hasta) - $y]);

            if ($franja === false) {
                continue;
            }

            $textos[] = $this->ejecutarTesseract($this->guardarTemporal($franja), $psm);
        }

        imagedestroy($imagen);

        return implode("\n", $textos);
    }

    private function guardarTemporal(\GdImage $imagen, bool $destruir = true): string
    {
        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_'.Str::random(12).'.png';
        imagepng($imagen, $ruta);
        $this->temporales[] = $ruta;

        if ($destruir) {
            imagedestroy($imagen);
        }

        return $ruta;
    }

    // ------------------------------------------------------------------
    // PDF (mutool / MuPDF)
    // ------------------------------------------------------------------

    private function esPdf(string $ruta): bool
    {
        $cabecera = @file_get_contents($ruta, false, null, 0, 5);

        return $cabecera !== false && str_starts_with($cabecera, '%PDF-');
    }

    private function mutool(): ?string
    {
        $mutool = config('services.mutool.executable');

        return $mutool && file_exists($mutool) ? $mutool : null;
    }

    private function textoNativoPdf(string $rutaPdf): string
    {
        if (! $mutool = $this->mutool()) {
            return '';
        }

        $resultado = Process::timeout(60)->run([$mutool, 'draw', '-F', 'txt', '-o', '-', $rutaPdf, '1-'.self::MAX_PAGINAS_PDF]);

        return $resultado->successful() ? $resultado->output() : '';
    }

    /**
     * Convierte las primeras páginas del PDF a PNG a 300 DPI para que el
     * texto quede legible para Tesseract.
     *
     * @return string[]
     */
    private function rasterizarPdf(string $rutaPdf): array
    {
        if (! $mutool = $this->mutool()) {
            return [];
        }

        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf_'.Str::random(12);
        $resultado = Process::timeout(120)->run([$mutool, 'draw', '-o', $base.'_%d.png', '-r', '300', $rutaPdf, '1-'.self::MAX_PAGINAS_PDF]);

        $paginas = glob($base.'_*.png') ?: [];
        natsort($paginas);
        array_push($this->temporales, ...$paginas);

        return $resultado->successful() ? array_values($paginas) : [];
    }

    // ------------------------------------------------------------------
    // Análisis del texto reconocido
    // ------------------------------------------------------------------

    /**
     * Junta el resultado de varias lecturas del mismo documento: cada
     * lectura puede haber captado partes distintas, así que por cada dato se
     * elige el mejor candidato entre todas.
     *
     * @param  string[]  $variantes
     */
    private function combinar(array $variantes): array
    {
        $curp = $this->elegirCurp($variantes);
        $clasificacion = $this->clasificador->clasificar(implode("\n", $variantes));
        $tipo = $clasificacion['tipo'];

        $nombre = null;
        foreach ($variantes as $texto) {
            $candidato = $this->extraerNombre($texto, $curp['curp']);

            if ($candidato !== null && ($nombre === null || $candidato['prioridad'] > $nombre['prioridad'])) {
                $nombre = $candidato;
            }
        }

        $numero = null;
        foreach ($variantes as $texto) {
            $candidato = $this->extraerNumeroDocumento($texto, $curp['curp'], $tipo);

            if ($candidato !== null && ($numero === null || ($candidato['especifico'] && ! $numero['especifico']))) {
                $numero = $candidato;
            }
        }

        // Con una CURP verificada, la fecha que trae es más confiable que la
        // que se lea de la etiqueta (que el OCR deforma con facilidad).
        $fecha = $curp['verificada'] ? Curp::fechaNacimiento($curp['curp']) : null;
        foreach ($variantes as $texto) {
            $fecha ??= $this->extraerFechaPorEtiqueta($texto);
        }
        if ($fecha === null && $curp['curp'] !== null) {
            $fecha = Curp::fechaNacimiento($curp['curp']);
        }

        $textoPrincipal = collect($variantes)->sortByDesc(fn (string $t) => mb_strlen($t))->first() ?? '';

        return [
            'texto' => $textoPrincipal,
            // Todas las lecturas juntas: si la CURP no se leyó, aquí se busca
            // el nombre de la persona (cada pasada capta partes distintas).
            'texto_completo' => implode("\n\n", $variantes),
            'curp' => $curp['curp'],
            'curp_verificada' => $curp['verificada'],
            'nombre_completo' => $nombre['valor'] ?? null,
            // true si el nombre cuadra letra por letra con la CURP leída.
            'nombre_verificado' => ($nombre['prioridad'] ?? 0) >= 3,
            'numero_documento' => $numero['valor'] ?? null,
            'fecha_nacimiento' => $fecha,
            'entidad_nacimiento' => $curp['curp'] !== null ? Curp::entidad($curp['curp']) : null,
            'tipo_documento' => $tipo,
            'puntaje_tipo' => $clasificacion['puntaje'],
        ];
    }

    /**
     * Junta los candidatos a CURP de todas las lecturas. Gana la que pasa el
     * dígito verificador; entre iguales, la que más lecturas coinciden.
     *
     * @param  string[]  $variantes
     * @return array{curp: ?string, verificada: bool}
     */
    private function elegirCurp(array $variantes): array
    {
        $votos = [];

        foreach ($variantes as $texto) {
            foreach ($this->candidatosCurp($texto) as $candidato) {
                $corregida = Curp::corregir($candidato);

                if ($corregida['curp'] === null) {
                    continue;
                }

                $clave = $corregida['curp'];
                $votos[$clave] ??= ['verificada' => $corregida['verificada'], 'votos' => 0];
                $votos[$clave]['votos']++;
            }
        }

        if ($votos === []) {
            return ['curp' => null, 'verificada' => false];
        }

        uasort($votos, fn ($a, $b) => [$b['verificada'], $b['votos']] <=> [$a['verificada'], $a['votos']]);
        $curp = array_key_first($votos);

        return ['curp' => $curp, 'verificada' => $votos[$curp]['verificada']];
    }

    /**
     * Tokens de 18 caracteres con la FORMA general de una CURP (4 letras,
     * 6 "dígitos", sexo, 5 letras, diferenciador, verificador), permitiendo
     * letras y dígitos confundidos: Curp::corregir decide si es válida.
     * Se buscan en cada línea sin espacios (el OCR a veces parte la CURP) y,
     * con prioridad, cerca de la etiqueta "CURP".
     *
     * @return string[]
     */
    private function candidatosCurp(string $texto): array
    {
        $forma = '/[A-Z0-9]{4}[0-9OQDILZSGB]{6}[HMX][A-Z0-9]{5}[A-Z0-9][0-9OQDILZSGB]/';
        $lineas = preg_split('/\r\n|\r|\n/', strtoupper($texto));
        $cercaDeEtiqueta = [];
        $resto = [];

        foreach ($lineas as $i => $linea) {
            $limpia = preg_replace('/[^A-Z0-9]/', '', $linea);

            if (! preg_match_all($forma, $limpia, $matches)) {
                continue;
            }

            $cerca = false;
            for ($j = max(0, $i - 3); $j <= $i; $j++) {
                $cerca = $cerca || str_contains($lineas[$j], 'CURP');
            }

            if ($cerca) {
                array_push($cercaDeEtiqueta, ...$matches[0]);
            } else {
                array_push($resto, ...$matches[0]);
            }
        }

        return [...$cercaDeEtiqueta, ...$resto];
    }

    /**
     * @return array{valor: string, prioridad: int}|null
     */
    private function extraerNombre(string $texto, ?string $curp): ?array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);

        // Lo más confiable: el nombre que cuadra letra por letra con la CURP,
        // esté donde esté en el documento (sin depender de etiquetas, que en
        // algunos PDF salen desordenadas o pertenecen a otra persona).
        if ($curp !== null && ($porCurp = NombreEnCurp::buscar($lineas, $curp)) !== null) {
            return ['valor' => $porCurp, 'prioridad' => 4];
        }

        $porEtiqueta = $this->extraerNombrePorEtiqueta($lineas);

        if ($porEtiqueta !== null) {
            $ordenado = $this->ordenarNombreConCurp($porEtiqueta, $curp);

            return ['valor' => $ordenado['valor'], 'prioridad' => $ordenado['verificado'] ? 3 : 2];
        }

        $heuristico = $this->extraerNombrePorHeuristica($lineas);

        return $heuristico !== null ? ['valor' => $heuristico, 'prioridad' => 1] : null;
    }

    /**
     * @param  string[]  $lineas
     */
    private function extraerNombrePorEtiqueta(array $lineas): ?string
    {
        foreach ($lineas as $i => $linea) {
            if (! preg_match('/NOMBRE/i', $linea) || preg_match('/PADRE|MADRE/i', $linea)) {
                continue;
            }

            $partes = [];

            // En el acta y la constancia de CURP el valor puede venir en la
            // misma línea que la etiqueta ("Nombre(s): JUAN ...").
            $resto = preg_replace('/.*?NOMBRE\(?S?\)?\s*:?/iu', '', $linea, 1);
            $enLinea = $this->palabrasDeNombre($resto);
            if ($enLinea !== '') {
                $partes[] = $enLinea;
            }

            // En la INE el nombre viene repartido en varias líneas
            // (apellido paterno, materno y nombre(s)) tras la etiqueta
            // "NOMBRE", así que se concatenan hasta topar con la
            // siguiente etiqueta del documento.
            $lineasRevisadas = 0;
            // Cuenta solo líneas con contenido: el OCR a veces mete
            // líneas vacías de relleno entre cada dato (columnas
            // vecinas), y si contaran contra el límite se agotaba antes
            // de llegar al nombre de pila.
            for ($j = $i + 1; $j < count($lineas) && $lineasRevisadas < 4; $j++) {
                $candidata = trim($lineas[$j]);

                if ($candidata === '') {
                    continue;
                }

                // Renglón que solo trae etiquetas ("Primer apellido
                // Segundo apellido"): se salta sin contarlo.
                if (preg_match('/APELLIDO|NOMBRE/i', $candidata) && $this->palabrasDeNombre($candidata) === '') {
                    continue;
                }

                $lineasRevisadas++;

                if (preg_match(self::FIN_BLOQUE_NOMBRE, $candidata)) {
                    break;
                }

                // Una línea de domicilio/otros campos casi siempre trae
                // números; una línea de nombre real, no. Se usa como
                // corte de bloque en vez de exigir que TODA la línea sea
                // mayúsculas, porque el OCR suele pegar basura suelta (un
                // símbolo o letra de una columna vecina) al inicio o
                // final de la línea sin afectar el nombre en sí.
                if (preg_match('/\d/', $candidata)) {
                    break;
                }

                $palabras = $this->palabrasDeNombre($candidata);
                if ($palabras !== '') {
                    $partes[] = $palabras;
                }
            }

            if ($partes !== []) {
                return implode(' ', $partes);
            }
        }

        return null;
    }

    /**
     * Cada "palabra" debe EMPEZAR con 2+ mayúsculas (así se descarta
     * basura suelta en minúsculas de una columna vecina), pero se le
     * permite arrastrar minúsculas pegadas al final: el OCR a veces junta
     * dos nombres sin espacio y solo le baja el caso a la segunda mitad
     * (p. ej. "CARLOSALExis"). Las etiquetas conocidas se descartan.
     */
    private function palabrasDeNombre(string $texto): string
    {
        if (! preg_match_all('/[A-ZÁÉÍÓÚÑ]{2,}[a-záéíóúñ]*/u', $texto, $coincidencias)) {
            return '';
        }

        $palabras = array_filter(
            array_map('mb_strtoupper', $coincidencias[0]),
            fn (string $palabra) => ! in_array($palabra, self::RUIDO_NOMBRE, true)
        );

        return implode(' ', $palabras);
    }

    /**
     * La INE imprime "APELLIDOS NOMBRE(S)" y el acta/constancia suelen
     * imprimir "NOMBRE(S) APELLIDOS". Para guardar siempre en el mismo
     * orden (apellidos primero, como la INE) se prueba cuál de los dos
     * acomodos coincide con las iniciales de la CURP.
     *
     * @return array{valor: string, verificado: bool}
     */
    private function ordenarNombreConCurp(string $nombre, ?string $curp): array
    {
        $palabras = preg_split('/\s+/', trim($nombre));

        if ($curp === null || count($palabras) < 3) {
            return ['valor' => $nombre, 'verificado' => false];
        }

        [$paterno, $materno] = $palabras;
        if (Curp::coincideConNombre($curp, $paterno, $materno, implode(' ', array_slice($palabras, 2)))) {
            return ['valor' => $nombre, 'verificado' => true];
        }

        $paterno = $palabras[count($palabras) - 2];
        $materno = $palabras[count($palabras) - 1];
        $nombres = implode(' ', array_slice($palabras, 0, -2));
        if (Curp::coincideConNombre($curp, $paterno, $materno, $nombres)) {
            return ['valor' => "{$paterno} {$materno} {$nombres}", 'verificado' => true];
        }

        return ['valor' => $nombre, 'verificado' => false];
    }

    /**
     * Respaldo para cuando la etiqueta "NOMBRE" queda ilegible en el OCR
     * (tapada por hologramas, la marca de agua "MUESTRA", o simplemente mal
     * reconocida). Recorre las líneas antes de "DOMICILIO"/"CURP"/"CLAVE" y
     * junta las palabras en mayúsculas que no sean texto de encabezado ni
     * etiquetas conocidas del documento. Es menos preciso que el método por
     * etiqueta (puede colar algún fragmento de ruido), así que solo se usa
     * cuando ese no encontró nada.
     *
     * @param  string[]  $lineas
     */
    private function extraerNombrePorHeuristica(array $lineas): ?string
    {
        $palabras = [];

        foreach ($lineas as $linea) {
            if (preg_match('/DOMICILIO|ADDRESS|\bCURP\b|CLAVE/i', $linea)) {
                break;
            }

            if (! preg_match_all('/[A-ZÁÉÍÓÚÑ]{3,}/u', $linea, $matches)) {
                continue;
            }

            $tokensLinea = $matches[0];

            // Si la línea trae alguna etiqueta/encabezado reconocible, se
            // descarta completa: normalmente es ruido de una columna vecina
            // (p. ej. "SEXO" o "FECHA") que el OCR mezcló con esa línea.
            if (array_intersect($tokensLinea, self::RUIDO_NOMBRE) !== []) {
                continue;
            }

            foreach ($tokensLinea as $palabra) {
                if (mb_strlen($palabra) > 15) {
                    continue;
                }

                $palabras[] = $palabra;

                if (count($palabras) >= 6) {
                    break 2;
                }
            }
        }

        return $palabras !== [] ? implode(' ', $palabras) : null;
    }

    private function extraerFechaPorEtiqueta(string $texto): ?string
    {
        if (! preg_match('/FECHA\s+DE\s+NACIMIENTO[^0-9]{0,20}(\d{1,2})[\/\-. ](\d{1,2})[\/\-. ](\d{2,4})/i', $texto, $match)) {
            return null;
        }

        $dia = (int) $match[1];
        $mes = (int) $match[2];
        $anio = (int) $match[3];

        if (strlen($match[3]) === 2) {
            $anio += $anio + 2000 > (int) date('Y') ? 1900 : 2000;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    /**
     * Primero busca el número con el formato propio de cada documento
     * ("específico"); si no, cae a una heurística genérica.
     *
     * @return array{valor: string, especifico: bool}|null
     */
    private function extraerNumeroDocumento(string $texto, ?string $curp, ?string $tipo): ?array
    {
        $mayusculas = mb_strtoupper($texto);
        $sinEspacios = preg_replace('/[^A-Z0-9\n]/', '', $mayusculas);

        $especifico = match ($tipo) {
            // La constancia de CURP no tiene otro número: su número es la CURP.
            'curp' => $curp,
            // Clave de elector: 6 letras + 8 dígitos + sexo + 3 dígitos.
            'ine' => preg_match('/[A-Z]{6}\d{8}[HMX]\d{3}/', $sinEspacios, $m) ? $m[0] : null,
            'pasaporte' => preg_match('/\b[A-Z]\d{8}\b/', $mayusculas, $m) ? $m[0] : null,
            'cartilla_militar' => preg_match('/MATR[IÍ]CULA\W{0,5}([A-Z]?-?\d{6,9})/u', $mayusculas, $m) ? $m[1] : null,
            'acta_nacimiento' => $this->numeroDeActa($mayusculas),
            default => null,
        };

        if ($especifico !== null) {
            return ['valor' => $especifico, 'especifico' => true];
        }

        // En el acta los números sueltos (libro, foja, oficialía, fechas)
        // confundirían a la heurística genérica.
        if ($tipo === 'acta_nacimiento') {
            return null;
        }

        // Heurística genérica: busca tokens alfanuméricos tipo "clave de
        // elector"/folio (mezclan letras y números), descartando la CURP ya
        // detectada o cualquier otra cosa con forma de CURP.
        if (preg_match_all('/\b[A-Z0-9]{9,18}\b/', $mayusculas, $matches)) {
            foreach ($matches[0] as $candidata) {
                if ($candidata === $curp || Curp::corregir($candidata)['curp'] !== null) {
                    continue;
                }

                if (preg_match('/[A-Z]/', $candidata) && preg_match('/\d/', $candidata)) {
                    return ['valor' => $candidata, 'especifico' => false];
                }
            }
        }

        return null;
    }

    /**
     * En el formato único de acta, "Número de acta" es la última columna
     * del renglón de etiquetas y su valor queda en el renglón de abajo; en
     * formatos viejos va en la misma línea ("ACTA No. 00123").
     */
    private function numeroDeActa(string $texto): ?string
    {
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $texto))));
        $etiqueta = '/(N[UÚ]MERO|N[UÚ]M\.?|NO\.?)\s*(DE\s+)?ACTA|ACTA\s*(N[UÚ]M\.?|NO\.?|N[°º])/u';

        foreach ($lineas as $i => $linea) {
            if (! preg_match($etiqueta, $linea, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $despues = substr($linea, $m[0][1] + strlen($m[0][0]));
            if (preg_match('/^\W{0,5}(\d{1,6})\b/', $despues, $numero)) {
                return $numero[1];
            }

            if (isset($lineas[$i + 1]) && preg_match_all('/\b\d{1,6}\b/', $lineas[$i + 1], $numeros)) {
                return end($numeros[0]);
            }
        }

        return null;
    }
}
