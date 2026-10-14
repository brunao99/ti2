<?php

namespace Tests\Feature;

use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A migration de dados iniciais dá telefone às contas de homologação.
        // Zera aqui para cada teste controlar exatamente quem recebe.
        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
    }

    public function test_client_nao_acessa_gestao_de_alertas(): void
    {
        $cliente = User::factory()->create(['perfil' => User::CLIENT]);

        $this->actingAs($cliente)
            ->get(route('alertas.index'))
            ->assertForbidden();
    }

    public function test_disparo_vai_so_para_quem_autorizou(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso: {{titulo}}']);

        $autorizou = User::factory()->recebeAlertas()->create(['nome' => 'Autorizou']);
        $naoAutorizou = User::factory()->comTelefone()->create(['nome' => 'Não autorizou']);
        $semTelefone = User::factory()->create(['nome' => 'Sem telefone']);
        $inativo = User::factory()->recebeAlertas()->create(['nome' => 'Inativo', 'ativo' => false]);

        $this->actingAs($adm)
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id])
            ->assertRedirect(route('alertas.index'));

        $this->assertTrue($postagem->fresh()->alerta_enviado);

        $this->assertDatabaseHas('alert_dispatches', [
            'postagem_id' => $postagem->id,
            'total_destinatarios' => 1,
            'total_enviados' => 1,
            'total_falhas' => 0,
            'status' => 'sucesso',
        ]);

        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $autorizou->id,
            'telefone' => $autorizou->telefone,
            'status' => 'enviado',
        ]);
        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $naoAutorizou->id,
            'status' => 'ignorado',
        ]);

        // Sem telefone ou com conta inativa nem entram no disparo.
        $this->assertDatabaseMissing('alert_dispatch_recipients', ['user_id' => $semTelefone->id]);
        $this->assertDatabaseMissing('alert_dispatch_recipients', ['user_id' => $inativo->id]);
    }

    public function test_disparo_sem_ninguem_autorizado_nao_envia(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso']);

        User::factory()->comTelefone()->create();

        $this->actingAs($adm)
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id])
            ->assertSessionHasErrors('alerta');

        $this->assertFalse($postagem->fresh()->alerta_enviado);
        $this->assertDatabaseCount('alert_dispatches', 0);
    }

    public function test_publicacao_nao_permite_segundo_disparo(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm, ['alerta_enviado' => true]);

        $this->actingAs($adm)
            ->get(route('alertas.enviar', $postagem))
            ->assertStatus(409);
    }

    public function test_envio_pela_janela_recebe_so_o_formulario(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm);
        User::factory()->recebeAlertas()->create(['nome' => 'Prefeitura Autorizada']);

        // Pedido do JavaScript: só o formulário, sem o layout do painel.
        $this->actingAs($adm)
            ->get(route('alertas.enviar', $postagem), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('data-envio', false)
            ->assertSee('data-fecha', false)
            ->assertSee('Prefeitura Autorizada');

        // Acesso direto: página inteira, que é a reserva sem JavaScript.
        $this->actingAs($adm)
            ->get(route('alertas.enviar', $postagem))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('data-envio', false)
            ->assertDontSee('data-fecha', false);
    }

    public function test_lista_de_publicacoes_abre_o_envio_em_janela(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm);

        $this->actingAs($adm)
            ->get(route('postagens.index'))
            ->assertOk()
            ->assertSee('href="' . route('alertas.enviar', $postagem) . '" data-modal-envio', false);
    }

    public function test_erro_no_disparo_volta_para_a_pagina_de_envio(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->publicacao($adm);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso']);

        // Vindo da janela, a página anterior é a lista; o erro tem de voltar
        // para o envio, onde faz sentido.
        $this->actingAs($adm)
            ->from(route('postagens.index'))
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id])
            ->assertRedirect(route('alertas.enviar', $postagem))
            ->assertSessionHasErrors('alerta');
    }

    // ------------------------------------------------------------ Minha conta

    public function test_usuario_autoriza_e_desautoriza_os_alertas(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->put(route('conta.update'), ['telefone' => '(31) 99999-0001', 'autorizo' => '1'])
            ->assertRedirect(route('conta.edit'));

        $cliente->refresh();
        $this->assertSame('5531999990001', $cliente->telefone);
        $this->assertNotNull($cliente->alerta_consentimento_em);

        $this->actingAs($cliente)
            ->put(route('conta.update'), ['telefone' => '(31) 99999-0001'])
            ->assertRedirect(route('conta.edit'));

        $cliente->refresh();
        $this->assertSame('5531999990001', $cliente->telefone);
        $this->assertNull($cliente->alerta_consentimento_em);
    }

    public function test_autorizar_exige_telefone_valido(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)
            ->put(route('conta.update'), ['telefone' => '', 'autorizo' => '1'])
            ->assertSessionHasErrors('telefone');

        $this->actingAs($cliente)
            ->put(route('conta.update'), ['telefone' => '1234', 'autorizo' => '1'])
            ->assertSessionHasErrors('telefone');

        $this->assertNull($cliente->fresh()->alerta_consentimento_em);
    }

    // --------------------------------------------------------- cadastro (ADM)

    public function test_adm_nao_consegue_marcar_consentimento_pelo_usuario(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $cliente = User::factory()->create();

        $this->actingAs($adm)->put(route('usuarios.update', $cliente), [
            'nome' => $cliente->nome,
            'usuario' => $cliente->usuario,
            'email' => $cliente->email,
            'perfil' => User::CLIENT,
            'contrato' => '225/2025',
            'ativo' => '1',
            'telefone' => '31 99999-0003',
            'alerta_consentimento_em' => now()->toDateTimeString(),
        ])->assertRedirect(route('usuarios.index'));

        $cliente->refresh();
        $this->assertSame('5531999990003', $cliente->telefone);
        $this->assertNull($cliente->alerta_consentimento_em);
    }

    public function test_adm_trocar_o_numero_cancela_a_autorizacao(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $cliente = User::factory()->recebeAlertas()->create();

        $dados = [
            'nome' => $cliente->nome,
            'usuario' => $cliente->usuario,
            'email' => $cliente->email,
            'perfil' => User::CLIENT,
            'contrato' => '225/2025',
            'ativo' => '1',
        ];

        // Mesmo número, só com outra máscara: a autorização continua.
        $this->actingAs($adm)->put(route('usuarios.update', $cliente),
            $dados + ['telefone' => $cliente->telefoneFormatado()]);
        $this->assertNotNull($cliente->fresh()->alerta_consentimento_em);

        $this->actingAs($adm)->put(route('usuarios.update', $cliente),
            $dados + ['telefone' => '(31) 98888-0000']);
        $this->assertNull($cliente->fresh()->alerta_consentimento_em);
    }

    private function publicacao(User $autor, array $extra = []): Postagem
    {
        return Postagem::create($extra + [
            'tipo' => 'ministerios_editais',
            'titulo' => 'Publicação para teste',
            'texto' => 'Conteúdo',
            'data_publicacao' => '2026-09-30',
            'mes_referencia' => '2026-09',
            'situacao' => 'publicado',
            'alerta_enviado' => false,
            'user_id' => $autor->id,
        ]);
    }
}
