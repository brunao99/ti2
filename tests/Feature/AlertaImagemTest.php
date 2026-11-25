<?php

namespace Tests\Feature;

use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Alertas\ImagemAlerta;
use App\Support\ResumoAlerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AlertaImagemTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ resumo padrão

    public function test_resumo_padrao_tira_o_html_e_corta_no_fim_de_uma_frase(): void
    {
        $html = '<p>Primeira frase do texto.</p><p>Segunda <b>frase</b> aqui. Terceira frase bem mais longa que não cabe no limite.</p>';

        $this->assertSame('Primeira frase do texto. Segunda frase aqui.', ResumoAlerta::padrao($html, 50));
        $this->assertSame(ResumoAlerta::textoPuro($html), ResumoAlerta::padrao($html));
    }

    public function test_primeira_frase_longa_corta_na_ultima_palavra_com_reticencias(): void
    {
        $resumo = ResumoAlerta::padrao(str_repeat('palavra ', 100), 30);

        $this->assertSame('palavra palavra palavra…', $resumo);
    }

    public function test_publicacao_usa_o_resumo_proprio_ou_o_padrao(): void
    {
        $postagem = new Postagem(['texto' => '<p>Texto da publicação.</p>']);
        $this->assertSame('Texto da publicação.', $postagem->resumoParaAlerta());

        $postagem->resumo_alerta = 'Resumo escrito pela equipe.';
        $this->assertSame('Resumo escrito pela equipe.', $postagem->resumoParaAlerta());
    }

    // --------------------------------------------------------------- formulário

    public function test_formulario_traz_o_campo_preenchido_com_o_padrao(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->postagem($adm, ['texto' => '<p>Prazo até 11 de dezembro de 2026.</p>']);

        $this->actingAs($adm)->get(route('postagens.edit', $postagem))
            ->assertOk()
            ->assertSee('name="resumo_alerta"', false)
            ->assertSee('data-auto="1"', false)
            ->assertSee('Prazo até 11 de dezembro de 2026.');
    }

    public function test_resumo_editado_e_gravado_e_vazio_volta_ao_padrao(): void
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);

        $this->actingAs($adm)->post(route('postagens.store'), $this->campos(['resumo_alerta' => '  Resumo   próprio. ']));
        $this->assertSame('Resumo próprio.', Postagem::latest('id')->first()->resumo_alerta);

        $this->actingAs($adm)->post(route('postagens.store'), $this->campos(['resumo_alerta' => '']));
        $this->assertNull(Postagem::latest('id')->first()->resumo_alerta);
    }

    // ------------------------------------------------------------------ imagem

    public function test_imagem_sem_assinatura_e_recusada(): void
    {
        $postagem = $this->postagem(User::factory()->create(['perfil' => User::ADM]));

        $this->get(route('alertas.imagem', $postagem))->assertForbidden();
    }

    public function test_imagem_assinada_e_um_png_no_formato_de_story(): void
    {
        $postagem = $this->postagem(User::factory()->create(['perfil' => User::ADM]));
        $url = URL::temporarySignedRoute('alertas.imagem', now()->addHour(), ['postagem' => $postagem->id]);

        $resposta = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

        [$largura, $altura] = getimagesizefromstring($resposta->getContent());
        $this->assertSame([900, 1600], [$largura, $altura]);
    }

    public function test_previa_da_imagem_exige_login(): void
    {
        $postagem = $this->postagem(User::factory()->create(['perfil' => User::ADM]));

        $this->get(route('alertas.imagem.previa', $postagem))->assertRedirect(route('login'));
    }

    public function test_endereco_da_imagem_so_existe_com_alerta_imagem_ligado(): void
    {
        $postagem = $this->postagem(User::factory()->create(['perfil' => User::ADM]));

        config(['services.alertas.imagem' => false]);
        $this->assertNull(ImagemAlerta::url($postagem));

        config(['services.alertas.imagem' => true]);
        $this->assertStringContainsString('signature=', ImagemAlerta::url($postagem));
    }

    // ------------------------------------------------------------ envio Twilio

    public function test_twilio_manda_a_imagem_com_a_mensagem_de_legenda_quando_ligado(): void
    {
        $this->configurarTwilio(imagem: true);
        $usuario = User::factory()->recebeAlertas()->create();
        $postagem = $this->disparar();

        Http::assertSent(fn (HttpRequest $req) => $req['To'] === 'whatsapp:+' . $usuario->telefone
            && $req['Body'] === 'Aviso: Edital de teste'
            && str_contains($req['MediaUrl'] ?? '', '/alertas/imagem/' . $postagem->id)
            && str_contains($req['MediaUrl'] ?? '', 'signature='));
    }

    public function test_twilio_sem_alerta_imagem_manda_so_o_texto(): void
    {
        $this->configurarTwilio(imagem: false);
        User::factory()->recebeAlertas()->create();
        $this->disparar();

        Http::assertSent(fn (HttpRequest $req) => $req['Body'] === 'Aviso: Edital de teste' && ! isset($req['MediaUrl']));
    }

    // ------------------------------------------------------------------- apoio

    private function configurarTwilio(bool $imagem): void
    {
        config([
            'services.whatsapp.driver' => 'twilio',
            'services.twilio.account_sid' => 'ACteste00000000000000000000000000',
            'services.twilio.auth_token' => 'token-de-teste',
            'services.twilio.whatsapp_from' => 'whatsapp:+17372508034',
            'services.alertas.imagem' => $imagem,
        ]);
        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);
    }

    private function disparar(): Postagem
    {
        $adm = User::factory()->create(['perfil' => User::ADM]);
        $postagem = $this->postagem($adm);
        $modelo = AlertTemplate::create(['nome' => 'Teste', 'mensagem' => 'Aviso: {{titulo}}']);

        $this->actingAs($adm)
            ->post(route('alertas.disparar', $postagem), ['alert_template_id' => $modelo->id])
            ->assertRedirect(route('alertas.index'));

        return $postagem;
    }

    private function postagem(User $autor, array $campos = []): Postagem
    {
        return Postagem::create($campos + [
            'tipo' => 'ministerios_editais',
            'titulo' => 'Edital de teste',
            'texto' => '<p>Os municípios têm até 11 de dezembro de 2026 para enviar as indicações.</p>',
            'data_publicacao' => '2026-11-25',
            'mes_referencia' => '2026-11',
            'situacao' => 'publicado',
            'alerta_enviado' => false,
            'user_id' => $autor->id,
        ]);
    }

    private function campos(array $extra = []): array
    {
        return $extra + [
            'tipo' => 'ministerios_editais',
            'titulo' => 'Nova publicação',
            'texto' => '<p>Texto.</p>',
            'data_publicacao' => '2026-11-25',
            'mes_referencia' => '2026-11',
            'situacao' => 'publicado',
        ];
    }
}
