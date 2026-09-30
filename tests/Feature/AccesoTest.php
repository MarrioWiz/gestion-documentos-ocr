<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_todo_redirige_al_login(): void
    {
        foreach (['/', '/panel', '/personas', '/documentos/crear', '/documentos/carga-masiva', '/historial'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    public function test_inicia_y_cierra_sesion_y_queda_en_el_historial(): void
    {
        $usuario = User::factory()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'secreto123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->assertDatabaseHas('historial_accesos', ['user_id' => $usuario->id, 'accion' => 'login']);
        $this->assertDatabaseHas('historial_accesos', ['user_id' => $usuario->id, 'accion' => 'logout']);
    }

    public function test_contrasena_incorrecta_no_entra(): void
    {
        $usuario = User::factory()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'otra'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('historial_accesos', ['accion' => 'login_fallido']);
    }

    public function test_solo_el_administrador_ve_historial_y_usuarios(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/historial')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/historial')->assertOk();
        $this->get('/usuarios')->assertOk();
    }

    public function test_las_paginas_principales_cargan(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $persona = Persona::create(['curp' => 'MERC010722HPLNZRA3', 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);

        $this->get('/panel')->assertOk()->assertSee('Documentos por tipo');
        $this->get('/personas')->assertOk()->assertSee('MENDOZA RUIZ CARLOS DANIEL');
        $this->get(route('personas.show', $persona))->assertOk()->assertSee('Acta de nacimiento');
        $this->get('/documentos/crear')->assertOk();
        $this->get('/documentos/carga-masiva')->assertOk();
    }

    public function test_el_archivo_de_un_documento_solo_se_sirve_con_sesion(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/1/ine.png', 'imagen');
        $persona = Persona::create(['curp' => 'MERC010722HPLNZRA3', 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);
        $documento = Documento::create([
            'persona_id' => $persona->id,
            'tipo_documento' => 'ine',
            'ruta_archivo' => 'documentos/1/ine.png',
        ]);

        $this->get(route('documentos.archivo', $documento))->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->get(route('documentos.archivo', $documento))->assertOk();
        $this->assertDatabaseHas('historial_accesos', ['accion' => 'consulta']);
    }

    public function test_el_reporte_csv_incluye_los_faltantes(): void
    {
        $this->actingAs(User::factory()->create());
        Persona::create(['curp' => 'MERC010722HPLNZRA3', 'nombre_completo' => 'MENDOZA RUIZ CARLOS DANIEL']);

        $respuesta = $this->get(route('personas.reporte'))->assertOk();

        $this->assertStringContainsString('MENDOZA RUIZ CARLOS DANIEL', $respuesta->streamedContent());
        $this->assertStringContainsString('Acta de nacimiento', $respuesta->streamedContent());
    }

    public function test_un_administrador_crea_usuarios_y_no_puede_borrarse_a_si_mismo(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post('/usuarios', ['name' => 'Capturista', 'email' => 'captura@example.com', 'password' => 'password123'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'captura@example.com', 'es_admin' => false]);

        $this->delete(route('usuarios.destroy', $admin));
        $this->assertModelExists($admin);
    }
}
