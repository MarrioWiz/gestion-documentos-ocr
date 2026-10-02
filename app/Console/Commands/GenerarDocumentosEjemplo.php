<?php

namespace App\Console\Commands;

use App\Services\Curp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Genera documentos de identidad FICTICIOS (INE, constancia de CURP y acta
 * de nacimiento) como imágenes y PDF, para probar el OCR y la carga masiva
 * sin usar documentos de personas reales. Cada archivo lleva la leyenda
 * "EJEMPLO FICTICIO".
 */
#[Signature('documentos:generar-ejemplos {--dir= : Carpeta de salida (por defecto tests/Fixtures/documentos)} {--set=basico : "basico" (3 personas de las pruebas) o "extra" (4 personas con expediente completo)}')]
#[Description('Genera documentos de identidad ficticios (INE, CURP, acta, pasaporte, licencia, cartilla) para probar el OCR.')]
class GenerarDocumentosEjemplo extends Command
{
    private const PERSONAS = [
        'carlos' => [
            'paterno' => 'MENDOZA', 'materno' => 'RUIZ', 'nombres' => 'CARLOS DANIEL',
            'curp17' => 'MERC010722HPLNZRA', 'nacimiento' => '22/07/2001', 'sexo' => 'HOMBRE',
            'entidad' => 'PUEBLA', 'municipio' => 'PUEBLA', 'clave_elector' => 'MNRZCR01072221H300',
            'padre' => 'JOSE LUIS MENDOZA PEREZ', 'madre' => 'ROSA MARIA RUIZ LUNA', 'acta' => '01234',
        ],
        'maria' => [
            'paterno' => 'LOPEZ', 'materno' => 'GARCIA', 'nombres' => 'MARIA FERNANDA',
            'curp17' => 'LOGF951103MJCPRR0', 'nacimiento' => '03/11/1995', 'sexo' => 'MUJER',
            'entidad' => 'JALISCO', 'municipio' => 'GUADALAJARA', 'clave_elector' => 'LPGRFR95110314M500',
            'padre' => 'ALBERTO LOPEZ DIAZ', 'madre' => 'SOFIA GARCIA NAVA', 'acta' => '00587',
        ],
        'julian' => [
            'paterno' => 'SANCHEZ', 'materno' => 'VELA', 'nombres' => 'JULIAN',
            'curp17' => 'SAVJ870214HQTNLL0', 'nacimiento' => '14/02/1987', 'sexo' => 'HOMBRE',
            'entidad' => 'QUERETARO', 'municipio' => 'QUERETARO', 'clave_elector' => 'SNVLJL87021422H100',
            'padre' => 'RAUL SANCHEZ ORTIZ', 'madre' => 'ELENA VELA CRUZ', 'acta' => '02210',
        ],

        // Segundo juego (--set=extra): expedientes completos con pasaporte,
        // licencia y cartilla, y casos difíciles (apellido compuesto, Ñ,
        // "JOSÉ" como primer nombre, menor de edad sin INE).
        'ana' => [
            'paterno' => 'GUTIERREZ', 'materno' => 'NAVARRO', 'nombres' => 'ANA SOFIA',
            'curp17' => 'GUNA030418MNLTVNA', 'nacimiento' => '18/04/2003', 'sexo' => 'MUJER',
            'entidad' => 'NUEVO LEON', 'municipio' => 'MONTERREY', 'clave_elector' => 'GTNVAN03041819M200',
            'padre' => 'RICARDO GUTIERREZ SOLIS', 'madre' => 'PATRICIA NAVARRO REYES', 'acta' => '03318',
            'pasaporte' => 'G48213097', 'licencia' => 'NL2219843', 'matricula' => null,
        ],
        'luis' => [
            'paterno' => 'RAMIREZ', 'materno' => 'TORRES', 'nombres' => 'LUIS ALBERTO',
            'curp17' => 'RATL920905HDFMRS0', 'nacimiento' => '05/09/1992', 'sexo' => 'HOMBRE',
            'entidad' => 'CIUDAD DE MEXICO', 'municipio' => 'COYOACAN', 'clave_elector' => 'RMTRLS92090509H400',
            'padre' => 'ARTURO RAMIREZ VEGA', 'madre' => 'GLORIA TORRES MEJIA', 'acta' => '07741',
            'pasaporte' => null, 'licencia' => 'A09281746', 'matricula' => 'D-1847263',
        ],
        'jose' => [
            'paterno' => 'HERNANDEZ', 'materno' => 'DE LA CRUZ', 'nombres' => 'JOSE MANUEL',
            'curp17' => 'HECM851230HOCRRN0', 'nacimiento' => '30/12/1985', 'sexo' => 'HOMBRE',
            'entidad' => 'OAXACA', 'municipio' => 'OAXACA DE JUAREZ', 'clave_elector' => 'HRCRMN85123020H700',
            'padre' => 'MANUEL HERNANDEZ LOPEZ', 'madre' => 'TERESA DE LA CRUZ SANTIAGO', 'acta' => '00912',
            'pasaporte' => null, 'licencia' => 'OX7730215', 'matricula' => null,
        ],
        'ximena' => [
            'paterno' => 'ORTIZ', 'materno' => 'PEÑA', 'nombres' => 'XIMENA',
            'curp17' => 'OIPX070125MYNRXMA', 'nacimiento' => '25/01/2007', 'sexo' => 'MUJER',
            'entidad' => 'YUCATAN', 'municipio' => 'MERIDA', 'clave_elector' => null,
            'padre' => 'FERNANDO ORTIZ CAN', 'madre' => 'LUCIA PEÑA DZUL', 'acta' => '04406',
            'pasaporte' => 'G77105432', 'licencia' => null, 'matricula' => null,
        ],

        // Tercer juego (--set=estres): casos límite para probar el sistema.
        'brenda' => [ // un solo apellido (X en la CURP), Ñ, nacida el último día de 1999
            'paterno' => 'NUÑEZ', 'materno' => '', 'nombres' => 'BRENDA',
            'curp17' => 'NUXB991231MDFXXR0', 'nacimiento' => '31/12/1999', 'sexo' => 'MUJER',
            'entidad' => 'CIUDAD DE MEXICO', 'municipio' => 'IZTAPALAPA', 'clave_elector' => 'NXBRND99123109M100',
            'padre' => '', 'madre' => 'CAROLINA NUÑEZ PAZ', 'acta' => '05120',
            'pasaporte' => null, 'licencia' => null, 'matricula' => null,
        ],
        'diego' => [ // gemelo de Daniela: mismos apellidos y misma fecha
            'paterno' => 'SALAZAR', 'materno' => 'MORA', 'nombres' => 'DIEGO',
            'curp17' => 'SAMD000101HSRLRGA', 'nacimiento' => '01/01/2000', 'sexo' => 'HOMBRE',
            'entidad' => 'SONORA', 'municipio' => 'HERMOSILLO', 'clave_elector' => 'SLMRDG00010126H300',
            'padre' => 'HECTOR SALAZAR RUIZ', 'madre' => 'IRMA MORA LEYVA', 'acta' => '00031',
            'pasaporte' => null, 'licencia' => null, 'matricula' => null,
        ],
        'daniela' => [
            'paterno' => 'SALAZAR', 'materno' => 'MORA', 'nombres' => 'DANIELA',
            'curp17' => 'SAMD000101MSRLRNA', 'nacimiento' => '01/01/2000', 'sexo' => 'MUJER',
            'entidad' => 'SONORA', 'municipio' => 'HERMOSILLO', 'clave_elector' => 'SLMRDN00010126M800',
            'padre' => 'HECTOR SALAZAR RUIZ', 'madre' => 'IRMA MORA LEYVA', 'acta' => '00032',
            'pasaporte' => null, 'licencia' => 'SO4410982', 'matricula' => null,
        ],
        'guadalupe' => [ // "MA. GUADALUPE" en la INE, "MARIA GUADALUPE" en el acta
            'paterno' => 'TORRES', 'materno' => 'LEON', 'nombres' => 'MARIA GUADALUPE',
            'curp17' => 'TOLG750615MMNRND0', 'nacimiento' => '15/06/1975', 'sexo' => 'MUJER',
            'entidad' => 'MICHOACAN', 'municipio' => 'MORELIA', 'clave_elector' => 'TRLNGD75061516M600',
            'padre' => 'JESUS TORRES ARIAS', 'madre' => 'ESPERANZA LEON VACA', 'acta' => '01577',
            'pasaporte' => null, 'licencia' => null, 'matricula' => null,
        ],
        'kevin' => [ // nacido en el extranjero (clave NE)
            'paterno' => 'WILLIAMS', 'materno' => 'GARZA', 'nombres' => 'KEVIN',
            'curp17' => 'WIGK040310HNELRVA', 'nacimiento' => '10/03/2004', 'sexo' => 'HOMBRE',
            'entidad' => 'NACIDO EN EL EXTRANJERO', 'municipio' => 'HOUSTON', 'clave_elector' => null,
            'padre' => 'JAMES WILLIAMS', 'madre' => 'LAURA GARZA OCHOA', 'acta' => '00745',
            'pasaporte' => 'G30918264', 'licencia' => null, 'matricula' => null,
        ],
        'roberto' => [ // credencial vieja del IFE
            'paterno' => 'CASTRO', 'materno' => 'VIDAL', 'nombres' => 'ROBERTO',
            'curp17' => 'CAVR680820HJCSDB0', 'nacimiento' => '20/08/1968', 'sexo' => 'HOMBRE',
            'entidad' => 'JALISCO', 'municipio' => 'ZAPOPAN', 'clave_elector' => 'CSVDRB68082014H200',
            'padre' => 'ROBERTO CASTRO ORTEGA', 'madre' => 'AMELIA VIDAL ROJAS', 'acta' => '02904',
            'pasaporte' => null, 'licencia' => null, 'matricula' => null,
        ],
    ];

