<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContaTest extends TestCase
{
    use RefreshDatabase;

    public function test_minha_conta_mostra_os_dados_do_usuario(): void
    {
        $cliente = User::factory()->comTelefone()->create([
            'nome' => 'Prefeitura de Teste',
            'usuario' => 'pref.teste',
            'email' => 'pref@teste.gov.br',
        ]);

        $this->actingAs($cliente)
            ->get(route('conta.edit'))
            ->assertOk()
            ->assertSee('Prefeitura de Teste')
            ->assertSee('pref.teste')
            ->assertSee('pref@teste.gov.br')
            ->assertSee($cliente->telefoneFormatado())
            ->assertSee('Cliente')
            ->assertSee('Sair da conta')
            ->assertSee('data-tema="dark"', false);
    }

    public function test_tema_e_sair_ficam_so_em_minha_conta(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);

        // O nome na barra lateral leva a Minha conta.
        $this->actingAs($adm)
            ->get(route('painel.inicio'))
            ->assertOk()
            ->assertSee('href="' . route('conta.edit') . '"', false)
            ->assertDontSee('data-tema', false)
            ->assertDontSee('class="toggle"', false)
            ->assertDontSee('action="' . route('logout') . '"', false);

        $this->actingAs($adm)
            ->get(route('painel.mais'))
            ->assertOk()
            ->assertDontSee('action="' . route('logout') . '"', false);
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_listagem_de_usuarios_mostra_o_telefone(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $cliente = User::factory()->comTelefone()->create();

        $this->actingAs($adm)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('Telefone')
            ->assertSee($cliente->telefoneFormatado(comDdi: false));
    }

    public function test_css_e_js_saem_com_versao_no_endereco(): void
    {
        // Sem a versão, o navegador reaproveitava o CSS antigo depois de um
        // deploy e a tela nova aparecia sem estilo.
        $this->actingAs(User::factory()->create())
            ->get(route('conta.edit'))
            ->assertOk()
            ->assertSee('assets/style.css?v=', false)
            ->assertSee('assets/app.js?v=', false);
    }
}
