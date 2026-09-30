<?php

namespace App\Services;

/**
 * Decide qué tipo de documento es un texto reconocido por OCR sumando el
 * peso de las "firmas" (frases características) de cada tipo que aparecen
 * en él. Gana el tipo con más puntaje, siempre que supere un mínimo.
 *
 * Se usa puntaje y no un simple "si contiene X" porque los documentos se
 * mencionan entre sí: la constancia de CURP imprime "Acta de nacimiento"
 * como documento probatorio, el acta y la INE imprimen la etiqueta "CURP",
 * etc. Con pesos, la frase propia de cada documento pesa más que la mención.
 */
class ClasificadorDocumentos
{
    public const PUNTAJE_MINIMO = 0.3;

    /**
     * Patrones sobre texto en MAYÚSCULAS y sin acentos (ver normalizar()).
     *
     * @var array<string, array<string, float>>
     */
    private const FIRMAS = [
        'ine' => [
            '/INSTITUTO (NACIONAL|FEDERAL) ELECTORAL/' => 0.5,
            '/CREDENCIAL PARA VOTAR/' => 0.5,
            '/CLAVE DE ELECTOR/' => 0.35,
            // La clave de elector tiene forma fija: 6 letras + 8 dígitos + sexo + 3 dígitos.
            '/[A-Z]{6}\d{8}[HMX]\d{3}/' => 0.35,
            '/\bDOMICILIO\b/' => 0.15,
            '/\bSECCION\b/' => 0.1,
            '/\bVIGENCIA\b/' => 0.1,
            '/ANO DE REGISTRO/' => 0.1,
        ],
        'curp' => [
            '/CONSTANCIA DE LA (CLAVE UNICA|CURP)/' => 0.6,
            '/CLAVE UNICA DE REGISTRO DE POBLACION/' => 0.25,
            '/REGISTRO NACIONAL DE POBLACION|RENAPO/' => 0.25,
            '/DOCUMENTO PROBATORIO|ESTATUS (DE LA )?CURP|CURP CERTIFICADA/' => 0.3,
            '/\bCURP\b/' => 0.05,
        ],
        'acta_nacimiento' => [
            '/ACTA DE NACIMIENTO/' => 0.35,
            '/PERSONA REGISTRADA/' => 0.35,
            '/FILIACION|PROGENITOR|\bPADRE\b|\bMADRE\b/' => 0.25,
            '/IDENTIFICADOR ELECTRONICO/' => 0.25,
            '/OFICIALIA|JUZGADO|OFICIAL DEL REGISTRO/' => 0.2,
            '/ANOTACIONES MARGINALES|EL SUSCRITO|CERTIFICA/' => 0.15,
            '/\bFOJA\b|\bLIBRO\b/' => 0.1,
            '/REGISTRO CIVIL/' => 0.1,
        ],
        'pasaporte' => [
            '/PASAPORTE|PASSPORT/' => 0.6,
            '/P<MEX/' => 0.5,
            '/RELACIONES EXTERIORES/' => 0.3,
        ],
        'licencia_conducir' => [
            '/LICENCIA/' => 0.3,
            '/CONDUCIR|CHOFER|MANEJAR|AUTOMOVILISTA|MOTOCICLISTA/' => 0.35,
            '/MOVILIDAD|TRANSITO|VIALIDAD|TRANSPORTE/' => 0.2,
            '/TIPO DE LICENCIA/' => 0.2,
        ],
        'cartilla_militar' => [
            '/CARTILLA/' => 0.5,
            '/SERVICIO MILITAR/' => 0.5,
            '/DEFENSA NACIONAL|SEDENA|MATRICULA/' => 0.2,
        ],
    ];

    /**
     * @return array{tipo: ?string, puntaje: float, puntajes: array<string, float>}
     */
    public function clasificar(string $texto): array
    {
        $normalizado = self::normalizar($texto);
        $puntajes = [];

        foreach (self::FIRMAS as $tipo => $firmas) {
            $puntajes[$tipo] = 0.0;

            foreach ($firmas as $patron => $peso) {
                if (preg_match($patron, $normalizado)) {
                    $puntajes[$tipo] += $peso;
                }
            }

            $puntajes[$tipo] = round($puntajes[$tipo], 2);
        }

        arsort($puntajes);
        $tipo = array_key_first($puntajes);
        $puntaje = $puntajes[$tipo];

        return [
            'tipo' => $puntaje >= self::PUNTAJE_MINIMO ? $tipo : null,
            'puntaje' => $puntaje,
            'puntajes' => $puntajes,
        ];
    }

    /**
     * Mayúsculas, sin acentos y con espacios simples: el OCR pierde tildes
     * seguido y a veces mete dobles espacios o saltos a mitad de frase.
     */
    public static function normalizar(string $texto): string
    {
        $texto = strtr(mb_strtoupper($texto), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);

        return preg_replace('/[ \t]+/', ' ', preg_replace('/\s*\n\s*/', ' ', $texto));
    }
}
