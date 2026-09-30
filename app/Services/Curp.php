<?php

namespace App\Services;

/**
 * Reglas oficiales de la CURP (RENAPO): patrón de 18 caracteres, catálogo de
 * entidades, dígito verificador y corrección de confusiones típicas del OCR.
 * No depende de ninguna API externa: todo es un algoritmo público y fijo.
 */
class Curp
{
    // Claves de entidad federativa (posiciones 12-13). "NE" = nacido en el
    // extranjero. Querétaro es "QT" (no "QO").
    public const ENTIDADES = [
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
        'QT' => 'Querétaro',
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

    // Patrón oficial SIN delimitadores, para poder usarlo tanto anclado
    // (validación) como suelto (búsqueda dentro del texto del OCR).
    public const PATRON = '[A-Z][AEIOUX][A-Z]{2}\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])[HMX](?:AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TL|TS|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d';

    // Confusiones visuales típicas de Tesseract entre letras y dígitos.
    private const A_DIGITO = ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'Z' => '2', 'S' => '5', 'G' => '6', 'B' => '8'];

    private const A_LETRA = ['0' => 'O', '1' => 'I', '2' => 'Z', '5' => 'S', '6' => 'G', '8' => 'B'];

    public static function regexValidacion(): string
    {
        return '/^'.self::PATRON.'$/';
    }

    public static function esValida(?string $curp): bool
    {
        return $curp !== null && (bool) preg_match(self::regexValidacion(), $curp);
    }

    /**
     * Algoritmo oficial del dígito verificador (posición 18): cada uno de
     * los primeros 17 caracteres vale su posición en el diccionario de
     * RENAPO multiplicada por (18 - índice). La Ñ ocupa el lugar del "*".
     */
    public static function digitoVerificadorValido(string $curp): bool
    {
        if (strlen($curp) !== 18 || ! ctype_digit($curp[17])) {
            return false;
        }

        return self::digitoVerificador(substr($curp, 0, 17)) === (int) $curp[17];
    }

    /**
     * Calcula el dígito verificador de los primeros 17 caracteres, o null si
     * alguno no está en el diccionario.
     */
    public static function digitoVerificador(string $primeros17): ?int
    {
        $diccionario = '0123456789ABCDEFGHIJKLMN*OPQRSTUVWXYZ';
        $suma = 0;

        for ($i = 0; $i < 17; $i++) {
            $valor = strpos($diccionario, $primeros17[$i] ?? '?');

            if ($valor === false) {
                return null;
            }

            $suma += $valor * (18 - $i);
        }

        return (10 - $suma % 10) % 10;
    }

    /**
     * Convierte un candidato de 18 caracteres leído por OCR en una CURP
     * válida aprovechando su estructura fija: donde SIEMPRE va dígito se
     * cambia O→0, I→1...; donde SIEMPRE va letra, 0→O, 1→I... Después, si el
     * dígito verificador no cuadra, prueba cambiar UN solo carácter por su
     * confusión típica y acepta únicamente si exactamente una variante cuadra.
     *
     * @return array{curp: ?string, verificada: bool}
     */
    public static function corregir(string $candidato): array
    {
        $candidato = strtoupper($candidato);

        if (strlen($candidato) !== 18) {
            return ['curp' => null, 'verificada' => false];
        }

        $c = str_split($candidato);

        foreach ([4, 5, 6, 7, 8, 9, 17] as $posicion) {
            $c[$posicion] = self::A_DIGITO[$c[$posicion]] ?? $c[$posicion];
        }

        foreach ([0, 1, 2, 3, 10, 11, 12, 13, 14, 15] as $posicion) {
            $c[$posicion] = self::A_LETRA[$c[$posicion]] ?? $c[$posicion];
        }

        $c[16] = self::ajustarDiferenciador($c);
        $curp = implode('', $c);

        if (! self::esValida($curp)) {
            return ['curp' => null, 'verificada' => false];
        }

        if (self::digitoVerificadorValido($curp)) {
            return ['curp' => $curp, 'verificada' => true];
        }

        $corregida = self::corregirConDigitoVerificador($curp);

        return $corregida !== null
            ? ['curp' => $corregida, 'verificada' => true]
            : ['curp' => $curp, 'verificada' => false];
    }