    private string $fuente;

    private string $fuenteNegrita;

    public function handle(): int
    {
        $this->fuente = $this->buscarFuente(['arial.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf']);
        $this->fuenteNegrita = $this->buscarFuente(['arialbd.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf']) ?: $this->fuente;

        if ($this->fuente === '') {
            $this->error('No se encontró una fuente TTF (Arial o DejaVu Sans) en el sistema.');

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: base_path('tests/Fixtures/documentos');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if ($this->option('set') === 'extra') {
            return $this->generarJuegoExtra($dir);
        }

        if ($this->option('set') === 'estres') {
            return $this->generarJuegoEstres($dir);
        }

        $carlos = $this->persona('carlos');
        $maria = $this->persona('maria');
        $julian = $this->persona('julian');

        imagepng($this->ine($carlos), "{$dir}/carlos_ine.png");
        $this->pdfConTexto($this->lineasConstanciaCurp($carlos), "{$dir}/carlos_curp.pdf");
        imagejpeg($this->acta($carlos), "{$dir}/carlos_acta.jpg", 90);

        $curpMaria = imagerotate($this->constanciaCurp($maria), 90, 0);
        imagejpeg($curpMaria, "{$dir}/maria_curp_girada.jpg", 90);
        imagepng($this->acta($maria), "{$dir}/maria_acta.png");
        $this->convertirAPdf("{$dir}/maria_acta.png", "{$dir}/maria_acta_escaneada.pdf");
        @unlink("{$dir}/maria_acta.png");

        imagewebp($this->ine($julian), "{$dir}/julian_ine.webp", 92);

        foreach (glob("{$dir}/*") as $archivo) {
            $this->line('  '.basename($archivo));
        }
        $this->info("Documentos de ejemplo generados en {$dir}");
        $this->line('CURP ficticias: '.implode(', ', array_column([$carlos, $maria, $julian], 'curp')));

        return self::SUCCESS;
    }

    /**
     * Expedientes completos de 4 personas nuevas, en formatos variados.
     */
    private function generarJuegoExtra(string $dir): int
    {
        $ana = $this->persona('ana');
        $luis = $this->persona('luis');
        $jose = $this->persona('jose');
        $ximena = $this->persona('ximena');

        // Ana: expediente completo con pasaporte.
        imagejpeg($this->ine($ana), "{$dir}/ana_ine.jpg", 90);
        $this->pdfConTexto($this->lineasConstanciaCurp($ana), "{$dir}/ana_curp.pdf");
        imagepng($this->acta($ana), "{$dir}/ana_acta.png");
        imagejpeg($this->pasaporte($ana), "{$dir}/ana_pasaporte.jpg", 90);
        imagejpeg($this->licencia($ana), "{$dir}/ana_licencia.jpg", 90);

        // Luis: con cartilla militar y licencia; su INE fotografiada de cabeza.
        imagejpeg(imagerotate($this->ine($luis), 180, 0), "{$dir}/luis_ine_de_cabeza.jpg", 90);
        $this->pdfConTexto($this->lineasConstanciaCurp($luis), "{$dir}/luis_curp.pdf");
        imagejpeg($this->acta($luis), "{$dir}/luis_acta.jpg", 90);
        imagepng($this->cartilla($luis), "{$dir}/luis_cartilla_militar.png");
        imagewebp($this->licencia($luis), "{$dir}/luis_licencia.webp", 90);

        // José Manuel: apellido compuesto y licencia BORROSA (sin CURP
        // legible): en carga masiva se reconoce por su nombre al final.
        imagepng($this->ine($jose), "{$dir}/jose_ine.png");
        imagepng($this->constanciaCurp($jose), "{$dir}/jose_curp.png");
        imagepng($this->acta($jose), "{$dir}/jose_acta.png");
        $this->convertirAPdf("{$dir}/jose_acta.png", "{$dir}/jose_acta_escaneada.pdf");
        @unlink("{$dir}/jose_acta.png");
        imagejpeg($this->desenfocar($this->licencia($jose)), "{$dir}/jose_licencia_borrosa.jpg", 70);

        // Ximena: menor de edad (sin INE), con Ñ en el apellido.
        $this->pdfConTexto($this->lineasConstanciaCurp($ximena), "{$dir}/ximena_curp.pdf");
        imagejpeg($this->acta($ximena), "{$dir}/ximena_acta.jpg", 90);
        imagepng($this->pasaporte($ximena), "{$dir}/ximena_pasaporte.png");

        foreach (glob("{$dir}/*") as $archivo) {
            $this->line('  '.basename($archivo));
        }
        $this->info("Documentos de ejemplo generados en {$dir}");
        $this->line('CURP ficticias: '.implode(', ', array_column([$ana, $luis, $jose, $ximena], 'curp')));

        return self::SUCCESS;
    }

    /**
     * Casos límite y fotos de mala calidad. Además de los archivos escribe
     * esperado.json con el tipo y la CURP que DEBE detectar el sistema en
     * cada uno, para verificarlo automáticamente.
     */
    private function generarJuegoEstres(string $dir): int
    {
        $p = array_map(fn (string $clave) => $this->persona($clave), array_combine(
            ['brenda', 'diego', 'daniela', 'guadalupe', 'kevin', 'roberto'],
            ['brenda', 'diego', 'daniela', 'guadalupe', 'kevin', 'roberto'],
        ));
        $esperado = [];
        $guardar = function (string $archivo, \GdImage $img, string $tipo, string $persona, int $calidad = 88) use ($dir, $p, &$esperado) {
            match (pathinfo($archivo, PATHINFO_EXTENSION)) {
                'png' => imagepng($img, "{$dir}/{$archivo}"),
                'webp' => imagewebp($img, "{$dir}/{$archivo}", $calidad),
                default => imagejpeg($img, "{$dir}/{$archivo}", $calidad),
            };
            $esperado[$archivo] = ['tipo' => $tipo, 'curp' => $p[$persona]['curp']];
        };
        $pdf = function (string $archivo, array $imagenes, string $tipo, string $persona) use ($dir, $p, &$esperado) {
            $rutas = [];
            foreach ($imagenes as $i => $img) {
                imagepng($img, $rutas[] = "{$dir}/_pagina{$i}.png");
            }
            $this->convertirAPdf($rutas, "{$dir}/{$archivo}");
            array_map('unlink', $rutas);
            $esperado[$archivo] = ['tipo' => $tipo, 'curp' => $p[$persona]['curp']];
        };

        // Brenda: INE fotografiada muy chica y acta inclinada.
        $guardar('brenda_ine_chica.jpg', $this->achicar($this->ine($p['brenda']), 520), 'ine', 'brenda');
        $guardar('brenda_acta_inclinada.jpg', $this->inclinar($this->acta($p['brenda']), 4), 'acta_nacimiento', 'brenda');

        // Gemelos: la licencia borrosa de Daniela NO debe irse a Diego.
        $guardar('diego_ine.jpg', $this->ine($p['diego']), 'ine', 'diego');
        $pdf('diego_acta_escaneada.pdf', [$this->acta($p['diego'])], 'acta_nacimiento', 'diego');
        $guardar('daniela_ine_oscura.jpg', $this->oscurecer($this->ine($p['daniela'])), 'ine', 'daniela');
        $this->pdfConTexto($this->lineasConstanciaCurp($p['daniela']), "{$dir}/daniela_curp.pdf");
        $esperado['daniela_curp.pdf'] = ['tipo' => 'curp', 'curp' => $p['daniela']['curp']];
        $guardar('daniela_licencia_borrosa.jpg', $this->desenfocar($this->licencia($p['daniela'])), 'licencia_conducir', 'daniela', 70);

        // Guadalupe: "MA." en la INE, acta girada 90°, CURP muy comprimida.
        $guardar('guadalupe_ine.png', $this->ine(array_merge($p['guadalupe'], ['nombres' => 'MA. GUADALUPE'])), 'ine', 'guadalupe');
        $guardar('guadalupe_acta_girada.jpg', imagerotate($this->acta($p['guadalupe']), 270, 0), 'acta_nacimiento', 'guadalupe');
        $guardar('guadalupe_curp_comprimida.jpg', $this->constanciaCurp($p['guadalupe']), 'curp', 'guadalupe', 18);

        // Kevin: nacido en el extranjero.
        $this->pdfConTexto($this->lineasConstanciaCurp($p['kevin']), "{$dir}/kevin_curp.pdf");
        $esperado['kevin_curp.pdf'] = ['tipo' => 'curp', 'curp' => $p['kevin']['curp']];
        $guardar('kevin_pasaporte.jpg', $this->pasaporte($p['kevin']), 'pasaporte', 'kevin');

        // Roberto: credencial vieja del IFE y acta escaneada de 2 páginas.
        $guardar('roberto_ife.jpg', $this->ine($p['roberto'], true), 'ine', 'roberto');
        $pdf('roberto_acta_2_paginas.pdf', [$this->portadaCopiaCertificada(), $this->acta($p['roberto'])], 'acta_nacimiento', 'roberto');
        $guardar('roberto_curp.png', $this->constanciaCurp($p['roberto']), 'curp', 'roberto');

        file_put_contents("{$dir}/esperado.json", json_encode($esperado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        foreach (glob("{$dir}/*") as $archivo) {
            $this->line('  '.basename($archivo));
        }
        $this->info("Documentos de ejemplo generados en {$dir}");
        $this->line('CURP ficticias: '.implode(', ', array_column($p, 'curp')));

        return self::SUCCESS;
    }

    private function persona(string $clave): array
    {
        $p = self::PERSONAS[$clave];
        $p['curp'] = $p['curp17'].Curp::digitoVerificador($p['curp17']);

        return $p;
    }

    // ------------------------------------------------------------------ INE

    /**
     * @param  bool  $ife  credencial anterior a 2014 (Instituto FEDERAL Electoral)
     */
    private function ine(array $p, bool $ife = false): \GdImage
    {
        $img = $this->lienzo(1012, 638, [246, 222, 233], [214, 236, 240]);
        $tinta = imagecolorallocate($img, 25, 25, 35);
        $gris = imagecolorallocate($img, 95, 95, 110);
        $guinda = imagecolorallocate($img, 128, 22, 64);

        // Patrón de seguridad de fondo (líneas finas de color), como en la credencial real.
        $patron = imagecolorallocatealpha($img, 180, 80, 130, 105);
        for ($x = -640; $x < 1012; $x += 18) {
            imageline($img, $x, 638, $x + 640, 0, $patron);
        }

        $this->texto($img, 30, 50, 17, 'MÉXICO', $guinda, true);
        $this->texto($img, 300, 42, 19, $ife ? 'INSTITUTO FEDERAL ELECTORAL' : 'INSTITUTO NACIONAL ELECTORAL', $guinda, true);
        $this->texto($img, 300, 72, 15, $ife ? 'CREDENCIAL PARA VOTAR CON FOTOGRAFIA' : 'CREDENCIAL PARA VOTAR', $guinda, true);

        // Recuadro de la foto.
        imagefilledrectangle($img, 32, 120, 262, 420, imagecolorallocate($img, 200, 200, 210));
        $this->texto($img, 105, 280, 16, 'FOTO', $gris, true);

        $x = 300;
        $this->texto($img, $x, 130, 13, 'NOMBRE', $gris);
        $this->texto($img, $x, 160, 21, $p['paterno'], $tinta, true);
        $this->texto($img, $x, 190, 21, $p['materno'], $tinta, true);
        $this->texto($img, $x, 220, 21, $p['nombres'], $tinta, true);
        $this->texto($img, 770, 130, 13, 'SEXO '.$p['sexo'][0], $tinta, true);

        $this->texto($img, $x, 262, 13, 'DOMICILIO', $gris);
        $this->texto($img, $x, 290, 18, 'C 5 DE MAYO 123 COL CENTRO', $tinta, true);
        $this->texto($img, $x, 318, 18, '72000 '.$p['municipio'].', '.$p['entidad'], $tinta, true);

        $this->texto($img, $x, 362, 13, 'CLAVE DE ELECTOR', $gris);
        $this->texto($img, $x + 180, 362, 18, $p['clave_elector'], $tinta, true);
        $this->texto($img, $x, 396, 13, 'CURP', $gris);
        $this->texto($img, $x + 180, 396, 18, $p['curp'], $tinta, true);
        $this->texto($img, $x, 430, 13, 'AÑO DE REGISTRO 2019 01', $tinta);
        $this->texto($img, $x, 464, 13, 'FECHA DE NACIMIENTO', $gris);
        $this->texto($img, $x + 230, 464, 18, $p['nacimiento'], $tinta, true);
        $this->texto($img, $x, 498, 13, 'SECCIÓN 1234', $tinta);
        $this->texto($img, $x + 230, 498, 13, 'VIGENCIA 2019 - 2029', $tinta);

        $this->leyendaFicticia($img);

        return $img;
    }

    // -------------------------------------------------------------- CURP

    /** @return string[] */
    private function lineasConstanciaCurp(array $p): array
    {
        return [
            'ESTADOS UNIDOS MEXICANOS',
            'CONSTANCIA DE LA CLAVE ÚNICA DE REGISTRO DE POBLACIÓN',
            '',
            'Clave: '.$p['curp'],
            'Nombre',
            $p['nombres'].' '.$p['paterno'].' '.$p['materno'],
            'Fecha de inscripción: 15/08/2001',
            'Entidad de registro: '.$p['entidad'],
            '',
            'Datos del documento probatorio',
            'Acta de nacimiento   Año de registro 2001   Número de acta '.$p['acta'],
            '',
            'Registro Nacional de Población (RENAPO)',
            'EJEMPLO FICTICIO - DOCUMENTO SIN VALIDEZ',
        ];
    }

    private function constanciaCurp(array $p): \GdImage
    {
        $img = $this->lienzo(1240, 900, [255, 255, 255], [240, 246, 244]);
        $tinta = imagecolorallocate($img, 20, 20, 20);
        $verde = imagecolorallocate($img, 16, 90, 70);

        $y = 90;
        foreach ($this->lineasConstanciaCurp($p) as $i => $linea) {
            $grande = $i === 1 || str_starts_with($linea, 'Clave') || $i === 5;
            $this->texto($img, 70, $y, $grande ? 24 : 19, $linea, $i < 2 ? $verde : $tinta, $grande);
            $y += $linea === '' ? 30 : 52;
        }

        return $img;
    }

    // -------------------------------------------------------------- Acta

    private function acta(array $p): \GdImage
    {
        $img = $this->lienzo(1275, 1650, [250, 252, 247], [233, 241, 230]);
        $tinta = imagecolorallocate($img, 20, 20, 20);
        $gris = imagecolorallocate($img, 90, 90, 90);
        $verde = imagecolorallocate($img, 20, 80, 60);

        $this->texto($img, 380, 110, 24, 'ESTADOS UNIDOS MEXICANOS', $verde, true);
        $this->texto($img, 440, 170, 30, 'ACTA DE NACIMIENTO', $tinta, true);
        $this->texto($img, 90, 240, 16, 'Identificador electrónico: 21114001200101234', $gris);

        $this->texto($img, 90, 310, 15, 'Entidad de registro    Municipio de registro    Oficialía    Fecha de registro    Libro    Número de acta', $gris);
        $this->texto($img, 90, 345, 18, $p['entidad'].'    '.$p['municipio'].'    0001    15/08/2001    0003    '.$p['acta'], $tinta, true);

        $this->texto($img, 90, 430, 20, 'Datos de la persona registrada', $verde, true);
        $this->texto($img, 90, 490, 18, 'Nombre(s): '.$p['nombres'].'   Primer apellido: '.$p['paterno'].'   Segundo apellido: '.$p['materno'], $tinta);
        $this->texto($img, 90, 550, 18, 'Sexo: '.$p['sexo'].'   Fecha de nacimiento: '.$p['nacimiento'], $tinta);
        $this->texto($img, 90, 610, 18, 'Lugar de nacimiento: '.$p['municipio'].', '.$p['entidad'], $tinta);
        $this->texto($img, 90, 670, 18, 'CURP: '.$p['curp'], $tinta, true);

        $this->texto($img, 90, 770, 20, 'Datos de filiación de la persona registrada', $verde, true);
        $this->texto($img, 90, 830, 18, 'Progenitor 1: '.$p['padre'].'   Nacionalidad: MEXICANA', $tinta);
        $this->texto($img, 90, 890, 18, 'Progenitor 2: '.$p['madre'].'   Nacionalidad: MEXICANA', $tinta);

        $this->texto($img, 90, 1000, 20, 'Anotaciones marginales', $verde, true);
        $this->texto($img, 90, 1060, 16, 'Sin anotaciones.', $gris);
        $this->texto($img, 90, 1200, 16, 'El suscrito oficial del Registro Civil certifica que los datos son copia fiel del libro original.', $gris);

        $this->leyendaFicticia($img);

        return $img;
    }

    // ---------------------------------------------------------- Pasaporte

    private function pasaporte(array $p): \GdImage
    {
        $img = $this->lienzo(1250, 880, [233, 240, 236], [214, 228, 222]);
        $tinta = imagecolorallocate($img, 20, 30, 30);
        $gris = imagecolorallocate($img, 80, 90, 95);
        $verde = imagecolorallocate($img, 18, 74, 62);
        [$dia, $mes, $anio] = explode('/', $p['nacimiento']);

        $this->texto($img, 60, 70, 22, 'ESTADOS UNIDOS MEXICANOS', $verde, true);
        $this->texto($img, 60, 105, 18, 'PASAPORTE / PASSPORT', $verde, true);
        $this->texto($img, 760, 70, 14, 'SECRETARIA DE RELACIONES EXTERIORES', $gris);

        imagefilledrectangle($img, 60, 150, 330, 500, imagecolorallocate($img, 200, 205, 210));
        $this->texto($img, 150, 335, 16, 'FOTO', $gris, true);

        $x = 380;
        $this->texto($img, $x, 160, 13, 'Tipo / Type   P        Clave del país / Code   MEX', $gris);
        $this->texto($img, $x + 520, 160, 13, 'Pasaporte No. / Passport No.', $gris);
        $this->texto($img, $x + 520, 190, 20, $p['pasaporte'] ?? '', $tinta, true);
        $this->texto($img, $x, 225, 13, 'Apellidos / Surname', $gris);
        $this->texto($img, $x, 255, 20, $p['paterno'].' '.$p['materno'], $tinta, true);
        $this->texto($img, $x, 295, 13, 'Nombres / Given names', $gris);
        $this->texto($img, $x, 325, 20, $p['nombres'], $tinta, true);
        $this->texto($img, $x, 365, 13, 'Nacionalidad / Nationality   MEXICANA', $gris);
        $this->texto($img, $x, 400, 13, 'Fecha de nacimiento / Date of birth', $gris);
        $this->texto($img, $x, 428, 18, "{$dia} {$mes} {$anio}", $tinta, true);
        $this->texto($img, $x + 420, 400, 13, 'Sexo / Sex', $gris);
        $this->texto($img, $x + 420, 428, 18, $p['sexo'][0] === 'H' ? 'M' : 'F', $tinta, true);
        $this->texto($img, $x, 468, 13, 'CURP', $gris);
        $this->texto($img, $x, 496, 18, $p['curp'], $tinta, true);
        $this->texto($img, $x, 536, 13, 'Lugar de nacimiento / Place of birth   '.$p['entidad'], $gris);

        // Zona de lectura mecánica (MRZ).
        $mono = imagecolorallocate($img, 15, 15, 15);
        $nombreMrz = str_replace(' ', '<', Curp::sinAcentos($p['paterno'].'<'.$p['materno'].'<<'.$p['nombres']));
        $this->texto($img, 60, 700, 22, str_pad('P<MEX'.$nombreMrz, 44, '<'), $mono, true);
        $this->texto($img, 60, 745, 22, str_pad(($p['pasaporte'] ?? '').'<0MEX'.substr($anio, 2).$mes.$dia, 44, '<'), $mono, true);

        $this->leyendaFicticia($img);

        return $img;
    }

    // ----------------------------------------------------------- Licencia

    private function licencia(array $p): \GdImage
    {
        $img = $this->lienzo(1012, 638, [226, 236, 248], [244, 238, 226]);
        $tinta = imagecolorallocate($img, 20, 25, 40);
        $gris = imagecolorallocate($img, 85, 90, 105);
        $azul = imagecolorallocate($img, 25, 60, 130);

        $this->texto($img, 300, 48, 20, 'LICENCIA PARA CONDUCIR', $azul, true);
        $this->texto($img, 300, 78, 14, 'ESTADO DE '.$p['entidad'].'  ·  SECRETARIA DE MOVILIDAD', $azul);

        imagefilledrectangle($img, 32, 110, 262, 400, imagecolorallocate($img, 200, 200, 212));
        $this->texto($img, 105, 265, 16, 'FOTO', $gris, true);

        $x = 300;
        $this->texto($img, $x, 130, 13, 'No. DE LICENCIA', $gris);
        $this->texto($img, $x + 170, 130, 17, $p['licencia'] ?? '', $tinta, true);
        $this->texto($img, $x + 470, 130, 13, 'TIPO  A  AUTOMOVILISTA', $gris);
        $this->texto($img, $x, 175, 13, 'NOMBRE', $gris);
        $this->texto($img, $x, 205, 21, $p['nombres'], $tinta, true);
        $this->texto($img, $x, 235, 21, $p['paterno'].' '.$p['materno'], $tinta, true);
        $this->texto($img, $x, 280, 13, 'CURP', $gris);
        $this->texto($img, $x + 70, 280, 17, $p['curp'], $tinta, true);
        $this->texto($img, $x, 320, 13, 'FECHA DE NACIMIENTO', $gris);
        $this->texto($img, $x + 230, 320, 17, $p['nacimiento'], $tinta, true);
        $this->texto($img, $x, 360, 13, 'EXPEDICION 08/07/2024', $gris);
        $this->texto($img, $x + 260, 360, 13, 'VIGENCIA 06/07/2027', $gris);

        $this->leyendaFicticia($img);

        return $img;
    }

    // ----------------------------------------------------------- Cartilla

    private function cartilla(array $p): \GdImage
    {
        $img = $this->lienzo(1100, 760, [236, 232, 214], [222, 216, 194]);
        $tinta = imagecolorallocate($img, 30, 30, 20);
        $gris = imagecolorallocate($img, 90, 88, 70);
        $verde = imagecolorallocate($img, 52, 70, 30);

        $this->texto($img, 300, 70, 20, 'SECRETARIA DE LA DEFENSA NACIONAL', $verde, true);
        $this->texto($img, 330, 110, 24, 'CARTILLA DE IDENTIDAD', $tinta, true);
        $this->texto($img, 340, 145, 16, 'SERVICIO MILITAR NACIONAL', $verde, true);

        imagefilledrectangle($img, 60, 190, 290, 470, imagecolorallocate($img, 200, 196, 180));
        $this->texto($img, 135, 340, 16, 'FOTO', $gris, true);

        $x = 340;
        $this->texto($img, $x, 210, 14, 'MATRICULA', $gris);
        $this->texto($img, $x + 150, 210, 20, $p['matricula'] ?? '', $tinta, true);
        $this->texto($img, $x, 260, 14, 'NOMBRE', $gris);
        $this->texto($img, $x, 295, 20, $p['paterno'].' '.$p['materno'].' '.$p['nombres'], $tinta, true);
        $this->texto($img, $x, 345, 14, 'CURP', $gris);
        $this->texto($img, $x + 80, 345, 18, $p['curp'], $tinta, true);
        $this->texto($img, $x, 395, 14, 'FECHA DE NACIMIENTO  '.$p['nacimiento'], $gris);
        $this->texto($img, $x, 435, 14, 'CLASE '.substr($p['nacimiento'], -4).'   ·   JUNTA MUNICIPAL DE RECLUTAMIENTO '.$p['municipio'], $gris);

        $this->leyendaFicticia($img);

        return $img;
    }

    /**
     * Simula una foto movida/desenfocada: reduce, vuelve a ampliar y
     * desenfoca, hasta que la CURP (texto chico) deja de leerse pero el
     * nombre (texto grande) todavía se alcanza a reconocer.
     */
    private function desenfocar(\GdImage $img, float $factor = 0.28): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $pequena = imagescale($img, (int) ($w * $factor), (int) ($h * $factor), IMG_BILINEAR_FIXED);
        $borrosa = imagescale($pequena, $w, $h, IMG_BILINEAR_FIXED);
        imagedestroy($pequena);
        imagedestroy($img);

        for ($i = 0; $i < 3; $i++) {
            imagefilter($borrosa, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $borrosa;
    }

    // ------------------------------------------------- Fotos de mala calidad

    private function achicar(\GdImage $img, int $ancho): \GdImage
    {
        $chica = imagescale($img, $ancho, (int) round(imagesy($img) * $ancho / imagesx($img)), IMG_BILINEAR_FIXED);
        imagedestroy($img);

        return $chica;
    }

    private function inclinar(\GdImage $img, float $grados): \GdImage
    {
        $inclinada = imagerotate($img, $grados, imagecolorallocate($img, 245, 245, 240));
        imagedestroy($img);

        return $inclinada;
    }

    private function oscurecer(\GdImage $img): \GdImage
    {
        imagefilter($img, IMG_FILTER_BRIGHTNESS, -70);
        imagefilter($img, IMG_FILTER_CONTRAST, 25);

        return $img;
    }

    private function portadaCopiaCertificada(): \GdImage
    {
        $img = $this->lienzo(1275, 1650, [252, 252, 250], [240, 244, 238]);
        $tinta = imagecolorallocate($img, 30, 30, 30);
        $this->texto($img, 330, 600, 28, 'COPIA CERTIFICADA', $tinta, true);
        $this->texto($img, 200, 680, 18, 'Documento expedido por medios electrónicos.', $tinta);
        $this->texto($img, 200, 720, 18, 'Consta de 2 páginas. Página 1 de 2.', $tinta);
        $this->leyendaFicticia($img);

        return $img;
    }

    // ------------------------------------------------------------ Soporte

    /**
     * Lienzo con degradado vertical entre dos colores (un fondo plano sería
     * demasiado fácil para el OCR).
     */
    private function lienzo(int $w, int $h, array $arriba, array $abajo): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $color = imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $t), $arriba, $abajo));
            imageline($img, 0, $y, $w, $y, $color);
        }

