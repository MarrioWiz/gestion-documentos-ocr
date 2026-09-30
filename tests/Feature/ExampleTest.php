<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Sin sesión, la raíz manda a la pantalla de inicio de sesión.
     */
    public function test_la_raiz_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('DocuVault');
    }
}
