<?php

namespace Tests\Feature;

use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Alertas\AlertSender;
use App\Services\Alertas\TwilioWhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTwilioTest extends TestCase
{
    use RefreshDatabase;

    private const SID = 'ACteste00000000000000000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.driver' => 'twilio',
            'services.twilio.account_sid' => self::SID,
            'services.twilio.auth_token' => 'token-de-teste',
            'services.twilio.whatsapp_from' => 'whatsapp:+14155238886',
        ]);

        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
    }

    public function test_driver_twilio_usa_o_envio_do_twilio(): void
    {
        $this->assertInstanceOf(TwilioWhatsAppSender::class, app(AlertSender::class));
    }

    public function test_disparo_envia_a_mensagem_montada_pelo_twilio_e_guarda_o_sid(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM1234567890abcdef', 'status' => 'queued'], 201),
        ]);

        $usuario = User::factory()->recebeAlertas()->create();
        $this->dispararPara($usuario);

        Http::assertSent(function (HttpRequest $req) use ($usuario) {
            return $req->url() === 'https://api.twilio.com/2010-04-01/Accounts/' . self::SID . '/Messages.json'
                && $req->hasHeader('Authorization', 'Basic ' . base64_encode(self::SID . ':token-de-teste'))
                && $req['From'] === 'whatsapp:+14155238886'
                && $req['To'] === 'whatsapp:+' . $usuario->telefone
                && $req['Body'] === 'Aviso: Edital de teste';
        });

        $this->assertDatabaseHas('alert_dispatch_recipients', [
            'user_id' => $usuario->id,
            'status' => 'enviado',
            'wamid' => 'SM1234567890abcdef',
        ]);
        $this->assertSame('sucesso', AlertDispatch::firstOrFail()->status);
    }

    public function test_numero_fora_do_sandbox_vira_falha_explicada(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response([
                'code' => 63015,
                'message' => 'Channel Sandbox can only send messages to phone numbers that have joined the Sandbox',
                'status' => 400,
            ], 400),
        ]);

        $usuario = User::factory()->recebeAlertas()->create();
        $this->dispararPara($usuario);

        $destinatario = AlertDispatchRecipient::where('user_id', $usuario->id)->firstOrFail();
        $this->assertSame('falha', $destinatario->status);
        $this->assertSame(63015, $destinatario->erro_codigo);
        $this->assertStringContainsString('ainda não entrou no sandbox', $destinatario->resultado);
        $this->assertNull($destinatario->wamid);
        $this->assertSame('falha', AlertDispatch::firstOrFail()->status);
    }

    public function test_sem_conexao_vira_falha_reenviavel(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $resultado = app(TwilioWhatsAppSender::class)->send('5531999990001', 'x');

        $this->assertSame('falha', $resultado['status']);
        $this->assertNull($resultado['erro_codigo']);
    }

    public function test_sem_configuracao_nao_chama_o_twilio(): void
    {
        Http::fake();
        config(['services.twilio.auth_token' => null]);

        $resultado = app(TwilioWhatsAppSender::class)->send('5531999990001', 'x');

        $this->assertSame('falha', $resultado['status']);
        $this->assertStringContainsString('TWILIO_AUTH_TOKEN', $resultado['resultado']);
        Http::assertNothingSent();
    }

    private function dispararPara(User $usuario): Postagem
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = Postagem::create([
            'tipo' => 'ministerios_editais',
            'titulo' => 'Edital de teste',
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
