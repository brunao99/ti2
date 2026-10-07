<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** A raiz manda quem não está logado para o login. */
    public function test_raiz_leva_visitante_ao_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
