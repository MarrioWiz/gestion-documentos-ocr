<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Carga masiva de punta a punta con OCR REAL: documentos de varias personas
 * mezclados en una sola tanda, con una persona que ya existía.
 */
#[Group('ocr')]
class CargaMasivaOcrRealTest extends TestCase
{
    use RefreshDatabase;

    public function test_separa_documentos_mezclados_de_varias_personas(): void
    {
        if (! is_file((string) config('services.tesseract.executable')) || ! config('services.mutool.executable')) {
            $this->markTestSkipped('Se necesitan Tesseract y mutool instalados.');
        }

        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        // Carlos ya estaba registrado con su INE.
        $carlos = Persona::create(['curp' => 'MERC010722HPLNZRA3', 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);
        Documento::create(['persona_id' => $carlos->id, 'tipo_documento' => 'ine', 'ruta_archivo' => 'documentos/previa.png']);

        // Tanda revuelta: documentos de Carlos, María y Julián intercalados.
        $esperado = [
            'maria_acta_escaneada.pdf' => ['guardado', 'LOGF951103MJCPRR08'],
            'carlos_acta.jpg' => ['guardado', 'MERC010722HPLNZRA3'],
            'julian_ine.webp' => ['guardado', 'SAVJ870214HQTNLL03'],
            'carlos_ine.png' => ['omitido', 'MERC010722HPLNZRA3'],
            'maria_curp_girada.jpg' => ['guardado', 'LOGF951103MJCPRR08'],
        ];

        foreach ($esperado as $archivo => [$status, $curp]) {
            $subido = new UploadedFile(base_path("tests/Fixtures/documentos/{$archivo}"), $archivo, null, null, true);

            $this->postJson(route('documentos.carga-masiva.procesar'), ['archivo' => $subido])
                ->assertOk()
                ->assertJson(['status' => $status, 'curp' => $curp]);
        }

        $expedientes = Persona::with('documentos')->get()
            ->mapWithKeys(fn (Persona $p) => [$p->curp => $p->documentos->pluck('tipo_documento')->sort()->values()->all()])
            ->all();

        $this->assertEquals([
            'MERC010722HPLNZRA3' => ['acta_nacimiento', 'ine'],
            'LOGF951103MJCPRR08' => ['acta_nacimiento', 'curp'],
            'SAVJ870214HQTNLL03' => ['ine'],
        ], $expedientes);
    }
}