    /**
     * El carácter 17 (diferenciador) es dígito para nacidos antes de 2000 y
     * letra para 2000 en adelante. Justo ahí "0" y "O" dan el MISMO dígito
     * verificador (su diferencia de valor, 25 × 2 = 50, no cambia el
     * módulo 10), así que el verificador no detecta el error: se decide con
     * el año. Si ambos siglos son posibles, se deja como se leyó.
     *
     * @param  string[]  $c
     */
    private static function ajustarDiferenciador(array $c): string
    {
        $anioCorto = $c[4].$c[5];

        if (! ctype_digit($anioCorto)) {
            return $c[16];
        }

        $anioActual = (int) date('Y');
        $posible1900 = $anioActual - (1900 + (int) $anioCorto) <= 115;
        $posible2000 = 2000 + (int) $anioCorto <= $anioActual;

        if ($posible1900 && ! $posible2000) {
            return self::A_DIGITO[$c[16]] ?? $c[16];
        }

        if ($posible2000 && ! $posible1900) {
            return self::A_LETRA[$c[16]] ?? $c[16];
        }

        return $c[16];
    }

    private static function corregirConDigitoVerificador(string $curp): ?string
    {
        $pares = self::A_DIGITO + self::A_LETRA;
        $validas = [];

        for ($i = 0; $i < 17; $i++) {
            if (! isset($pares[$curp[$i]])) {
                continue;
            }

            $variante = substr_replace($curp, $pares[$curp[$i]], $i, 1);

            if (self::esValida($variante) && self::digitoVerificadorValido($variante)) {
                $validas[$variante] = true;
            }
        }

        return count($validas) === 1 ? array_key_first($validas) : null;
    }

    public static function entidad(string $curp): ?string
    {
        return self::ENTIDADES[substr($curp, 11, 2)] ?? null;
    }

    /**
     * Fecha AAAA-MM-DD a partir de las posiciones 5-10 (AAMMDD). El carácter
     * 17 indica el siglo (dígito = antes de 2000, letra = 2000 o después),
     * pero como el OCR confunde "0" y "O", si ese siglo da una fecha futura o
     * una edad absurda se usa el otro.
     */
    public static function fechaNacimiento(string $curp): ?string
    {
        if (! preg_match('/^[A-Z]{4}(\d{2})(\d{2})(\d{2})/', $curp, $m) || strlen($curp) < 17) {
            return null;
        }

        [, $anioCorto, $mes, $dia] = $m;
        $siglos = ctype_digit($curp[16]) ? [1900, 2000] : [2000, 1900];
        $anioActual = (int) date('Y');

        foreach ($siglos as $siglo) {
            $anio = $siglo + (int) $anioCorto;

            if ($anio <= $anioActual && $anioActual - $anio <= 115 && checkdate((int) $mes, (int) $dia, $anio)) {
                $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

                if ($fecha <= date('Y-m-d')) {
                    return $fecha;
                }
            }
        }

        return null;
    }

    /**
     * Las 4 primeras letras de la CURP salen del nombre: inicial del primer
     * apellido, su primera vocal interna, inicial del segundo apellido e
     * inicial del nombre. Se usa para verificar/ordenar el nombre leído.
     */
    public static function coincideConNombre(string $curp, string $paterno, string $materno, string $nombre): bool
    {
        $inicial = fn (string $palabra) => substr(self::sinAcentos($palabra), 0, 1);

        return $inicial($paterno) === $curp[0]
            && ($materno === '' ? $curp[2] === 'X' : $inicial($materno) === $curp[2])
            && $inicial(self::nombreDePila($nombre)) === $curp[3];
    }

    /**
     * Para "JOSÉ", "MARÍA" (y sus abreviaturas) RENAPO usa el segundo nombre
     * cuando hay más de uno.
     */
    private static function nombreDePila(string $nombres): string
    {
        $partes = preg_split('/\s+/', trim($nombres));

        if (count($partes) > 1 && in_array(self::sinAcentos($partes[0]), ['JOSE', 'J', 'J.', 'MARIA', 'MA', 'MA.', 'M', 'M.'], true)) {
            return $partes[1];
        }

        return $partes[0] ?? '';
    }

    public static function sinAcentos(string $texto): string
    {
        return strtr(mb_strtoupper($texto), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'X']);
    }
}
