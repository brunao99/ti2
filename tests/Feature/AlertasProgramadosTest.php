<?php

namespace Tests\Feature;

use App\Models\AgendaEvento;
use App\Models\AgendaOcorrencia;
use App\Models\AlertDispatch;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertasProgramadosTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;
    private Postagem $publicacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:29', AgendaEvento::FUSO));
        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
        $this->adm = User::factory()->create(['perfil' => User::ADM]);
        User::factory()->recebeAlertas()->count(2)->create();
        User::factory()->comTelefone()->create(); // tem telefone, mas não autorizou

        $this->publicacao = Postagem::create([
            'tipo' => 'ministerios_editais', 'titulo' => 'Edital ArborizaCidades', 'texto' => '<p>x</p>',
            'data_publicacao' => '2026-11-10', 'mes_referencia' => '2026-11', 'situacao' => 'publicado',
            'user_id' => $this->adm->id,
        ]);
    }

    private function alerta(array $dados = []): AgendaEvento
    {
        return AgendaEvento::create($dados + [
            'tipo' => 'alerta', 'data' => '2026-11-11', 'hora_inicio' => '15:30',
            'repetir' => 'semanal', 'termina' => 'data', 'termina_em' => '2026-11-27',
            'postagem_id' => $this->publicacao->id, 'alert_template_id' => AlertTemplate::first()->id,
            'visibilidade' => 'equipe', 'user_id' => $this->adm->id,
        ]);
    }

    public function test_o_agendador_envia_no_horario_e_uma_vez_so(): void
    {
        $evento = $this->alerta();

        // Antes do horário, nada sai.
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('0 enviado(s)')->assertSuccessful();
        $this->assertDatabaseCount('alert_dispatches', 0);

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:30', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('1 enviado(s)')->assertSuccessful();
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('0 enviado(s)')->assertSuccessful();

        $disparo = AlertDispatch::sole();
        $this->assertSame([2, 2, 'sucesso', $this->adm->id], [$disparo->total_destinatarios, $disparo->total_enviados, $disparo->status, $disparo->user_id]);
        $this->assertSame(3, $disparo->destinatarios()->count()); // os 2 autorizados + 1 ignorado
        $this->assertSame(AgendaOcorrencia::ENVIADO, $evento->ocorrencias()->sole()->situacao);

        // O envio aparece na Central de alertas e a agenda mostra "Enviado".
        $this->actingAs($this->adm)->get(route('alertas.index'))->assertSee('Edital ArborizaCidades');
        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia']))->assertSee('Enviado · 2 entregues');

        // A semana seguinte sai na sua data.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:31', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('1 enviado(s)');
        $this->assertSame(['2026-11-11', '2026-11-18'], $evento->ocorrencias()->orderBy('data')->get()->map(fn ($o) => $o->data->format('Y-m-d'))->all());
    }

    public function test_envio_cancelado_nao_sai_e_os_outros_continuam(): void
    {
        $evento = $this->alerta();

        $this->actingAs($this->adm)->post(route('agenda.eventos.cancelar', ['evento' => $evento, 'data' => '2026-11-11']))
            ->assertSessionHas('ok', 'Envio de 11/11 cancelado. Os outros envios da série continuam.');

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:35', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas');
        $this->assertDatabaseCount('alert_dispatches', 0);

        $this->actingAs($this->adm)->get(route('agenda.eventos.detalhe', ['evento' => $evento, 'data' => '2026-11-11']))->assertSee('Cancelado');

        $this->travelTo(CarbonImmutable::parse('2026-11-18 15:30', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas');
        $this->assertDatabaseCount('alert_dispatches', 1);
    }

    public function test_enviar_agora_sai_antes_do_horario_e_nao_repete_no_agendador(): void
    {
        $evento = $this->alerta(['repetir' => 'nao', 'termina' => 'nunca', 'termina_em' => null]);

        $this->actingAs($this->adm)->post(route('agenda.eventos.enviar', ['evento' => $evento, 'data' => '2026-11-11']))
            ->assertSessionHas('ok', 'Alerta enviado agora: 2 destinatário(s). O envio está na Central de alertas.');

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:30', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('0 enviado(s)');
        $this->assertDatabaseCount('alert_dispatches', 1);

        $this->actingAs($this->adm)->post(route('agenda.eventos.enviar', ['evento' => $evento, 'data' => '2026-11-11']))
            ->assertSessionHasErrors('agenda');
    }

    public function test_publicacao_despublicada_registra_falha_sem_enviar(): void
    {
        $evento = $this->alerta();
        $this->publicacao->update(['situacao' => 'rascunho']);

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:30', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('1 com falha');

        $this->assertDatabaseCount('alert_dispatches', 0);
        $ocorrencia = $evento->ocorrencias()->sole();
        $this->assertSame([AgendaOcorrencia::FALHOU, 'A publicação do alerta não está mais publicada.'], [$ocorrencia->situacao, $ocorrencia->detalhe]);
        $this->actingAs($this->adm)->get(route('agenda.eventos.detalhe', ['evento' => $evento, 'data' => '2026-11-11']))
            ->assertSee('Falhou')->assertSee('A publicação do alerta não está mais publicada.');
    }

    public function test_envio_atrasado_sai_dentro_da_tolerancia_e_depois_dela_conta_como_falha(): void
    {
        $evento = $this->alerta(['repetir' => 'nao', 'termina' => 'nunca', 'termina_em' => null]);

        // Servidor ficou parado e voltou na manhã seguinte: ainda dentro de 24 horas.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('1 enviado(s)');

        $outro = $this->alerta(['repetir' => 'nao', 'termina' => 'nunca', 'termina_em' => null, 'data' => '2026-11-12', 'hora_inicio' => '08:00']);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00', AgendaEvento::FUSO));
        $this->artisan('agenda:enviar-alertas')->expectsOutputToContain('0 enviado(s)');
        $this->actingAs($this->adm)->get(route('agenda.eventos.detalhe', ['evento' => $outro, 'data' => '2026-11-12']))->assertSee('Falhou');
    }

    public function test_o_painel_aberto_tambem_dispara_o_envio(): void
    {
        $this->alerta();
        Cache::flush();

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:31', AgendaEvento::FUSO));
        $this->actingAs($this->adm)->get(route('painel.inicio'))->assertOk();

        $this->assertDatabaseCount('alert_dispatches', 1);
    }

    public function test_alerta_manual_continua_igual(): void
    {
        $this->actingAs($this->adm)
            ->post(route('alertas.disparar', $this->publicacao), ['alert_template_id' => AlertTemplate::first()->id])
            ->assertRedirect(route('alertas.index'));

        $this->assertSame(2, AlertDispatch::sole()->total_enviados);
        $this->assertTrue($this->publicacao->fresh()->alerta_enviado);
    }
}