        return $img;
    }

    private function texto(\GdImage $img, int $x, int $y, int $tamano, string $texto, int $color, bool $negrita = false): void
    {
        imagettftext($img, $tamano, 0, $x, $y, $color, $negrita ? $this->fuenteNegrita : $this->fuente, $texto);
    }

    private function leyendaFicticia(\GdImage $img): void
    {
        $rojo = imagecolorallocatealpha($img, 200, 30, 30, 40);
        $this->texto($img, 30, imagesy($img) - 25, 13, 'EJEMPLO FICTICIO - SIN VALIDEZ OFICIAL', $rojo, true);
    }

    private function buscarFuente(array $nombres): string
    {
        $carpetas = ['C:/Windows/Fonts', '/usr/share/fonts/truetype/dejavu', '/usr/share/fonts/truetype/liberation', '/Library/Fonts'];

        foreach ($carpetas as $carpeta) {
            foreach ($nombres as $nombre) {
                if (is_file("{$carpeta}/{$nombre}")) {
                    return "{$carpeta}/{$nombre}";
                }
            }
        }

        return '';
    }

    /**
     * PDF "digital" (con texto seleccionable, como la constancia que se
     * descarga de gob.mx): se escribe a mano con la fuente Helvetica
     * integrada en todos los lectores de PDF.
     *
     * @param  string[]  $lineas
     */
    private function pdfConTexto(array $lineas, string $ruta): void
    {
        $contenido = "BT\n/F1 12 Tf\n14 TL\n60 780 Td\n";
        foreach ($lineas as $linea) {
            $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $linea);
            $contenido .= '('.addcslashes($texto, '()\\').") '\n";
        }
        $contenido .= 'ET';

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $posiciones = [];
        foreach ($objetos as $i => $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }
        $pdf .= 'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF\n";

        file_put_contents($ruta, $pdf);
    }

    /**
     * PDF "escaneado" (solo imagen, sin texto): se arma con mutool.
     */
    /**
     * @param  string|string[]  $imagenes  una imagen por página
     */
    private function convertirAPdf(string|array $imagenes, string $pdf): void
    {
        $mutool = config('services.mutool.executable');

        if (! $mutool || ! file_exists($mutool)) {
            $this->warn('MUTOOL_PATH no está configurado: se omite el PDF escaneado de ejemplo.');

            return;
        }

        Process::run([$mutool, 'convert', '-O', 'compress-images', '-o', $pdf, ...(array) $imagenes]);
    }
}
