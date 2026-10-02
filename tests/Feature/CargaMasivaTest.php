<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\HistorialAcceso;
use App\Models\Persona;
use App\Models\User;
use App\Services\DocumentoOcrService;
use App\Services\RegistroDocumentos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Reglas de la carga masiva con un OCR simulado (el OCR real se prueba en
 * OcrDocumentosEjemploTest). Cada archivo subido recibe el siguiente
 * resultado de la cola $lecturas.
 */
class CargaMasivaTest extends TestCase
{
    use RefreshDatabase;

    private const CURP_CARLOS = 'MERC010722HPLNZRA3';

    /** @var array<int, array> */
    private array $lecturas = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $this->mock(DocumentoOcrService::class, function ($mock) {
            $mock->shouldReceive('extraer')->andReturnUsing(fn () => array_shift($this->lecturas));
        });
    }

    private function lectura(string $tipo, array $cambios = []): array
    {
        return array_merge([
            'texto' => "texto de {$tipo}",
            'curp' => self::CURP_CARLOS,
            'curp_verificada' => true,
            'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL',
            'numero_documento' => null,
            'fecha_nacimiento' => '2001-07-22',
            'entidad_nacimiento' => 'Puebla',
            'tipo_documento' => $tipo,
            'puntaje_tipo' => 1.0,
            'metodo' => 'ocr_imagen',
        ], $cambios);
    }

    private function subir(string $nombre, array $lectura): TestResponse
    {
        $this->lecturas[] = $lectura;

        // Contenido único por archivo para que la huella SHA-256 no se repita.
        $archivo = UploadedFile::fake()->createWithContent($nombre, random_bytes(64));

        return $this->postJson(route('documentos.carga-masiva.procesar'), ['archivo' => $archivo]);
    }

    public function test_agrega_solo_el_acta_a_una_persona_que_ya_tenia_ine_y_curp(): void
    {
        $this->subir('ine.png', $this->lectura('ine'))->assertJson(['status' => 'guardado', 'persona_nueva' => true]);
        $this->subir('curp.pdf', $this->lectura('curp'))->assertJson(['status' => 'guardado', 'persona_nueva' => false]);

        // Ahora se suben los tres: INE y CURP ya existen, el acta es nueva.
        $this->subir('ine2.png', $this->lectura('ine'))->assertJson(['status' => 'omitido']);
        $this->subir('curp2.pdf', $this->lectura('curp'))->assertJson(['status' => 'omitido']);
        $this->subir('acta.jpg', $this->lectura('acta_nacimiento', ['numero_documento' => '01234']))
            ->assertJson([
                'status' => 'guardado',
                'persona_nueva' => false,
                'tipo_documento' => 'acta_nacimiento',
                'porcentaje' => 50,
            ]);

        $this->assertSame(1, Persona::count());
        $persona = Persona::first();
        $this->assertEqualsCanonicalizing(['ine', 'curp', 'acta_nacimiento'], $persona->documentos->pluck('tipo_documento')->all());
        $this->assertSame('01234', $persona->documentos->firstWhere('tipo_documento', 'acta_nacimiento')->numero_documento);
    }

    public function test_el_archivo_se_guarda_en_el_disco_privado_con_su_huella(): void
    {
        $this->subir('ine.png', $this->lectura('ine'))->assertJson(['status' => 'guardado']);

        $documento = Documento::first();
        Storage::disk('local')->assertExists($documento->ruta_archivo);
        $this->assertStringStartsWith('documentos/', $documento->ruta_archivo);
        $this->assertSame(64, strlen($documento->archivo_hash));
        $this->assertSame(auth()->id(), $documento->subido_por);
        $this->assertTrue(HistorialAcceso::where('accion', 'subida')->exists());
    }

    public function test_el_mismo_archivo_subido_dos_veces_se_omite(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('ine.png', 'contenido-identico');

        $this->lecturas = [$this->lectura('ine'), $this->lectura('curp')];
        $this->postJson(route('documentos.carga-masiva.procesar'), ['archivo' => $archivo])->assertJson(['status' => 'guardado']);

        $copia = UploadedFile::fake()->createWithContent('copia.png', 'contenido-identico');
        $this->postJson(route('documentos.carga-masiva.procesar'), ['archivo' => $copia])
            ->assertJson(['status' => 'omitido'])
            ->assertJsonFragment(['mensaje' => 'Este mismo archivo ya estaba cargado como INE.']);

        $this->assertSame(1, Documento::count());
    }

    public function test_completa_datos_faltantes_de_la_persona_sin_sobrescribir(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => Persona::NOMBRE_PENDIENTE]);

        $this->subir('acta.jpg', $this->lectura('acta_nacimiento'))->assertJson(['status' => 'guardado']);

        $persona->refresh();
        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', $persona->nombre_completo);
        $this->assertSame('2001-07-22', $persona->fecha_nacimiento->toDateString());
        $this->assertSame('Puebla', $persona->entidad_nacimiento);
    }

    public function test_no_crea_persona_nueva_si_la_curp_no_pasa_el_digito_verificador(): void
    {
        $this->subir('ine.png', $this->lectura('ine', ['curp' => 'MERC010722HPLNZRA4', 'curp_verificada' => false]))
            ->assertJson(['status' => 'revision']);

        $this->assertSame(0, Persona::count());
    }

    public function test_asocia_una_curp_mal_leida_por_un_caracter_a_la_persona_existente(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);

        $this->subir('acta.jpg', $this->lectura('acta_nacimiento', ['curp' => 'MERC010722HPLNZRA4', 'curp_verificada' => false]))
            ->assertJson(['status' => 'guardado', 'persona_id' => $persona->id, 'persona_nueva' => false]);

        $this->assertSame(1, Persona::count());
    }

    public function test_sin_tipo_reconocido_pide_revision_y_no_guarda(): void
    {
        $this->subir('foto.png', $this->lectura('ine', ['tipo_documento' => null]))
            ->assertJson(['status' => 'revision', 'curp' => self::CURP_CARLOS]);

        $this->assertSame(0, Documento::count());
    }

    public function test_sin_curp_sugiere_la_persona_con_nombre_parecido(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);

        $this->subir('acta.jpg', $this->lectura('acta_nacimiento', ['curp' => null, 'curp_verificada' => false, 'nombre_completo' => 'CARLOS DANIEL MENDOZA RUIZ']))
            ->assertJson(['status' => 'revision', 'sugerencia_persona_id' => $persona->id, 'curp' => self::CURP_CARLOS]);
    }

    /**
     * Error real: se subieron a la vez la CURP y el acta de una persona
     * nueva. Cuando la carga de la CURP revisó, la persona aún no existía;
     * cuando fue a crearla, la carga del acta ya la había registrado.
     */
    public function test_si_otra_carga_crea_a_la_misma_persona_al_mismo_tiempo_se_reutiliza(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);
        $this->partialMock(RegistroDocumentos::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('personaPorCurp')->once()->andReturnNull();
        });

        $this->subir('curp.pdf', $this->lectura('curp'))
            ->assertOk()
            ->assertJson(['status' => 'guardado', 'persona_nueva' => false, 'persona_id' => $persona->id]);

        $this->assertSame(1, Persona::count());
        $this->assertSame(['curp'], $persona->documentos()->pluck('tipo_documento')->all());
    }

    public function test_si_otra_carga_guarda_el_mismo_documento_al_mismo_tiempo_se_omite_sin_error(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);
        Documento::create(['persona_id' => $persona->id, 'tipo_documento' => 'acta_nacimiento', 'ruta_archivo' => 'documentos/otra-carga.jpg']);
        $this->partialMock(RegistroDocumentos::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('yaTieneTipo')->once()->andReturnFalse();
        });

        $this->subir('acta.jpg', $this->lectura('acta_nacimiento'))
            ->assertOk()
            ->assertJson(['status' => 'omitido', 'persona_id' => $persona->id]);

        $this->assertSame(1, Documento::count());
        // El archivo de la carga que perdió no se queda huérfano en el disco.
        $this->assertSame([], Storage::disk('local')->allFiles("documentos/{$persona->id}"));
    }

    public function test_varias_personas_en_una_sola_carga(): void
    {
        $this->subir('ine.png', $this->lectura('ine'))->assertJson(['status' => 'guardado']);
        $this->subir('acta_maria.pdf', $this->lectura('acta_nacimiento', [
            'curp' => 'LOGF951103MJCPRR08',
            'nombre_completo' => 'LOPEZ GARCIA MARIA FERNANDA',
        ]))->assertJson(['status' => 'guardado', 'persona_nueva' => true]);

        $this->assertSame(2, Persona::count());
    }

    public function test_la_subida_individual_agrega_el_acta_al_expediente_existente(): void
    {
        $persona = Persona::create(['curp' => self::CURP_CARLOS, 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);

        $this->post(route('documentos.store'), [
            'curp' => strtolower(self::CURP_CARLOS),
            'nombre_completo' => 'mendoza ruiz carlos daniel',
            'tipo_documento' => 'acta_nacimiento',
            'archivo' => UploadedFile::fake()->image('acta.jpg'),
        ])->assertRedirect(route('personas.show', $persona));

        $this->assertSame(['acta_nacimiento'], $persona->documentos()->pluck('tipo_documento')->all());
    }
}
