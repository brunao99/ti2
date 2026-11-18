<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginaInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_ve_a_pagina_inicial_com_contato_e_acesso_ao_painel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Consultoria em gestão pública para prefeituras')
            ->assertSee('O que monitoramos para o seu município')
            ->assertSee('Como a informação chega até a prefeitura')
            ->assertSee('Sobre a Algoritmos e Dados')
            ->assertSee('Fale com a consultoria')
            ->assertSee('https://wa.me/5531984294085?text=', false)
            ->assertSee('mailto:cdalgoritmosedados@gmail.com', false)
            ->assertSee('href="' . route('login') . '"', false)
            ->assertSee('Acessar o painel');
    }

    public function test_os_onze_objetivos_aparecem(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(11, substr_count(
            substr($html, strpos($html, 'id="monitoramos"'), strpos($html, 'id="como-funciona"') - strpos($html, 'id="monitoramos"')),
            '<li>'
        ));
    }

    public function test_quem_ja_entrou_ve_o_atalho_para_o_painel(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/')
            ->assertOk()
            ->assertSee('Abrir o painel')
            ->assertSee('href="' . route('painel.inicio') . '"', false)
            ->assertDontSee('Acessar o painel');
    }

    public function test_as_fotos_e_os_arquivos_da_pagina_existem(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('#assets/site/[a-z-]+\.jpg#', $html, $fotos);
        $this->assertNotEmpty($fotos[0]);
        foreach (array_unique($fotos[0]) as $foto) {
            $this->assertFileExists(public_path($foto));
        }
        $this->assertFileExists(public_path('assets/site.css'));
        $this->assertFileExists(public_path('assets/site.js'));
    }

    public function test_o_login_volta_para_a_pagina_inicial(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="' . route('inicio') . '"', false);
    }
}
