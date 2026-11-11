<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarEventoRequest;
use App\Models\AgendaEvento;
use App\Models\AgendaOcorrencia;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Agenda\Calendario;
use App\Services\Agenda\EnviaAlertasProgramados;
use App\Services\Agenda\Ocorrencia;
use App\Services\Agenda\ResumoRepeticao;
use App\Services\Agenda\TelaAgenda;
use App\Support\Recorrencia;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Agenda dos administradores (Master e ADM): compromissos, alertas
 * programados, lembretes e datas importantes, com os feriados na faixa
 * "dia todo". O perfil Cliente não tem acesso (rotas em perfil:master,adm).
 */
class AgendaController extends Controller
{
    public function index(Request $request, TelaAgenda $tela): View
    {
        $visao = in_array($request->query('visao'), TelaAgenda::VISOES, true) ? $request->query('visao') : null;
        $data = TelaAgenda::lerData($request->query('data')) ?? AgendaEvento::hoje();
        $mini = TelaAgenda::lerData($request->query('mini'), 'Y-m');

        return view('agenda.index', $tela->montar($request->user(), $data, $visao, $mini) + [
            'temPublicacao' => Postagem::where('situacao', 'publicado')->exists(),
        ]);
    }

    // ----------------------------------------------------------- formulário

    /**
     * Pedido via JavaScript (janela sobre a agenda) recebe só o formulário;
     * acesso direto recebe a página inteira, que é a reserva.
     */
    public function criar(Request $request): View
    {
        $tipo = array_key_exists($request->query('tipo'), AgendaEvento::TIPOS)
            ? $request->query('tipo') : AgendaEvento::COMPROMISSO;

        $evento = new AgendaEvento([
            'tipo' => $tipo,
            'data' => TelaAgenda::lerData($request->query('data')) ?? AgendaEvento::hoje(),
            'repetir' => 'nao',
            'termina' => 'nunca',
            'visibilidade' => 'equipe',
        ]);

        return $this->formulario($request, $evento);
    }

    public function editar(Request $request, AgendaEvento $evento): View
    {
        $this->conferirAcesso($request, $evento);

        return $this->formulario($request, $evento);
    }

    public function salvar(SalvarEventoRequest $request): JsonResponse|RedirectResponse
    {
        $evento = DB::transaction(function () use ($request) {
            $evento = AgendaEvento::create($request->dados() + ['user_id' => $request->user()->id]);
            $evento->participantes()->sync($request->participantes());

            return $evento;
        });

        return $this->depoisDeSalvar($request, $evento, $this->mensagemDeSalvo($evento, novo: true));
    }

    public function atualizar(SalvarEventoRequest $request, AgendaEvento $evento): JsonResponse|RedirectResponse
    {
        $this->conferirAcesso($request, $evento);

        DB::transaction(function () use ($request, $evento) {
            $evento->update($request->dados());
            $evento->participantes()->sync($request->participantes());
        });

        return $this->depoisDeSalvar($request, $evento, $this->mensagemDeSalvo($evento, novo: false));
    }

    public function excluir(Request $request, AgendaEvento $evento): RedirectResponse
    {
        $this->conferirAcesso($request, $evento);

        $nome = $evento->nomeTipo();
        $data = $evento->data;
        $evento->delete(); // os envios já feitos continuam na Central de alertas

        return redirect()
            ->route('agenda.index', $this->voltarPara($request, $data))
            ->with('ok', "{$nome} removido da agenda.");
    }

    // ------------------------------------------------------- uma ocorrência

    /** Detalhe de uma data do evento: é o cartão que abre ao clicar na agenda. */
    public function detalhe(Request $request, AgendaEvento $evento, string $data, Calendario $calendario, ResumoRepeticao $resumo): View
    {
        $ocorrencia = $this->ocorrencia($request, $evento, $data, $calendario);

        $dados = [
            'o' => $ocorrencia,
            'evento' => $evento,
            'repete' => $resumo->linha($ocorrencia),
            'destinatarios' => $calendario->destinatarios()[0],
            'visao' => $this->visao($request),
        ];

        return $request->ajax()
            ? view('agenda._detalhe', $dados)
            : view('agenda.detalhe', $dados);
    }

