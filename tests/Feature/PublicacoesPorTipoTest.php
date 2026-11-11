<?php

namespace Tests\Feature;

use App\Models\Postagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacoesPorTipoTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    protected function setUp(): void
    {
        parent::setUp();

        // As contas iniciais criam publicações de exemplo; o teste controla as suas.
        Postagem::query()->delete();
        $this->adm = User::factory()->create(['perfil' => User::ADM]);

        $this->publicar('ministerios_editais', 'Edital de saneamento', '2026-09-10');
        $this->publicar('ministerios_editais', 'Edital de iluminação', '2026-10-02');
        $this->publicar('ministerios_editais', 'Edital em preparação', '2026-10-05', 'rascunho');
        $this->publicar('financiamentos', 'Linha do BNDES', '2026-09-20');
    }

    public function test_primeiro_acesso_abre_a_escolha_de_tipo(): void
    {
        $this->actingAs($this->adm)->get(route('postagens.index'))
            ->assertOk()
            ->assertViewIs('postagens.tipos')
            ->assertSee('Escolha o tipo de publicação')
            ->assertSee('Ministérios e autarquias — Editais')
            ->assertSee('3 publicações · 1 rascunho')
            ->assertSee('Mais recente em 05/10/2026')
            ->assertSee('Nenhuma publicação ainda')
            ->assertSee(route('postagens.index', ['tipo' => 'financiamentos']), false)
            // Sem filtro rápido de tipos nem a listagem misturada.
            ->assertDontSee('name="tipos[]"', false)
            ->assertDontSee('Linha do BNDES');
    }

    public function test_listagem_mostra_so_o_tipo_escolhido_e_sem_filtro_rapido(): void
    {
        $this->actingAs($this->adm)->get(route('postagens.index', ['tipo' => 'ministerios_editais']))
            ->assertOk()
            ->assertViewIs('postagens.index')
            ->assertSee('Edital de saneamento')
            ->assertSee('Edital em preparação')
            ->assertDontSee('Linha do BNDES')
            ->assertDontSee('name="tipos[]"', false)
            // Troca de tipo: janela na própria página, com o tipo aberto marcado.
            ->assertSee('id="modal-tipos"', false)
            ->assertSee('data-abre="modal-tipos"', false)
            ->assertSee('aria-current="true"', false)
            ->assertSee(route('postagens.create', ['tipo' => 'ministerios_editais']), false);
    }

    public function test_depois_do_primeiro_acesso_volta_direto_ao_ultimo_tipo(): void
    {
        $this->actingAs($this->adm)->get(route('postagens.index', ['tipo' => 'financiamentos']))->assertOk();

        $this->actingAs($this->adm)->get(route('postagens.index'))
            ->assertRedirect(route('postagens.index', ['tipo' => 'financiamentos']));

        // Sem JavaScript, o botão de troca leva de volta à escolha.
        $this->actingAs($this->adm)->get(route('postagens.index', ['escolher' => 1]))
            ->assertOk()
            ->assertViewIs('postagens.tipos')
            ->assertSee('Aberto agora');
    }

    public function test_ao_entrar_de_novo_no_sistema_a_escolha_aparece_outra_vez(): void
    {
        $usuario = User::factory()->create(['perfil' => User::ADM, 'password' => 'senha-segura-1']);

        $this->post(route('login.entrar'), ['usuario' => $usuario->usuario, 'senha' => 'senha-segura-1']);
        $this->get(route('postagens.index', ['tipo' => 'financiamentos']))->assertOk();
        $this->get(route('postagens.index'))->assertRedirect(route('postagens.index', ['tipo' => 'financiamentos']));

        $this->post(route('logout'));
        $this->post(route('login.entrar'), ['usuario' => $usuario->usuario, 'senha' => 'senha-segura-1']);

        $this->get(route('postagens.index'))->assertOk()->assertViewIs('postagens.tipos');
    }

    public function test_tipo_invalido_volta_para_a_escolha(): void
    {
        $this->actingAs($this->adm)->get(route('postagens.index', ['tipo' => 'nao_existe']))
            ->assertRedirect(route('postagens.index', ['escolher' => 1]));
    }

    public function test_cliente_ve_e_conta_so_as_publicadas(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->get(route('postagens.index'))
            ->assertSee('2 publicações')
            ->assertDontSee('rascunho');

        $this->actingAs($cliente)->get(route('postagens.index', ['tipo' => 'ministerios_editais']))
            ->assertSee('Edital de saneamento')
            ->assertDontSee('Edital em preparação');
    }

    public function test_busca_procura_dentro_do_tipo_aberto(): void
    {
        $this->publicar('financiamentos', 'Edital de crédito rural', '2026-09-25');

        $this->actingAs($this->adm)->get(route('postagens.index', ['tipo' => 'ministerios_editais', 'busca' => 'Edital']))
            ->assertSee('Edital de saneamento')
            ->assertDontSee('Edital de crédito rural')
            ->assertSee('name="tipo" value="ministerios_editais"', false);
    }

    public function test_nova_publicacao_ja_vem_no_tipo_da_listagem(): void
    {
        $html = $this->actingAs($this->adm)->get(route('postagens.create', ['tipo' => 'financiamentos']))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="financiamentos"\s+selected/', $html);
        $this->assertSame(1, preg_match_all('/<option value="[a-z_]+"\s+selected/', $html));
    }

    public function test_excluir_e_voltar_levam_para_a_listagem_do_tipo(): void
    {
        $postagem = Postagem::where('titulo', 'Linha do BNDES')->first();

        $this->actingAs($this->adm)->get(route('postagens.show', $postagem))
            ->assertSee('href="' . route('postagens.index', ['tipo' => 'financiamentos']) . '"', false);

        $this->actingAs($this->adm)->delete(route('postagens.destroy', $postagem))
            ->assertRedirect(route('postagens.index', ['tipo' => 'financiamentos']));
    }

    private function publicar(string $tipo, string $titulo, string $data, string $situacao = 'publicado'): Postagem
    {
        return Postagem::create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'texto' => '<p>Texto</p>',
            'data_publicacao' => $data,
            'mes_referencia' => substr($data, 0, 7),
            'situacao' => $situacao,
            'user_id' => $this->adm->id,
        ]);
    }
}
