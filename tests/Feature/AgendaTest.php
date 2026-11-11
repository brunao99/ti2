<?php

namespace Tests\Feature;

use App\Models\AgendaEvento;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 11/11/2026, 10:40 no horário de Brasília.
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:40', AgendaEvento::FUSO));
        User::query()->update(['telefone' => null, 'alerta_consentimento_em' => null]);
        $this->adm = User::factory()->create(['perfil' => User::ADM, 'nome' => 'Ana Administradora']);
    }

    private function evento(array $dados): AgendaEvento
    {
        return AgendaEvento::create($dados + [
            'user_id' => $this->adm->id, 'repetir' => 'nao', 'termina' => 'nunca', 'visibilidade' => 'equipe',
        ]);
    }

    private function publicacao(string $titulo = 'Edital ArborizaCidades'): Postagem
    {
        return Postagem::create([
            'tipo' => 'ministerios_editais', 'titulo' => $titulo, 'texto' => '<p>x</p>',
            'data_publicacao' => '2026-11-10', 'mes_referencia' => '2026-11', 'situacao' => 'publicado',
            'user_id' => $this->adm->id,
        ]);
    }

    // ------------------------------------------------------------- acesso

    public function test_cliente_nao_ve_nem_acessa_a_agenda(): void
    {
        $cliente = User::factory()->create(['perfil' => User::CLIENT]);

        $this->actingAs($cliente)->get(route('painel.inicio'))->assertOk()->assertDontSee(route('agenda.index'));
        foreach (['agenda.index', 'agenda.eventos.criar', 'feriados.index', 'agenda.avisos', 'agenda.resumo'] as $rota) {
            $this->actingAs($cliente)->get(route($rota))->assertForbidden();
        }
        $this->actingAs($cliente)->post(route('agenda.eventos.salvar'), [])->assertForbidden();
    }

    public function test_administradores_veem_a_agenda_no_menu(): void
    {
        foreach ([User::ADM, User::MASTER] as $perfil) {
            $usuario = User::factory()->create(['perfil' => $perfil]);
            $this->actingAs($usuario)->get(route('painel.inicio'))->assertOk()->assertSee(route('agenda.index'));
            $this->actingAs($usuario)->get(route('painel.mais'))->assertSee('Calendário de feriados');
        }
    }

    // ------------------------------------------------------------- telas

    public function test_semana_de_trabalho_mostra_os_eventos_feriados_e_a_linha_de_agora(): void
    {
        $this->evento(['tipo' => 'compromisso', 'titulo' => 'Reunião com Divinópolis', 'data' => '2026-11-10',
            'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'local' => 'Microsoft Teams']);
        $this->evento(['tipo' => 'lembrete', 'titulo' => 'Revisar publicações', 'data' => '2026-11-09', 'hora_inicio' => '14:00', 'repetir' => 'semanal']);
        $this->evento(['tipo' => 'data_importante', 'titulo' => 'Prazo: PDDE Equidade', 'data' => '2026-11-11', 'categoria' => 'prazo']);
        $this->evento(['tipo' => 'compromisso', 'titulo' => 'Fora da semana', 'data' => '2026-11-20', 'hora_inicio' => '09:00', 'hora_fim' => '10:00']);

        $this->actingAs($this->adm)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('09–13 de novembro de 2026')
            ->assertSee('Reunião com Divinópolis')
            ->assertSee('Microsoft Teams')
            ->assertSee('Revisar publicações')
            ->assertSee('Prazo: PDDE Equidade')
            ->assertSee('class="ag-agora"', false)
            ->assertDontSee('Fora da semana');

        // Semana com feriado: a faixa "dia todo" mostra o feriado (02/11 Finados vem da carga inicial).
        $this->actingAs($this->adm)->get(route('agenda.index', ['data' => '2026-11-02']))
            ->assertSee('Finados — feriado nacional')
            ->assertDontSee('class="ag-agora"', false);
    }

    public function test_visualizacoes_dia_e_mes(): void
    {
        $this->evento(['tipo' => 'compromisso', 'titulo' => 'Treinamento do painel', 'data' => '2026-11-25', 'hora_inicio' => '16:30', 'hora_fim' => '17:30']);

        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'mes']))
            ->assertOk()->assertSee('Novembro de 2026')->assertSee('Treinamento do painel');

        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2026-11-25']))
            ->assertOk()->assertSee('Quarta-feira, 25 de novembro de 2026')->assertSee('Treinamento do painel');

        // Data inválida no endereço volta para hoje, sem erro.
        $this->actingAs($this->adm)->get(route('agenda.index', ['data' => '2026-02-31', 'visao' => 'x']))
            ->assertOk()->assertSee('09–13 de novembro de 2026');
    }

    public function test_lembrete_so_para_mim_nao_aparece_para_outro_administrador(): void
    {
        $meu = $this->evento(['tipo' => 'lembrete', 'titulo' => 'Lembrete particular', 'data' => '2026-11-11',
            'hora_inicio' => '15:00', 'visibilidade' => 'eu']);
        $outro = User::factory()->create(['perfil' => User::MASTER]);

        $this->actingAs($this->adm)->get(route('agenda.index'))->assertSee('Lembrete particular');
        $this->actingAs($outro)->get(route('agenda.index'))->assertDontSee('Lembrete particular');
        $this->actingAs($outro)->get(route('agenda.eventos.detalhe', ['evento' => $meu, 'data' => '2026-11-11']))->assertNotFound();
        $this->actingAs($outro)->get(route('agenda.eventos.editar', $meu))->assertNotFound();
    }

    public function test_prazo_que_cai_em_fim_de_semana_ou_feriado_aparece_no_dia_util_anterior(): void
    {
        // Bimestral a partir de 30/11/2026: 30/01/2027 é sábado → 29/01 (sexta).
        $this->evento(['tipo' => 'data_importante', 'titulo' => 'Prazo: RREO', 'data' => '2026-11-30',
            'categoria' => 'prazo', 'repetir' => 'bimestral']);
        // Repetição em 15/11/2026 (domingo, feriado) → sexta, 13/11.
        $this->evento(['tipo' => 'data_importante', 'titulo' => 'Prazo: mensal do dia 15', 'data' => '2026-10-15',
            'categoria' => 'prazo', 'repetir' => 'mensal']);

        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2027-01-29']))->assertSee('Prazo: RREO');
        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2027-01-30']))->assertDontSee('Prazo: RREO');
        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2026-11-13']))->assertSee('Prazo: mensal do dia 15');
        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2026-11-15']))->assertDontSee('Prazo: mensal do dia 15');
    }

    // ------------------------------------------------------------- cadastro

    public function test_cadastra_compromisso_com_repeticao_e_participantes(): void
    {
        $master = User::factory()->create(['perfil' => User::MASTER]);
        $cliente = User::factory()->create(['perfil' => User::CLIENT, 'nome' => 'Divinópolis/MG']);

        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), [
            'tipo' => 'compromisso', 'titulo' => 'Reunião com a Prefeitura', 'data' => '2026-11-10',
            'hora_inicio' => '10:00', 'hora_fim' => '11:00', 'repetir' => 'quinzenal', 'termina' => 'vezes', 'termina_vezes' => 6,
            'local' => 'https://teams.microsoft.com/l/x', 'participantes' => [$this->adm->id, $master->id],
            'lembrar_antes' => 15, 'cliente_id' => $cliente->id, 'visao' => 'semana',
        ])->assertOk()->assertJson(['ir' => route('agenda.index', ['data' => '2026-11-10', 'visao' => 'semana'])]);

        $evento = AgendaEvento::sole();
        $this->assertSame(['compromisso', 'quinzenal', 'vezes', 6, 15], [$evento->tipo, $evento->repetir, $evento->termina, $evento->termina_vezes, $evento->lembrar_antes]);
        $this->assertEqualsCanonicalizing([$this->adm->id, $master->id], $evento->participantes->pluck('id')->all());
        $this->assertSame('2027-01-19', $evento->recorrencia()->ultima()->format('Y-m-d'));

        $this->actingAs($this->adm)->get(route('agenda.index', ['data' => '2027-01-19']))->assertSee('Reunião com a Prefeitura');
        $this->actingAs($this->adm)->get(route('agenda.index', ['data' => '2027-02-02']))->assertDontSee('Reunião com a Prefeitura');
    }

    public function test_cadastra_lembrete_e_data_importante(): void
    {
        $this->actingAs($this->adm)->post(route('agenda.eventos.salvar'), [
            'tipo' => 'lembrete', 'titulo' => 'Enviar relatório mensal', 'data' => '2026-11-12', 'hora_inicio' => '16:00',
            'repetir' => 'mensal', 'termina' => 'nunca', 'visibilidade' => 'eu', 'observacao' => 'Planilha + PDF',
        ])->assertRedirect(route('agenda.index', ['data' => '2026-11-12']))->assertSessionHas('ok', 'Lembrete salvo na agenda.');

        $this->actingAs($this->adm)->post(route('agenda.eventos.salvar'), [
            'tipo' => 'data_importante', 'titulo' => 'Prazo: Censo Escolar', 'data' => '2026-11-25',
            'categoria' => 'prazo', 'avisar_antes' => 5, 'repetir' => 'nao', 'mostrar_equipe' => '1',
            // campos de outro tipo enviados por engano são ignorados
            'hora_inicio' => '10:00', 'local' => 'Não vale',
        ])->assertRedirect();

        $lembrete = AgendaEvento::where('tipo', 'lembrete')->sole();
        $data = AgendaEvento::where('tipo', 'data_importante')->sole();
        $this->assertSame(['eu', 'Planilha + PDF'], [$lembrete->visibilidade, $lembrete->observacao]);
        $this->assertSame(['equipe', null, null, 5], [$data->visibilidade, $data->hora_inicio, $data->local, $data->avisar_antes]);
    }

    public function test_validacoes_do_formulario(): void
    {
        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), [
            'tipo' => 'compromisso', 'titulo' => '', 'data' => '2026-11-12', 'hora_inicio' => '11:00', 'hora_fim' => '10:00',
            'repetir' => 'semanal', 'termina' => 'data', 'termina_em' => '2026-11-01',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'titulo' => 'Informe o título do compromisso.',
            'hora_fim' => 'O fim deve ser depois do início.',
            'termina_em' => 'A repetição não pode terminar antes da primeira data.',
        ]);

        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), [
            'tipo' => 'compromisso', 'titulo' => 'Dia inteiro', 'data' => '2026-11-12', 'dia_inteiro' => '1', 'repetir' => 'diario',
        ])->assertStatus(422)->assertJsonValidationErrors(['repetir' => 'Escolha uma opção de repetição da lista.']);

        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), [
            'tipo' => 'compromisso', 'titulo' => 'Dia inteiro', 'data' => '2026-11-12', 'dia_inteiro' => '1', 'repetir' => 'nao',
        ])->assertOk();
        $this->assertTrue(AgendaEvento::sole()->semHorario());
    }

    public function test_programar_alerta_exige_publicacao_publicada_horario_futuro_e_alguem_autorizado(): void
    {
        $modelo = AlertTemplate::first();
        $rascunho = $this->publicacao('Rascunho');
        $rascunho->update(['situacao' => 'rascunho']);
        $publicada = $this->publicacao();

        $dados = ['tipo' => 'alerta', 'postagem_id' => $publicada->id, 'alert_template_id' => $modelo->id,
            'data' => '2026-11-11', 'hora_inicio' => '15:30', 'repetir' => 'semanal', 'termina' => 'data', 'termina_em' => '2026-11-27'];

        // Ninguém autorizou ainda.
        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), $dados)
            ->assertStatus(422)->assertJsonValidationErrors(['postagem_id']);

        User::factory()->recebeAlertas()->create();

        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), ['postagem_id' => $rascunho->id] + $dados)
            ->assertStatus(422)->assertJsonValidationErrors(['postagem_id' => 'Escolha uma publicação com situação "publicado".']);
        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), ['data' => '2026-11-10'] + $dados)
            ->assertStatus(422)->assertJsonValidationErrors(['data' => 'A data do envio não pode ser anterior a hoje.']);
        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), ['hora_inicio' => '09:00'] + $dados)
            ->assertStatus(422)->assertJsonValidationErrors(['hora_inicio']);

        $this->actingAs($this->adm)->postJson(route('agenda.eventos.salvar'), $dados)->assertOk();
        $this->assertSame('Alerta programado: 3 envios, o primeiro em 11/11 às 15:30.', session('ok'));

        $evento = AgendaEvento::sole();
        $this->assertNull($evento->titulo);
        $this->assertSame('Edital ArborizaCidades', $evento->nome());

        $this->actingAs($this->adm)->get(route('agenda.index'))
            ->assertSee('Próximos alertas')->assertSee('Hoje, 15:30 · 1 destinatário');
    }

    public function test_editar_e_excluir_evento(): void
    {
        $evento = $this->evento(['tipo' => 'compromisso', 'titulo' => 'Antigo', 'data' => '2026-11-12', 'hora_inicio' => '09:00', 'hora_fim' => '10:00']);

        $this->actingAs($this->adm)->get(route('agenda.eventos.editar', $evento), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Editar compromisso')->assertDontSee('<html', false);

        $this->actingAs($this->adm)->put(route('agenda.eventos.atualizar', $evento), [
            'tipo' => 'compromisso', 'titulo' => 'Novo título', 'data' => '2026-11-13', 'hora_inicio' => '14:00', 'hora_fim' => '15:00', 'repetir' => 'nao',
        ])->assertRedirect(route('agenda.index', ['data' => '2026-11-13']));
        $this->assertSame('Novo título', $evento->fresh()->titulo);

        $this->actingAs($this->adm)->delete(route('agenda.eventos.excluir', $evento))->assertRedirect();
        $this->assertDatabaseCount('agenda_eventos', 0);
    }

    public function test_cancelar_uma_data_da_serie(): void
    {
        $evento = $this->evento(['tipo' => 'compromisso', 'titulo' => 'Alinhamento semanal', 'data' => '2026-11-11',
            'hora_inicio' => '16:00', 'hora_fim' => '17:00', 'repetir' => 'semanal']);

        $this->actingAs($this->adm)->post(route('agenda.eventos.cancelar', ['evento' => $evento, 'data' => '2026-11-18']))
            ->assertRedirect()->assertSessionHas('ok', 'Compromisso de 18/11 cancelado. As outras datas continuam.');

        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2026-11-18']))->assertDontSee('Alinhamento semanal');
        $this->actingAs($this->adm)->get(route('agenda.index', ['visao' => 'dia', 'data' => '2026-11-25']))->assertSee('Alinhamento semanal');

        // Data que já passou ou que não pertence à série.
        $this->actingAs($this->adm)->post(route('agenda.eventos.cancelar', ['evento' => $evento, 'data' => '2026-11-18']))
            ->assertSessionHasErrors('agenda');
        $this->actingAs($this->adm)->post(route('agenda.eventos.cancelar', ['evento' => $evento, 'data' => '2026-11-19']))->assertNotFound();
    }

    public function test_detalhe_mostra_situacao_e_repeticao_do_alerta(): void
    {
        User::factory()->recebeAlertas()->count(2)->create();
        $evento = $this->evento(['tipo' => 'alerta', 'data' => '2026-11-11', 'hora_inicio' => '15:30', 'repetir' => 'semanal',
            'termina' => 'data', 'termina_em' => '2026-11-27', 'postagem_id' => $this->publicacao()->id, 'alert_template_id' => AlertTemplate::first()->id]);

        $this->actingAs($this->adm)
            ->get(route('agenda.eventos.detalhe', ['evento' => $evento, 'data' => '2026-11-18']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Programado')
            ->assertSee('2 usuários que autorizaram alertas')
            ->assertSee('Semanal, toda quarta às 15:30 · envio 2 de 3, até 25/11')
            ->assertSee('Editar série')->assertSee('Cancelar este')->assertSee('Enviar agora');
    }

    public function test_resumo_da_repeticao(): void
    {
        $resumo = fn (array $q) => $this->actingAs($this->adm)->getJson(route('agenda.resumo', $q))->assertOk()->json('frase');

        $this->assertSame('A partir de <b>04/11/2026</b>, toda quarta às 15:30, até 27/11/2026. <b>4 envios</b>: 04/11, 11/11, 18/11 e 25/11.',
            $resumo(['tipo' => 'alerta', 'data' => '2026-11-04', 'hora_inicio' => '15:30', 'repetir' => 'semanal', 'termina' => 'data', 'termina_em' => '2026-11-27']));
        $this->assertSame('A partir de <b>10/11/2026</b>, a cada 2 semanas na terça, das 10:00 às 11:00. <b>6 reuniões</b>, até 19/01/2027.',
            $resumo(['tipo' => 'compromisso', 'data' => '2026-11-10', 'hora_inicio' => '10:00', 'hora_fim' => '11:00', 'repetir' => 'quinzenal', 'termina' => 'vezes', 'termina_vezes' => 6]));
        $this->assertSame('A partir de <b>05/11/2026</b>, todo dia 5 às 16:00, sem data para terminar.',
            $resumo(['tipo' => 'lembrete', 'data' => '2026-11-05', 'hora_inicio' => '16:00', 'repetir' => 'mensal']));
        $this->assertSame('A partir de <b>30/11/2026</b>, a cada 2 meses: 30/01, 30/03, 30/05… Cai em fim de semana ou feriado? Mostra no dia útil anterior.',
            $resumo(['tipo' => 'data_importante', 'data' => '2026-11-30', 'repetir' => 'bimestral']));
        $this->assertNull($resumo(['tipo' => 'lembrete', 'data' => '2026-11-05', 'repetir' => 'nao']));
    }

    public function test_avisos_de_compromisso_lembrete_e_prazo(): void
    {
        $this->evento(['tipo' => 'compromisso', 'titulo' => 'Reunião das 10:50', 'data' => '2026-11-11', 'hora_inicio' => '10:50', 'hora_fim' => '11:30', 'lembrar_antes' => 15]);
        $this->evento(['tipo' => 'compromisso', 'titulo' => 'Reunião da tarde', 'data' => '2026-11-11', 'hora_inicio' => '15:00', 'hora_fim' => '16:00', 'lembrar_antes' => 15]);
        $this->evento(['tipo' => 'lembrete', 'titulo' => 'Lembrete das 10:30', 'data' => '2026-11-11', 'hora_inicio' => '10:30']);
        $this->evento(['tipo' => 'data_importante', 'titulo' => 'Prazo em 3 dias', 'data' => '2026-11-13', 'categoria' => 'prazo', 'avisar_antes' => 3]);
        $this->evento(['tipo' => 'data_importante', 'titulo' => 'Prazo sem aviso', 'data' => '2026-11-12', 'categoria' => 'prazo']);

        $avisos = collect($this->actingAs($this->adm)->getJson(route('agenda.avisos'))->assertOk()->json('avisos'));

        $this->assertEqualsCanonicalizing(['Reunião das 10:50', 'Lembrete das 10:30', 'Prazo em 3 dias'], $avisos->pluck('titulo')->all());
        $this->assertSame('Faltam 2 dias: 13/11.', $avisos->firstWhere('titulo', 'Prazo em 3 dias')['texto']);
    }
}