    /** "Cancelar este": só esta data; no alerta, este envio não sai. */
    public function cancelar(Request $request, AgendaEvento $evento, string $data, Calendario $calendario): RedirectResponse
    {
        $ocorrencia = $this->ocorrencia($request, $evento, $data, $calendario);
        $voltar = redirect()->route('agenda.index', $this->voltarPara($request, $ocorrencia->dia));

        if (! $ocorrencia->podeCancelar()) {
            return $voltar->withErrors(['agenda' => 'Esta data já aconteceu ou já foi cancelada.']);
        }

        try {
            AgendaOcorrencia::create([
                'agenda_evento_id' => $evento->id,
                'data' => $ocorrencia->chave(),
                'situacao' => AgendaOcorrencia::CANCELADO,
                'user_id' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Já existe registro para a data: o envio saiu enquanto a tela estava aberta.
            return $voltar->withErrors(['agenda' => 'Não foi possível cancelar: este envio já foi processado.']);
        }

        $quando = $ocorrencia->data->format('d/m');

        return $voltar->with('ok', $evento->ehAlerta()
            ? "Envio de {$quando} cancelado." . ($evento->repete() ? ' Os outros envios da série continuam.' : '')
            : "{$evento->nomeTipo()} de {$quando} cancelado." . ($evento->repete() ? ' As outras datas continuam.' : ''));
    }

    /** "Enviar agora": o alerta desta data sai já, antes do horário marcado. */
    public function enviarAgora(Request $request, AgendaEvento $evento, string $data, Calendario $calendario, EnviaAlertasProgramados $envio): RedirectResponse
    {
        $ocorrencia = $this->ocorrencia($request, $evento, $data, $calendario);
        $voltar = redirect()->route('agenda.index', $this->voltarPara($request, $ocorrencia->dia));

        if (! $ocorrencia->podeEnviarAgora()) {
            return $voltar->withErrors(['agenda' => 'Este alerta não está mais programado: já foi enviado ou cancelado.']);
        }

        $registro = $envio->enviar($evento, $ocorrencia->data, $request->user());

        if (! $registro) {
            return $voltar->withErrors(['agenda' => 'Este alerta já foi enviado ou cancelado.']);
        }

        if ($registro->situacao !== AgendaOcorrencia::ENVIADO) {
            return $voltar->withErrors(['agenda' => 'O alerta não foi enviado. ' . $registro->detalhe]);
        }

        $total = $registro->disparo?->total_enviados ?? 0;

        return $voltar->with('ok', "Alerta enviado agora: {$total} destinatário(s). O envio está na Central de alertas.");
    }

    // ------------------------------------------------------------- apoios

    /** Resumo da repetição e dicas da lista "Repetir", conforme o que está no formulário. */
    public function resumo(Request $request, ResumoRepeticao $resumo): JsonResponse
    {
        $data = TelaAgenda::lerData($request->query('data'));
        $tipo = array_key_exists($request->query('tipo'), AgendaEvento::TIPOS) ? $request->query('tipo') : AgendaEvento::COMPROMISSO;
        $repetir = array_key_exists($request->query('repetir'), Recorrencia::OPCOES) ? $request->query('repetir') : 'nao';
        $termina = in_array($request->query('termina'), ['data', 'vezes'], true) ? $request->query('termina') : 'nunca';
        $terminaEm = TelaAgenda::lerData($request->query('termina_em'));
        $vezes = max(1, min(500, $request->integer('termina_vezes')));
        $hora = fn (string $campo) => preg_match('/^\d{2}:\d{2}$/', (string) $request->query($campo)) ? $request->query($campo) : null;

        // Término incompleto ou anterior à primeira data: ainda não há o que resumir.
        $incompleto = ! $data
            || ($termina === 'data' && (! $terminaEm || $terminaEm->lt($data)))
            || ($termina === 'vezes' && ! $request->filled('termina_vezes'));

        if ($incompleto) {
            return response()->json(['frase' => null, 'dicas' => $data ? $resumo->dicas($data) : null]);
        }

        $rascunho = new AgendaEvento([
            'tipo' => $tipo,
            'data' => $data,
            'hora_inicio' => $hora('hora_inicio'),
            'hora_fim' => $hora('hora_fim'),
            'dia_inteiro' => $request->boolean('dia_inteiro'),
            'repetir' => $repetir,
            'termina' => $termina,
            'termina_em' => $terminaEm,
            'termina_vezes' => $termina === 'vezes' ? $vezes : null,
        ]);

        return response()->json([
            'frase' => $resumo->frase($rascunho),
            'dicas' => $resumo->dicas($data),
        ]);
    }

    /**
     * Avisos que o painel mostra na hora: compromisso chegando (conforme o
     * "Lembrar antes"), lembrete no horário e data importante chegando
     * (conforme o "Avisar antes"). A página consulta a cada minuto.
     */
    public function avisos(Request $request, Calendario $calendario): JsonResponse
    {
        $eu = $request->user();
        $agora = AgendaEvento::agora();
        $hoje = AgendaEvento::hoje();
        $avisos = [];

        foreach ($calendario->ocorrencias($eu, $hoje, $hoje->addDays(7)) as $o) {
            $evento = $o->evento;
            $inicio = $o->instante();

            if ($o->cancelada()) {
                continue;
            }

            // Data importante: aviso no painel a partir de N dias antes, um por dia.
            if ($evento->tipo === AgendaEvento::DATA_IMPORTANTE) {
                $faltam = (int) $hoje->diffInDays($o->dia);
                if (! $evento->avisar_antes || $faltam > $evento->avisar_antes) {
                    continue;
                }
                $avisos[] = [
                    'id' => $evento->id . ':' . $o->chave() . ':' . $hoje->format('Y-m-d'),
                    'tipo' => $evento->tipo,
                    'titulo' => $evento->nome(),
                    'texto' => match ($faltam) {
                        0 => 'É hoje.',
                        1 => 'É amanhã, ' . $o->dia->format('d/m') . '.',
                        default => "Faltam {$faltam} dias: " . $o->dia->format('d/m') . '.',
                    },
                    'url' => route('agenda.index', ['data' => $o->dia->format('Y-m-d'), 'visao' => 'dia']),
                ];
                continue;
            }

            if ($o->semHorario()) {
                continue;
            }

            if ($evento->tipo === AgendaEvento::COMPROMISSO && $evento->lembrar_antes) {
                $participa = $evento->user_id === $eu->id || $evento->participantes->contains('id', $eu->id);
                if (! $participa || $agora->lt($inicio->subMinutes($evento->lembrar_antes)) || $agora->gte($inicio)) {
                    continue;
                }
                $texto = $o->dia->equalTo($hoje) ? "Hoje, às {$evento->hora_inicio}" : "Amanhã, às {$evento->hora_inicio}";
            } elseif ($evento->tipo === AgendaEvento::LEMBRETE) {
                // O lembrete fica disponível por 30 minutos a partir do horário.
                if ($agora->lt($inicio) || $agora->gte($inicio->addMinutes(30))) {
                    continue;
                }
                $texto = "Lembrete das {$evento->hora_inicio}";
            } else {
                continue;
            }

            $avisos[] = [
                'id' => $evento->id . ':' . $o->chave(),
                'tipo' => $evento->tipo,
                'titulo' => $evento->nome(),
                'texto' => $texto . ($evento->local ? ' · ' . $evento->local : ''),
                'url' => route('agenda.index', ['data' => $o->dia->format('Y-m-d'), 'visao' => 'dia']),
            ];
        }

        return response()->json(['avisos' => $avisos]);
    }

    private function formulario(Request $request, AgendaEvento $evento): View
    {
        $calendario = app(Calendario::class);
        [$destinatarios, $semAutorizacao] = $calendario->destinatarios();

        $dados = [
            'evento' => $evento,
            'publicadas' => Postagem::where('situacao', 'publicado')->orderByDesc('data_publicacao')->orderByDesc('id')->get(),
            'publicacoes' => Postagem::orderByDesc('data_publicacao')->orderByDesc('id')->get(['id', 'titulo']),
            'modelos' => AlertTemplate::orderBy('nome')->get(),
            'administradores' => User::whereIn('perfil', [User::MASTER, User::ADM])->where('ativo', true)->orderBy('nome')->get(),
            'clientes' => User::where('perfil', User::CLIENT)->orderBy('nome')->get(),
            'participantes' => $evento->exists ? $evento->participantes->pluck('id')->all() : [],
            'destinatarios' => $destinatarios,
            'semAutorizacao' => $semAutorizacao,
            'visao' => $this->visao($request),
            'hojeIso' => AgendaEvento::hoje()->format('Y-m-d'),
        ];

        return $request->ajax()
            ? view('agenda._form', $dados + ['modal' => true])
            : view('agenda.form', $dados);
    }

    private function depoisDeSalvar(Request $request, AgendaEvento $evento, string $mensagem): JsonResponse|RedirectResponse
    {
        $destino = route('agenda.index', $this->voltarPara($request, $evento->data));
        $request->session()->flash('ok', $mensagem);

        // A janela envia por fetch e segue para o endereço devolvido.
        return $request->expectsJson()
            ? response()->json(['ir' => $destino])
            : redirect($destino);
    }

    private function mensagemDeSalvo(AgendaEvento $evento, bool $novo): string
    {
        if (! $evento->ehAlerta()) {
            return $evento->nomeTipo() . ($novo ? ' salvo na agenda.' : ' atualizado.');
        }

        $total = $evento->recorrencia()->total();
        $quando = $evento->data->format('d/m') . ' às ' . $evento->hora_inicio;

        return match (true) {
            ! $novo => 'Alerta programado atualizado.',
            $total === 1 => "Alerta programado para {$quando}.",
            $total === null => "Alerta programado: o primeiro envio sai em {$quando} e a série não tem data para terminar.",
            default => "Alerta programado: {$total} envios, o primeiro em {$quando}.",
        };
    }

    /** Lembrete e data "só para mim" são de quem criou. */
    private function conferirAcesso(Request $request, AgendaEvento $evento): void
    {
        abort_unless($evento->visibilidade === 'equipe' || $evento->user_id === $request->user()->id, 404);
    }

    private function ocorrencia(Request $request, AgendaEvento $evento, string $data, Calendario $calendario): Ocorrencia
    {
        $this->conferirAcesso($request, $evento);

        $dia = TelaAgenda::lerData($data);
        $ocorrencia = $dia ? $calendario->ocorrencia($evento, $dia) : null;

        abort_unless($ocorrencia, 404);

        return $ocorrencia;
    }

    private function visao(Request $request): ?string
    {
        $visao = $request->input('visao', $request->query('visao'));

        return in_array($visao, TelaAgenda::VISOES, true) ? $visao : null;
    }

    /** Parâmetros para voltar à agenda na data do evento, mantendo a visualização. */
    private function voltarPara(Request $request, CarbonImmutable $data): array
    {
        return array_filter(['data' => $data->format('Y-m-d'), 'visao' => $this->visao($request)]);
    }
}
