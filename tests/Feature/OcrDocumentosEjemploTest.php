<?php

namespace Tests\Feature;

use App\Services\DocumentoOcrService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * OCR REAL (Tesseract + mutool) sobre los documentos ficticios de
 * tests/Fixtures/documentos (se regeneran con `php artisan
 * documentos:generar-ejemplos`). Se omite si Tesseract no está instalado.
 * Tarda ~40 s; para saltarlo: `php artisan test --exclude-group=ocr`.
 */
#[Group('ocr')]
class OcrDocumentosEjemploTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function documentos(): array
    {
        return [
            'INE en PNG' => ['carlos_ine.png', 'ine', 'MERC010722HPLNZRA3', 'MENDOZA RUIZ CARLOS DANIEL'],
            'CURP en PDF digital' => ['carlos_curp.pdf', 'curp', 'MERC010722HPLNZRA3', 'MENDOZA RUIZ CARLOS DANIEL'],
            'Acta en JPG' => ['carlos_acta.jpg', 'acta_nacimiento', 'MERC010722HPLNZRA3', 'MENDOZA RUIZ CARLOS DANIEL'],
            'Acta en PDF escaneado' => ['maria_acta_escaneada.pdf', 'acta_nacimiento', 'LOGF951103MJCPRR08', 'LOPEZ GARCIA MARIA FERNANDA'],
            'CURP fotografiada de lado' => ['maria_curp_girada.jpg', 'curp', 'LOGF951103MJCPRR08', 'LOPEZ GARCIA MARIA FERNANDA'],
            'INE en WEBP (Querétaro)' => ['julian_ine.webp', 'ine', 'SAVJ870214HQTNLL03', 'SANCHEZ VELA JULIAN'],
        ];
    }

    #[DataProvider('documentos')]
    public function test_lee_el_documento_de_ejemplo(string $archivo, string $tipo, string $curp, string $nombre): void
    {
        if (! is_file((string) config('services.tesseract.executable'))) {
            $this->markTestSkipped('Tesseract no está instalado en esta máquina.');
        }

        $ruta = base_path("tests/Fixtures/documentos/{$archivo}");

        if (str_ends_with($archivo, '.pdf') && str_contains($archivo, 'escaneada') && ! is_file((string) config('services.mutool.executable'))) {
            $this->markTestSkipped('mutool no está configurado (MUTOOL_PATH).');
        }

        $resultado = app(DocumentoOcrService::class)->extraer($ruta);

        $this->assertSame($tipo, $resultado['tipo_documento']);
        $this->assertSame($curp, $resultado['curp']);
        $this->assertTrue($resultado['curp_verificada']);
        $this->assertSame($nombre, $resultado['nombre_completo']);
    }
}
