<?php

namespace App\Services;

/**
 * Encuentra el nombre de una persona dentro de un texto usando su CURP:
 * busca la secuencia de palabras que cuadra con las 7 letras del nombre
 * codificadas en la CURP (ver Curp::puntajeNombre). Sirve para dos cosas:
 *  - leer el nombre correcto aunque el documento traiga otros nombres
 *    (firmas, padres) o las etiquetas salgan desordenadas, y
 *  - identificar de quién es un documento cuya CURP no se pudo leer,
 *    probando con la CURP de cada persona registrada.
 */
class NombreEnCurp
{
    private const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'MC', 'VAN', 'VON'];

    /**
     * Revisa cada renglón y también 2-3 renglones juntos, porque la INE
     * parte el nombre en varios. Devuelve el nombre en orden
     * "PATERNO MATERNO NOMBRE(S)" o null si nada cuadra con la CURP.
     *
     * @param  string[]  $lineas
     */
    public static function buscar(array $lineas, string $curp): ?string
    {
        $lineas = array_values(array_filter(array_map('trim', $lineas), fn (string $l) => $l !== ''));
        $ruido = array_map([Curp::class, 'sinAcentos'], DocumentoOcrService::RUIDO_NOMBRE);
        $mejor = null;

        foreach (array_keys($lineas) as $i) {
            for ($ventana = 1; $ventana <= 3 && $i + $ventana <= count($lineas); $ventana++) {
                $bloque = implode(' ', array_slice($lineas, $i, $ventana));

                foreach (self::segmentos($bloque, $ruido) as $unidades) {
                    $candidato = self::mejorAcomodo($unidades, $curp);

                    if ($candidato !== null && ($mejor === null || [$candidato['puntaje'], $candidato['palabras']] > [$mejor['puntaje'], $mejor['palabras']])) {
                        $mejor = $candidato;
                    }
                }
            }
        }

        return $mejor['valor'] ?? null;
    }

    public static function enTexto(string $texto, string $curp): ?string
    {
        return self::buscar(preg_split('/\r\n|\r|\n/', $texto), $curp);
    }

    /**
     * Parte un texto en tramos de palabras que podrían ser un nombre:
     * palabras en MAYÚSCULAS que no sean etiquetas conocidas. Cortan el
     * tramo los números, las etiquetas ("CURP", "SECRETARIA"...) y las
     * palabras normales en minúsculas ("Primer", "Nombre"). La basura corta
     * que mete el OCR en fotos borrosas (una "c" suelta, guiones, comillas)
     * se ignora SIN cortar, para no partir "MARIO IMANOL c - JIMENEZ BIBIANO".
     * Las partículas ("DE LA", "DEL") se pegan a la palabra que sigue para
     * formar un solo apellido ("DE LA CRUZ").
     *
     * @param  string[]  $ruido
     * @return array<int, string[]>
     */
    private static function segmentos(string $texto, array $ruido): array
    {
        $segmentos = [];
        $actual = [];
        $particulas = '';

        foreach (preg_split('/\s+/u', $texto) as $token) {
            $palabra = trim($token, ".,;:()\"'");
            $esNombre = preg_match('/^[A-ZÁÉÍÓÚÜÑ]+$/u', $palabra) && ! in_array(Curp::sinAcentos($palabra), $ruido, true);
            $letras = preg_match_all('/\p{L}/u', $palabra);
            $esBasuraCorta = ! $esNombre && ! preg_match('/\d/', $palabra) && $letras <= 2;

            if ($esBasuraCorta) {
                continue;
            }

            if (! $esNombre) {
                if (count($actual) >= 2) {
                    $segmentos[] = $actual;
                }
                $actual = [];
                $particulas = '';

                continue;
            }

            if (in_array(Curp::sinAcentos($palabra), self::PARTICULAS, true)) {
                $particulas .= $palabra.' ';

                continue;
            }

            if (mb_strlen($palabra) < 2) {
                continue;
            }

            $actual[] = $particulas.$palabra;
            $particulas = '';
        }

        if (count($actual) >= 2) {
            $segmentos[] = $actual;
        }

        return $segmentos;
    }

    /**
     * Prueba cada sub-secuencia de 2 a 5 palabras en los dos órdenes
     * posibles y se queda con la que más letras comparte con la CURP (y, a
     * igualdad, la más larga: así no se pierde un segundo nombre).
     *
     * @param  string[]  $unidades
     * @return array{valor: string, puntaje: int, palabras: int}|null
     */
    private static function mejorAcomodo(array $unidades, string $curp): ?array
    {
        $mejor = null;
        $total = count($unidades);
        // Sin segundo apellido la CURP lleva "X" en la posición 3.
        $minimo = $curp[2] === 'X' ? 2 : 3;

        for ($inicio = 0; $inicio < $total; $inicio++) {
            for ($largo = $minimo; $largo <= 5 && $inicio + $largo <= $total; $largo++) {
                $sub = array_slice($unidades, $inicio, $largo);
                $acomodos = [
                    // Apellidos primero (INE): PATERNO MATERNO NOMBRE(S)
                    [$sub[0], $sub[1], implode(' ', array_slice($sub, 2))],
                    // Nombre(s) primero (acta, constancia): NOMBRE(S) PATERNO MATERNO
                    [$sub[$largo - 2], $sub[$largo - 1], implode(' ', array_slice($sub, 0, $largo - 2))],
                ];

                if ($minimo === 2) {
                    // Un solo apellido: PATERNO NOMBRE(S) o NOMBRE(S) PATERNO.
                    $acomodos[] = [$sub[0], '', implode(' ', array_slice($sub, 1))];
                    $acomodos[] = [$sub[$largo - 1], '', implode(' ', array_slice($sub, 0, $largo - 1))];
                }

                foreach ($acomodos as [$paterno, $materno, $nombres]) {
                    if ($nombres === '') {
                        continue;
                    }

                    $puntaje = Curp::puntajeNombre($curp, $paterno, $materno, $nombres);

                    if ($puntaje >= Curp::COINCIDENCIAS_MINIMAS_NOMBRE && ($mejor === null || [$puntaje, $largo] > [$mejor['puntaje'], $mejor['palabras']])) {
                        $mejor = [
                            'valor' => mb_strtoupper(trim(preg_replace('/\s+/', ' ', "{$paterno} {$materno} {$nombres}"))),
                            'puntaje' => $puntaje,
                            'palabras' => $largo,
                        ];
                    }
                }
            }
        }

        return $mejor;
    }
}
