<?php

namespace Tests\Feature;

use App\Models\AlertDispatch;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Alertas\AlertSender;
use App\Services\Alertas\SimulatedWhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReenvioAlertaTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    protected function setUp(): void
    {
        parent::setUp();

        // As contas de homologação nascem com telefone; zera para o teste
        // controlar quem recebe.
        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);

        $this->adm = User::factory()->create(['perfil' => User::ADM]);
    }

    public function test_reenvio_tenta_de_novo_so_quem_falhou(): void
    {
        $ok = User::factory()->recebeAlertas()->create(['nome' => 'Recebeu']);
        $falhou = User::factory()->recebeAlertas()->create(['nome' => 'Falhou']);
        $semAutorizacao = User::factory()->comTelefone()->create(['nome' => 'Sem autorização']);

        $disparo = $this->disparar(falhamPara: [$falhou->telefone]);

        $this->assertSame('parcial', $disparo->status);
        $this->assertSame(1, $disparo->total_falhas);

        // Agora o provedor responde: o reenvio entrega.
        $enviadosPara = $this->usarProvedor(falhamPara: []);

        $this->actingAs($this->adm)
            ->from(route('alertas.index'))
            ->post(route('alertas.reenviar', $disparo))
            ->assertRedirect(route('alertas.index'))
            ->assertSessionHas('ok');

        // Só quem falhou foi tentado de novo.
        $this->assertSame([$falhou->telefone], $enviadosPara->getArrayCopy());

        $disparo->refresh();
        $this->assertSame('sucesso', $disparo->status);
        $this->assertSame(2, $disparo->total_enviados);
        $this->assertSame(0, $disparo->total_falhas);

        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $falhou->id, 'status' => 'enviado', 'tentativas' => 2,
        ]);
        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $ok->id, 'status' => 'enviado', 'tentativas' => 1,
        ]);
        // Quem não autorizou não teve tentativa nenhuma.
        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $semAutorizacao->id, 'status' => 'ignorado', 'tentativas' => 0,
        ]);
    }

    public function test_reenvio_nao_manda_para_quem_desautorizou_depois(): void
    {
        $falhou = User::factory()->recebeAlertas()->create();
        User::factory()->recebeAlertas()->create();

        $disparo = $this->disparar(falhamPara: [$falhou->telefone]);

        $falhou->forceFill(['alerta_consentimento_em' => null])->save();
        $enviadosPara = $this->usarProvedor(falhamPara: []);

        $this->actingAs($this->adm)->post(route('alertas.reenviar', $disparo));

        $this->assertCount(0, $enviadosPara);
        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $falhou->id, 'status' => 'ignorado',
        ]);
        $this->assertSame('sucesso', $disparo->fresh()->status);
    }

    public function test_reenvio_que_falha_de_novo_continua_pendente(): void
    {
        $falhou = User::factory()->recebeAlertas()->create();

        $disparo = $this->disparar(falhamPara: [$falhou->telefone]);
        $this->assertSame('falha', $disparo->status);

        $this->usarProvedor(falhamPara: [$falhou->telefone]);
        $this->actingAs($this->adm)->post(route('alertas.reenviar', $disparo));

        $disparo->refresh();
        $this->assertSame('falha', $disparo->status);
        $this->assertSame(1, $disparo->total_falhas);
        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $falhou->id, 'status' => 'falha', 'tentativas' => 2,
        ]);
    }

    public function test_disparo_sem_falha_nao_pode_ser_reenviado(): void
    {
        User::factory()->recebeAlertas()->create();
        $disparo = $this->disparar(falhamPara: []);

        $this->actingAs($this->adm)
            ->post(route('alertas.reenviar', $disparo))
            ->assertStatus(409);
    }

    public function test_cliente_nao_reenvia(): void
    {
        $falhou = User::factory()->recebeAlertas()->create();
        $disparo = $this->disparar(falhamPara: [$falhou->telefone]);

        $this->actingAs(User::factory()->create())
            ->post(route('alertas.reenviar', $disparo))
            ->assertForbidden();
    }

    public function test_central_mostra_medidor_e_botao_de_reenvio(): void
    {
        $falhou = User::factory()->recebeAlertas()->create();
        User::factory()->recebeAlertas()->count(3)->create();

        $disparo = $this->disparar(falhamPara: [$falhou->telefone]);

        $this->actingAs($this->adm)
            ->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('Taxa de entrega')
            ->assertSeeInOrder(['75', '%'])               // 3 de 4 entregues
            ->assertSee('3 de 4')
            ->assertSee('action="' . route('alertas.reenviar', $disparo) . '"', false)
            ->assertSee('Reenviar 1 falha');
    }

    public function test_envio_simulado_falha_na_porcentagem_configurada(): void
    {
        $sender = new SimulatedWhatsAppSender();

        config(['services.alertas.simulacao_falha' => 100]);
        $this->assertSame('falha', $sender->send('5531999990001', 'x')['status']);

        config(['services.alertas.simulacao_falha' => 0]);
        $this->assertSame('enviado', $sender->send('5531999990001', 'x')['status']);
    }

    // ------------------------------------------------------------- apoio

    /** Faz um disparo com um provedor que falha para os números dados. */
    private function disparar(array $falhamPara): AlertDispatch
    {
        $this->usarProvedor($falhamPara);

        $postagem = Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => 'Publicação para teste',
            'texto' => 'Conteúdo',
            'data_publicacao' => '2026-09-30',
            'mes_referencia' => '2026-09',
            'situacao' => 'publicado',
            'alerta_enviado' => false,
            'user_id' => $this->adm->id,
        ]);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso: {{titulo}}']);

        $this->actingAs($this->adm)
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id]);

        return AlertDispatch::where('postagem_id', $postagem->id)->firstOrFail();
    }

    /**
     * Troca o provedor por um falso: falha para os números dados e anota
     * para quem tentou enviar.
     */
    private function usarProvedor(array $falhamPara): \ArrayObject
    {
        $enviadosPara = new \ArrayObject();

        $this->app->instance(AlertSender::class, new class($falhamPara, $enviadosPara) implements AlertSender {
            public function __construct(private array $falhamPara, private \ArrayObject $log) {}

            public function send(string $telefone, string $mensagem, array $parametros = []): array
            {
                $this->log[] = $telefone;

                return in_array($telefone, $this->falhamPara, true)
                    ? ['status' => 'falha', 'resultado' => 'Recusado pelo provedor.']
                    : ['status' => 'enviado', 'resultado' => 'Entregue.'];
            }
        });

        return $enviadosPara;
    }
}
