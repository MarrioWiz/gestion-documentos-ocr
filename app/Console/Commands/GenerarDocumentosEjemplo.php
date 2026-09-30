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
#[Signature('documentos:generar-ejemplos {--dir= : Carpeta de salida (por defecto tests/Fixtures/documentos)}')]
#[Description('Genera INE, CURP y actas de nacimiento ficticias para probar el OCR.')]
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

    private function persona(string $clave): array
    {
        $p = self::PERSONAS[$clave];
        $p['curp'] = $p['curp17'].Curp::digitoVerificador($p['curp17']);

        return $p;
    }

    // ------------------------------------------------------------------ INE

    private function ine(array $p): \GdImage
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
        $this->texto($img, 300, 42, 19, 'INSTITUTO NACIONAL ELECTORAL', $guinda, true);
        $this->texto($img, 300, 72, 15, 'CREDENCIAL PARA VOTAR', $guinda, true);

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
    private function convertirAPdf(string $imagen, string $pdf): void
    {
        $mutool = config('services.mutool.executable');

        if (! $mutool || ! file_exists($mutool)) {
            $this->warn('MUTOOL_PATH no está configurado: se omite el PDF escaneado de ejemplo.');

            return;
        }

        Process::run([$mutool, 'convert', '-O', 'compress-images', '-o', $pdf, $imagen]);
    }
}
