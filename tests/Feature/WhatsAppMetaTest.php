<?php

namespace Tests\Feature;

use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Alertas\AlertSender;
use App\Services\Alertas\MetaWhatsAppSender;
use App\Services\Alertas\SimulatedWhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppMetaTest extends TestCase
{
    use RefreshDatabase;

    private const SEGREDO = 'segredo-do-app-de-teste';
    private const VERIFY = 'verify-token-de-teste-1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.access_token' => 'token-de-teste',
            'services.whatsapp.phone_number_id' => '111222333444555',
            'services.whatsapp.graph_version' => 'v23.0',
            'services.whatsapp.verify_token' => self::VERIFY,
            'services.whatsapp.app_secret' => self::SEGREDO,
            'services.whatsapp.template' => 'nova_publicacao',
            'services.whatsapp.template_lang' => 'pt_BR',
        ]);

        // Processa os eventos na hora, em vez de depois da resposta.
        $this->withoutDefer();

        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
    }

    // ------------------------------------------------------------ verificação (GET)

    public function test_verificacao_com_token_certo_devolve_o_challenge(): void
    {
        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=' . self::VERIFY . '&hub.challenge=1158201444')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('1158201444');
    }

    public function test_verificacao_com_token_errado_da_403(): void
    {
        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=errado&hub.challenge=123')
            ->assertForbidden();
    }

    public function test_verificacao_sem_token_configurado_da_403(): void
    {
        config(['services.whatsapp.verify_token' => null]);

        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=123')
            ->assertForbidden();
    }

    // -------------------------------------------------------------- eventos (POST)

    public function test_post_com_assinatura_valida_da_200(): void
    {
        $this->postarWebhook(['object' => 'whatsapp_business_account', 'entry' => []])
            ->assertOk()
            ->assertSeeText('EVENT_RECEIVED');
    }

    public function test_post_com_assinatura_invalida_ou_ausente_da_403(): void
    {
        $corpo = json_encode(['entry' => []]);

        $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $corpo, 'outro-segredo'),
        ], $corpo)->assertForbidden();

        $this->call('POST', '/webhook/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json'], $corpo)
            ->assertForbidden();
    }

    public function test_status_entregue_e_lido_atualizam_pelo_wamid_sem_regredir(): void
    {
        $destinatario = $this->destinatarioEnviado('wamid.ABC');

        $this->postarWebhook($this->eventoStatus('wamid.ABC', 'delivered'))->assertOk();
        $this->assertSame('delivered', $destinatario->fresh()->status_meta);
        $this->assertSame('Entregue no WhatsApp.', $destinatario->fresh()->resultado);

        $this->postarWebhook($this->eventoStatus('wamid.ABC', 'read'));
        // Um "delivered" atrasado não desfaz o "read".
        $this->postarWebhook($this->eventoStatus('wamid.ABC', 'delivered'));

        $destinatario->refresh();
        $this->assertSame('read', $destinatario->status_meta);
        $this->assertSame('enviado', $destinatario->status);
    }

    public function test_status_failed_130497_vira_falha_e_libera_o_reenvio(): void
    {
        $destinatario = $this->destinatarioEnviado('wamid.XYZ');

        $this->postarWebhook($this->eventoStatus('wamid.XYZ', 'failed', [[
            'code' => 130497,
            'title' => 'Business account is restricted from messaging users in this country.',
            'message' => 'Business account is restricted from messaging users in this country.',
            'error_data' => ['details' => 'Restricted country'],
        ]]))->assertOk();

        $destinatario->refresh();
        $this->assertSame('falha', $destinatario->status);
        $this->assertSame('failed', $destinatario->status_meta);
        $this->assertSame(130497, $destinatario->erro_codigo);
        $this->assertSame('Restricted country', $destinatario->erro_mensagem);
        $this->assertStringContainsString('130497', $destinatario->resultado);

        $disparo = $destinatario->disparo->fresh();
        $this->assertSame(1, $disparo->total_falhas);
        $this->assertTrue($disparo->temFalhas());
    }

    public function test_status_de_wamid_desconhecido_e_ignorado(): void
    {
        $this->postarWebhook($this->eventoStatus('wamid.NAOEXISTE', 'delivered'))->assertOk();
        $this->assertDatabaseCount('alert_dispatch_recipients', 0);
    }

    // ------------------------------------------------------------------- envio

    public function test_disparo_envia_template_pela_meta_e_guarda_o_wamid(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '5531999990001', 'wa_id' => '5531999990001']],
                'messages' => [['id' => 'wamid.HBgNNTUzMTk5OTk5MDAwMRUCABEYEjA=']],
            ]),
        ]);

        $usuario = User::factory()->recebeAlertas()->create();
        $postagem = $this->dispararPara($usuario);

        Http::assertSent(function (HttpRequest $req) use ($usuario, $postagem) {
            return $req->url() === 'https://graph.facebook.com/v23.0/111222333444555/messages'
                && $req->hasHeader('Authorization', 'Bearer token-de-teste')
                && $req['type'] === 'template'
                && $req['to'] === $usuario->telefone
                && $req['template']['name'] === 'nova_publicacao'
                && $req['template']['language']['code'] === 'pt_BR'
                // Quebra de linha e espaço duplo do título saem: a Meta recusa.
                && array_column($req['template']['components'][0]['parameters'], 'text') === [
                    'Edital de teste',
                    $postagem->rotuloTipo(),
                    '30/09/2026',
                ];
        });

        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $usuario->id,
            'status' => 'enviado',
            'wamid' => 'wamid.HBgNNTUzMTk5OTk5MDAwMRUCABEYEjA=',
        ]);
    }

    public function test_erro_da_api_vira_falha_com_codigo_e_mensagem(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => '(#132001) Template name does not exist in the translation',
                    'type' => 'OAuthException',
                    'code' => 132001,
                    'error_data' => ['details' => 'template name (nova_publicacao) does not exist in pt_BR'],
                    'fbtrace_id' => 'AbCdEf',
                ],
            ], 400),
        ]);

        $usuario = User::factory()->recebeAlertas()->create();
        $this->dispararPara($usuario);

        $destinatario = AlertDispatchRecipient::where('user_id', $usuario->id)->firstOrFail();
        $this->assertSame('falha', $destinatario->status);
        $this->assertSame(132001, $destinatario->erro_codigo);
        $this->assertSame('template name (nova_publicacao) does not exist in pt_BR', $destinatario->erro_mensagem);
        $this->assertNull($destinatario->wamid);
        $this->assertSame('falha', AlertDispatch::firstOrFail()->status);
    }

    public function test_sem_conexao_vira_falha_reenviavel(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $resultado = app(MetaWhatsAppSender::class)->send('5531999990001', 'x', ['a', 'b', 'c']);

        $this->assertSame('falha', $resultado['status']);
        $this->assertNull($resultado['erro_codigo']);
    }

    public function test_sem_configuracao_nao_chama_a_meta(): void
    {
        Http::fake();
        config(['services.whatsapp.access_token' => null]);

        $resultado = app(MetaWhatsAppSender::class)->send('5531999990001', 'x');

        $this->assertSame('falha', $resultado['status']);
        $this->assertStringContainsString('WHATSAPP_ACCESS_TOKEN', $resultado['resultado']);
        Http::assertNothingSent();
    }

    public function test_driver_escolhe_a_implementacao(): void
    {
        $this->assertInstanceOf(MetaWhatsAppSender::class, app(AlertSender::class));

        config(['services.whatsapp.driver' => 'simulado']);
        $this->assertInstanceOf(SimulatedWhatsAppSender::class, app(AlertSender::class));
    }

    // ------------------------------------------------------------------- apoio

    private function postarWebhook(array $payload)
    {
        $corpo = json_encode($payload);

        return $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $corpo, self::SEGREDO),
        ], $corpo);
    }

    private function eventoStatus(string $wamid, string $status, array $erros = []): array
    {
        $evento = ['id' => $wamid, 'status' => $status, 'timestamp' => '1791400000', 'recipient_id' => '5531999990001'];
        if ($erros) {
            $evento['errors'] = $erros;
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['messaging_product' => 'whatsapp', 'statuses' => [$evento]],
                ]],
            ]],
        ];
    }

    private function destinatarioEnviado(string $wamid): AlertDispatchRecipient
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => $wamid]]])]);

        $usuario = User::factory()->recebeAlertas()->create();
        $this->dispararPara($usuario);

        return AlertDispatchRecipient::where('wamid', $wamid)->firstOrFail();
    }

    private function dispararPara(User $usuario): Postagem
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => "Edital  de\nteste",
            'texto' => 'Conteúdo',
            'data_publicacao' => '2026-09-30',
            'mes_referencia' => '2026-09',
            'situacao' => 'publicado',
            'alerta_enviado' => false,
            'user_id' => $adm->id,
        ]);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso: {{titulo}}']);

        $this->actingAs($adm)
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id])
            ->assertRedirect(route('alertas.index'));

        return $postagem;
    }
}
