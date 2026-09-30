<?php

namespace App\Services;

use App\Http\Requests\StoreDocumentoRequest;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentoOcrService
{
    // Tipos de archivo desde los que se puede extraer texto: imágenes
    // directamente, y PDF convirtiendo su primera página a imagen con
    // mutool (MuPDF) antes de pasarla a Tesseract.
    public const EXTENSIONES_SOPORTADAS = ['jpg', 'jpeg', 'png', 'pdf'];

    // Palabras de encabezado/etiquetas que NO son parte del nombre de la
    // persona; se usan para filtrar el respaldo heurístico de extraerNombre
    // cuando la etiqueta "NOMBRE" no se pudo leer.
    private const RUIDO_NOMBRE = [
        'INSTITUTO', 'NACIONAL', 'ELECTORAL', 'MEXICO', 'CREDENCIAL', 'VOTAR', 'VOTER', 'PARA',
        'DOMICILIO', 'ADDRESS', 'CURP', 'CLAVE', 'ELECTOR', 'SEXO', 'SEX', 'FECHA', 'DESDE',
        'NACIMIENTO', 'BIRTH', 'DATE', 'PLACE', 'VIGENCIA', 'EXPIRY', 'EMISION', 'EMISIÓN', 'ISSUE',
        'ESTADO', 'STATE', 'REGISTRO', 'MUNICIPIO', 'LOCALIDAD', 'SECCION', 'SECCIÓN', 'FIRMA',
        'MUESTRA', 'SAMPLE', 'EXTRANJERO', 'ABROAD', 'UNITED', 'STATES', 'NOMBRE', 'NAME', 'EDAD',
    ];

    // Catálogo oficial de claves de entidad federativa que usa RENAPO en
    // las posiciones 12-13 de la CURP (mismo listado que valida
    // StoreDocumentoRequest::CURP_REGEX). No depende de ninguna API externa:
    // es un catálogo fijo y público.
    public const ENTIDADES_CURP = [
        'AS' => 'Aguascalientes',
        'BC' => 'Baja California',
        'BS' => 'Baja California Sur',
        'CC' => 'Campeche',
        'CL' => 'Coahuila',
        'CM' => 'Colima',
        'CS' => 'Chiapas',
        'CH' => 'Chihuahua',
        'DF' => 'Ciudad de México',
        'DG' => 'Durango',
        'GT' => 'Guanajuato',
        'GR' => 'Guerrero',
        'HG' => 'Hidalgo',
        'JC' => 'Jalisco',
        'MC' => 'Estado de México',
        'MN' => 'Michoacán',
        'MS' => 'Morelos',
        'NT' => 'Nayarit',
        'NL' => 'Nuevo León',
        'OC' => 'Oaxaca',
        'PL' => 'Puebla',
        'QO' => 'Querétaro',
        'QR' => 'Quintana Roo',
        'SP' => 'San Luis Potosí',
        'SL' => 'Sinaloa',
        'SR' => 'Sonora',
        'TC' => 'Tabasco',
        'TL' => 'Tlaxcala',
        'TS' => 'Tamaulipas',
        'VZ' => 'Veracruz',
        'YN' => 'Yucatán',
        'ZS' => 'Zacatecas',
        'NE' => 'Nacido en el extranjero',
    ];

    public function soportaOcr(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONES_SOPORTADAS, true);
    }

    public function extraer(string $rutaArchivoAbsoluta): array
    {
        $rutaPdfConvertida = null;
        $rutaImagen = $rutaArchivoAbsoluta;

        if ($this->esPdf($rutaArchivoAbsoluta)) {
            $rutaPdfConvertida = $this->convertirPdfAImagen($rutaArchivoAbsoluta);

            if ($rutaPdfConvertida === null) {
                return [
                    'texto' => '',
                    'curp' => null,
                    'nombre_completo' => null,
                    'numero_documento' => null,
                    'error' => 'No se pudo convertir el PDF a imagen. Revisa que mutool esté instalado y configurado (MUTOOL_PATH).',
                ];
            }

            $rutaImagen = $rutaPdfConvertida;
        }

        $rutaProcesada = $this->preprocesar($rutaImagen);

        try {
            // psm 6 (bloque uniforme) es el más preciso cuando funciona,
            // pero en una credencial con foto + columnas de texto a veces
            // no segmenta bien y devuelve muy poco o nada. Si pasa eso, se
            // reintenta con psm 11 (texto disperso, sin asumir un bloque
            // único) y se usa el resultado más largo de los dos.
            $texto = $this->ejecutarTesseract($rutaProcesada, 6);

            if ($this->calidadInsuficiente($texto)) {
                $textoAlterno = $this->ejecutarTesseract($rutaProcesada, 11);

                if (strlen(trim($textoAlterno)) > strlen(trim($texto))) {
                    $texto = $textoAlterno;
                }
            }
        } finally {
            if ($rutaProcesada !== $rutaImagen) {
                @unlink($rutaProcesada);
            }
            if ($rutaPdfConvertida !== null) {
                @unlink($rutaPdfConvertida);
            }
        }

        $curp = $this->extraerCurp($texto);

        return [
            'texto' => $texto,
            'curp' => $curp,
            'nombre_completo' => $this->extraerNombre($texto),
            'numero_documento' => $this->extraerNumeroDocumento($texto, $curp),
            'fecha_nacimiento' => $this->extraerFechaNacimiento($texto, $curp),
            'entidad_nacimiento' => $this->extraerEntidadNacimiento($curp),
            'tipo_documento' => $this->detectarTipoDocumento($texto, $curp),
        ];
    }

    /**
     * Traduce las posiciones 12-13 de la CURP (clave de entidad federativa
     * de RENAPO) al nombre completo del estado. Es un catálogo fijo de 32
     * claves oficiales, así que no requiere ninguna API externa.
     */
    private function extraerEntidadNacimiento(?string $curp): ?string
    {
        if ($curp === null || mb_strlen($curp) < 13) {
            return null;
        }

        $clave = mb_substr($curp, 11, 2);

        return self::ENTIDADES_CURP[$clave] ?? null;
    }

    /**
     * Adivina el tipo de documento a partir de palabras clave típicas de
     * cada credencial. Es una heurística: para carga masiva se usa como
     * primer filtro, pero siempre queda a la vista para corregirse a mano.
     */
    public function detectarTipoDocumento(string $texto, ?string $curp): ?string
    {
        $limpio = strtoupper($texto);

        if (str_contains($limpio, 'PASAPORTE') || str_contains($limpio, 'PASSPORT')) {
            return 'pasaporte';
        }

        if (str_contains($limpio, 'CARTILLA') || str_contains($limpio, 'SERVICIO MILITAR NACIONAL')) {
            return 'cartilla_militar';
        }

        if (str_contains($limpio, 'LICENCIA') && (str_contains($limpio, 'CONDUCIR') || str_contains($limpio, 'CHOFER') || str_contains($limpio, 'MANEJO'))) {
            return 'licencia_conducir';
        }

        if (str_contains($limpio, 'INSTITUTO NACIONAL ELECTORAL') || str_contains($limpio, 'CREDENCIAL PARA VOTAR') || str_contains($limpio, 'CLAVE DE ELECTOR')) {
            return 'ine';
        }

        if (str_contains($limpio, 'REGISTRO NACIONAL DE POBLACION') || str_contains($limpio, 'RENAPO')) {
            return 'curp';
        }

        // Si no hay palabras clave de ningún otro documento pero sí se
        // encontró una CURP válida, lo más probable es que sea la
        // constancia de CURP (que trae poco más que ese código).
        if ($curp !== null) {
            return 'curp';
        }

        return null;
    }

    private function ejecutarTesseract(string $ruta, int $psm): string
    {
        return (new TesseractOCR($ruta))
            ->executable(config('services.tesseract.executable'))
            ->tessdataDir(config('services.tesseract.tessdata_dir'))
            ->lang('spa', 'eng')
            ->psm($psm)
            ->run();
    }

    private function calidadInsuficiente(string $texto): bool
    {
        return strlen(trim($texto)) < 25;
    }

    private function esPdf(string $ruta): bool
    {
        $cabecera = @file_get_contents($ruta, false, null, 0, 5);

        return $cabecera !== false && str_starts_with($cabecera, '%PDF-');
    }

    /**
     * Convierte la primera página del PDF a un PNG usando mutool (MuPDF),
     * a 300 DPI para que el texto quede legible para Tesseract.
     * Devuelve null si mutool no está configurado o falla.
     */
    private function convertirPdfAImagen(string $rutaPdf): ?string
    {
        $mutool = config('services.mutool.executable');

        if (! $mutool || ! file_exists($mutool)) {
            return null;
        }

        $rutaSalida = tempnam(sys_get_temp_dir(), 'pdf_').'.png';

        $comando = sprintf(
            '%s draw -o %s -r 300 %s 1 2>NUL',
            escapeshellarg($mutool),
            escapeshellarg($rutaSalida),
            escapeshellarg($rutaPdf)
        );

        exec($comando, $salida, $codigo);

        if ($codigo !== 0 || ! file_exists($rutaSalida)) {
            @unlink($rutaSalida);

            return null;
        }

        return $rutaSalida;
    }

    /**
     * Escala y aumenta el contraste de la imagen antes de pasarla a
     * Tesseract: las credenciales oficiales (INE, etc.) suelen tener
     * patrones de fondo y texto pequeño que degradan mucho el OCR crudo.
     * Devuelve la ruta original si no se puede procesar con GD.
     */
    private function preprocesar(string $ruta): string
    {
        $datos = @file_get_contents($ruta);
        $origen = $datos !== false ? @imagecreatefromstring($datos) : false;

        if ($origen === false) {
            return $ruta;
        }

        $origen = $this->corregirOrientacion($origen, $ruta);

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $anchoObjetivo = min(max($ancho * 2, 1600), 3200);
        $factor = $anchoObjetivo / $ancho;
        $nuevoAncho = (int) round($ancho * $factor);
        $nuevoAlto = (int) round($alto * $factor);

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($origen);

        imagefilter($destino, IMG_FILTER_GRAYSCALE);
        imagefilter($destino, IMG_FILTER_CONTRAST, -40);

        $rutaTemp = tempnam(sys_get_temp_dir(), 'ocr_').'.png';
        imagepng($destino, $rutaTemp);
        imagedestroy($destino);

        return $rutaTemp;
    }

    /**
     * Las fotos tomadas con celular casi siempre guardan la rotación como
     * metadato EXIF en vez de rotar los píxeles de verdad; si no se corrige,
     * Tesseract recibe el texto de lado y no reconoce prácticamente nada.
     */
    private function corregirOrientacion(\GdImage $imagen, string $ruta): \GdImage
    {
        $exif = @exif_read_data($ruta);

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

    private function extraerCurp(string $texto): ?string
    {
        return $this->extraerCurpPorEtiqueta($texto) ?? $this->extraerCurpEstricta($texto);
    }

    /**
     * Búsqueda estricta contra el patrón oficial de 18 caracteres, en todo
     * el texto reconocido (sin usar la etiqueta "CURP" como referencia). Es
     * exacta pero frágil: basta con que el OCR confunda UNA sola letra (p.
     * ej. una "D" leída como "O") en cualquiera de los 18 caracteres para
     * que no encuentre nada, así que solo se usa como último recurso.
     */
    private function extraerCurpEstricta(string $texto): ?string
    {
        $limpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $texto));

        // Quita el "/" final del regex de validación para poder buscarlo
        // como substring dentro de todo el texto reconocido.
        $patron = str_replace(['/', '^', '$'], '', StoreDocumentoRequest::CURP_REGEX);

        if (preg_match('/'.$patron.'/', $limpio, $match)) {
            return $match[0];
        }

        return null;
    }

    /**
     * Busca la etiqueta "CURP" y, cerca de ella, un token de 18 caracteres
     * con la FORMA general de una CURP (4 letras, 6 dígitos de fecha, sexo,
     * 2 letras de entidad, 3 letras, diferenciador, dígito verificador). A
     * diferencia del patrón oficial estricto, no exige que esas 3 letras
     * sean consonantes de una lista específica ni que la entidad esté en el
     * catálogo oficial: el OCR confunde letras parecidas (O/D, O/0, etc.)
     * justo en esa zona, y la posición cerca de la etiqueta ya da suficiente
     * confianza, así que basta con validar que los 6 dígitos formen una
     * fecha real.
     */
    private function extraerCurpPorEtiqueta(string $texto): ?string
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);

        foreach ($lineas as $i => $linea) {
            if (! preg_match('/\bCURP\b/i', $linea)) {
                continue;
            }

            for ($j = $i; $j < count($lineas) && $j <= $i + 3; $j++) {
                $limpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $lineas[$j]));

                if (! preg_match('/[A-Z]{4}(\d{2})(\d{2})(\d{2})[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]\d/', $limpio, $match)) {
                    continue;
                }

                [, $anioCorto, $mes, $dia] = $match;

                if (checkdate((int) $mes, (int) $dia, 2000 + (int) $anioCorto) || checkdate((int) $mes, (int) $dia, 1900 + (int) $anioCorto)) {
                    return $match[0];
                }
            }
        }

        return null;
    }

    private function extraerNombre(string $texto): ?string
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);

        return $this->extraerNombrePorEtiqueta($lineas) ?? $this->extraerNombrePorHeuristica($lineas);
    }

    /**
     * @param  string[]  $lineas
     */
    private function extraerNombrePorEtiqueta(array $lineas): ?string
    {
        foreach ($lineas as $i => $linea) {
            if (preg_match('/NOMBRE/i', $linea)) {
                // En la INE el nombre viene repartido en varias líneas
                // (apellido paterno, materno y nombre(s)) tras la etiqueta
                // "NOMBRE", así que se concatenan hasta topar con la
                // siguiente etiqueta del documento.
                $partes = [];
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

                    $lineasRevisadas++;

                    if (preg_match('/DOMICILIO|CURP|CLAVE|SEXO|FECHA|VIGENCIA|REGISTRO|ESTADO/i', $candidata)) {
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

                    // Cada "palabra" debe EMPEZAR con 2+ mayúsculas (así se
                    // descarta basura suelta en minúsculas de una columna
                    // vecina), pero se le permite arrastrar minúsculas
                    // pegadas al final: el OCR a veces junta dos nombres sin
                    // espacio y solo le baja el caso a la segunda mitad
                    // (p. ej. "CARLOSALExis").
                    if (preg_match_all('/[A-ZÁÉÍÓÚÑ]{2,}[a-záéíóúñ]*(?:\s+[A-ZÁÉÍÓÚÑ]{2,}[a-záéíóúñ]*)*/u', $candidata, $coincidencias) && $coincidencias[0] !== []) {
                        $partes[] = mb_strtoupper(implode(' ', $coincidencias[0]));
                    }
                }

                if ($partes !== []) {
                    return implode(' ', $partes);
                }
            }
        }

        return null;
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

    /**
     * Busca la etiqueta "FECHA DE NACIMIENTO" seguida de una fecha en el
     * texto reconocido. Si no aparece (o el OCR la deformó), la deriva de
     * la CURP: los primeros 6 dígitos son AAMMDD, y el siglo se determina
     * con el dígito diferenciador (posición 17): dígito = nacido antes de
     * 2000, letra = nacido en 2000 o después.
     */
    private function extraerFechaNacimiento(string $texto, ?string $curp): ?string
    {
        if (preg_match('/FECHA\s+DE\s+NACIMIENTO[^0-9]{0,20}(\d{1,2})[\/\-. ](\d{1,2})[\/\-. ](\d{2,4})/i', $texto, $match)) {
            $dia = (int) $match[1];
            $mes = (int) $match[2];
            $anio = strlen($match[3]) === 2 ? $this->expandirAnioDesdeCurp($match[3], '0') : (int) $match[3];

            if (checkdate($mes, $dia, $anio)) {
                return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            }
        }

        if ($curp !== null) {
            return $this->fechaDesdeCurp($curp);
        }

        return null;
    }

    private function fechaDesdeCurp(string $curp): ?string
    {
        // Igual que en extraerCurpPorEtiqueta: no se exige que las 3 letras
        // de las posiciones 14-16 sean consonantes de la lista oficial. Si
        // la CURP vino del método relajado (con una letra rara ahí por un
        // error de OCR), exigirlo aquí otra vez tiraría la fecha a la
        // basura aunque la CURP ya se haya aceptado.
        if (! preg_match('/^[A-Z]{4}(\d{2})(\d{2})(\d{2})[HM][A-Z]{2}[A-Z]{3}([A-Z\d])\d$/', $curp, $match)) {
            return null;
        }

        [, $anioCorto, $mes, $dia, $diferenciador] = $match;
        $anio = $this->expandirAnioDesdeCurp($anioCorto, $diferenciador);
        $mes = (int) $mes;
        $dia = (int) $dia;

        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /**
     * El dígito diferenciador (posición 17 de la CURP) indica el siglo,
     * pero en el OCR es una fuente de errores muy común: "0" y "O" son
     * visualmente idénticos y Tesseract los confunde seguido. Por eso el
     * resultado de ese dígito solo se usa si da un año plausible (no futuro
     * y no una edad absurda); si no, se usa el otro siglo.
     */
    private function expandirAnioDesdeCurp(string $anioCorto, string $diferenciador): int
    {
        $anioCorto = (int) $anioCorto;
        $candidato = (ctype_digit($diferenciador) ? 1900 : 2000) + $anioCorto;

        if ($this->anioEsPlausible($candidato)) {
            return $candidato;
        }

        return ($candidato >= 2000 ? 1900 : 2000) + $anioCorto;
    }

    private function anioEsPlausible(int $anio): bool
    {
        $anioActual = (int) date('Y');

        return $anio <= $anioActual && ($anioActual - $anio) <= 115;
    }

    private function extraerNumeroDocumento(string $texto, ?string $curp): ?string
    {
        // Heurística genérica: busca tokens alfanuméricos tipo "clave de
        // elector"/folio (mezclan letras y números), ignorando palabras del
        // encabezado del documento que no tienen dígitos y descartando la
        // CURP ya detectada (no debe proponerse dos veces).
        if (preg_match_all('/\b[A-Z0-9]{9,18}\b/', strtoupper($texto), $matches)) {
            foreach ($matches[0] as $candidata) {
                if ($candidata === $curp) {
                    continue;
                }

                $tieneLetra = (bool) preg_match('/[A-Z]/', $candidata);
                $tieneDigito = (bool) preg_match('/\d/', $candidata);

                if ($tieneLetra && $tieneDigito) {
                    return $candidata;
                }
            }
        }

        return null;
    }
}
