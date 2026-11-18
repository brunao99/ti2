<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginaInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_ve_o_acesso_ao_painel_logo_na_primeira_tela(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->assertSee('Consultoria em gestão pública para prefeituras')
            ->assertSee('Acesso ao painel')
            ->assertSee('action="' . route('login.entrar') . '"', false)
            ->assertSee('name="usuario"', false)
            ->assertSee('name="senha"', false)
            ->assertSee('https://wa.me/5531984294085?text=', false)
            ->assertSee('mailto:cdalgoritmosedados@gmail.com', false)
            ->getContent();

        // O formulário vem antes de qualquer outra seção da página.
        $this->assertLessThan(strpos($html, 'id="objetivos"'), strpos($html, 'id="acesso"'));
    }

    public function test_o_endereco_de_login_mostra_a_mesma_pagina(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Acesso ao painel')
            ->assertSee('id="objetivos"', false);
    }

    public function test_os_onze_objetivos_aparecem_com_as_imagens_do_site(): void
    {
        $html = $this->get('/')->getContent();
        $objetivos = substr($html, strpos($html, 'id="objetivos"'), strpos($html, 'id="como-funciona"') - strpos($html, 'id="objetivos"'));

        $this->assertSame(11, substr_count($objetivos, '<li class="objetivo">'));
        $this->assertStringContainsString('objetivos/ministerios.jpg', $objetivos);
        $this->assertStringContainsString('objetivos/caixa.jpg', $objetivos);
        $this->assertStringContainsString('objetivos/cnm.jpg', $objetivos);
    }

    public function test_login_errado_volta_para_a_pagina_inicial_com_a_mensagem(): void
    {
        User::factory()->create(['usuario' => 'prefeitura', 'password' => bcrypt('certa')]);

        $this->from('/')
            ->post(route('login.entrar'), ['usuario' => 'prefeitura', 'senha' => 'errada'])
            ->assertRedirect('/');

        $this->get('/')
            ->assertSee('Usuário ou senha inválidos.')
            ->assertSee('value="prefeitura"', false);
        $this->assertGuest();
    }

    public function test_login_certo_entra_no_painel(): void
    {
        User::factory()->create(['usuario' => 'prefeitura', 'password' => bcrypt('certa')]);

        $this->from('/')
            ->post(route('login.entrar'), ['usuario' => 'prefeitura', 'senha' => 'certa'])
            ->assertRedirect(route('painel.inicio'));
        $this->assertAuthenticated();
    }

    public function test_quem_ja_entrou_ve_o_atalho_para_o_painel_no_lugar_do_login(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/')
            ->assertOk()
            ->assertSee('Abrir o painel')
            ->assertSee('href="' . route('painel.inicio') . '"', false)
            ->assertDontSee('name="senha"', false);
    }

    public function test_sair_volta_para_a_pagina_inicial(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('inicio'));
    }

    public function test_as_fotos_e_os_arquivos_da_pagina_existem(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('#assets/site/[a-z/-]+\.jpg#', $html, $fotos);
        $this->assertGreaterThan(15, count(array_unique($fotos[0])));
        foreach (array_unique($fotos[0]) as $foto) {
            $this->assertFileExists(public_path($foto));
        }
        $this->assertFileExists(public_path('assets/site.css'));
        $this->assertFileExists(public_path('assets/site.js'));
    }
}
