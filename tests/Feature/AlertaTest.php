<?php

namespace Tests\Feature;

use App\Models\AlertContact;
use App\Models\AlertList;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_nao_acessa_gestao_de_alertas(): void
    {
        $cliente = User::factory()->create(['perfil' => User::CLIENT]);

        $this->actingAs($cliente)
            ->get(route('alertas.index'))
            ->assertForbidden();
    }

    public function test_disparo_simulado_envia_apenas_para_contatos_com_consentimento(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);

        $postagem = Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => 'Publicação para teste',
            'texto' => 'Conteúdo',
            'data_publicacao' => '2026-09-30',
            'mes_referencia' => '2026-09',
            'situacao' => 'publicado',
            'alerta_enviado' => false,
            'user_id' => $adm->id,
        ]);

        $modelo = AlertTemplate::create([
            'nome' => 'Teste',
            'mensagem' => 'Aviso: {{titulo}}',
        ]);

        $lista = AlertList::create(['nome' => 'Teste']);

        $comConsentimento = AlertContact::create([
            'nome' => 'Com consentimento',
            'telefone' => '31999990001',
            'consentimento' => true,
        ]);

        $semConsentimento = AlertContact::create([
            'nome' => 'Sem consentimento',
            'telefone' => '31999990002',
            'consentimento' => false,
        ]);

        $lista->contatos()->sync([$comConsentimento->id, $semConsentimento->id]);

        $this->actingAs($adm)
            ->post(route('alertas.disparar', $postagem), [
                'alert_template_id' => $modelo->id,
                'alert_list_id' => $lista->id,
            ])
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
            'nome' => 'Sem consentimento',
            'status' => 'ignorado',
        ]);
    }

    public function test_publicacao_nao_permite_segundo_disparo(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => 'Já alertada',
            'texto' => 'Conteúdo',
            'data_publicacao' => '2026-09-30',
            'mes_referencia' => '2026-09',
            'situacao' => 'publicado',
            'alerta_enviado' => true,
            'user_id' => $adm->id,
        ]);

        $this->actingAs($adm)
            ->get(route('alertas.enviar', $postagem))
            ->assertStatus(409);
    }
}
