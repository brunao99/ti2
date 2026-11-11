<?php

namespace App\Services\Agenda;

use App\Models\AgendaEvento;
use App\Models\Feriado;
use App\Models\User;
use App\Support\DataPt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Prepara tudo o que a tela da Agenda mostra para uma data e uma visualização.
 *
 * O computador e o celular têm leiautes diferentes e, sem escolha explícita,
 * padrões diferentes (Semana de trabalho e Dia). Como o servidor não sabe o
 * tamanho da tela, a página leva os dois leiautes e o CSS mostra um deles.
 */
class TelaAgenda
{
    public const VISOES = ['dia', 'semana', 'mes'];

    /** A grade mostra pelo menos este intervalo de horas. */
    private const HORA_INICIAL = 8;
    private const HORA_FINAL = 18;

    /** Altura de uma hora na grade, em pixels (igual à variável --ag-hora do CSS). */
    public const ALTURA_HORA = 72;

    /** Alerta e lembrete são blocos curtos; compromisso tem ao menos esta altura. */
    private const MINUTOS_MINIMOS = 36;

    /** No mês, quantas linhas cabem em um dia antes do "+N mais". */
    private const LINHAS_DO_MES = 3;

    public function __construct(private readonly Calendario $calendario)
    {
    }

    public function montar(User $usuario, CarbonImmutable $data, ?string $visaoPedida, ?CarbonImmutable $mesDoMini): array
    {
        $hoje = AgendaEvento::hoje();
        $agora = AgendaEvento::agora();
        $visao = $visaoPedida ?? 'semana';       // computador
        $visaoMovel = $visaoPedida ?? 'dia';     // celular

        $segunda = $data->startOfWeek(CarbonInterface::MONDAY);
        $semana = $this->dias($segunda, 7);
        [$gradeDe, $gradeAte] = $this->limitesDoMes($data);
        $mini = ($mesDoMini ?? $data)->startOfMonth();
        [$miniDe, $miniAte] = $this->limitesDoMes($mini);

        // Uma leitura só cobre tudo o que a página mostra.
        $de = min($segunda, $gradeDe, $miniDe);
        $ate = max($segunda->addDays(6), $gradeAte, $miniAte);

        $porDia = $this->calendario->ocorrencias($usuario, $de, $ate)
            ->groupBy(fn (Ocorrencia $o) => $o->dia->format('Y-m-d'));
        $feriados = Feriado::noIntervalo($de, $ate);
        [$destinatarios, $semAutorizacao] = $this->calendario->destinatarios();

        $colunas = match ($visao) {
            'dia' => [$data],
            'mes' => [],
            default => array_slice($semana, 0, 5),
        };

        // Período da visão do computador: é ele que as contagens de "Mostrar" consideram.
        [$periodoDe, $periodoAte] = match ($visao) {
            'dia' => [$data, $data],
            'mes' => [$data->startOfMonth(), $data->endOfMonth()->startOfDay()],
            default => [$segunda, $segunda->addDays(4)],
        };

        return [
            'data' => $data,
            'hoje' => $hoje,
            'agora' => $agora,
            'visao' => $visao,
            'visaoMovel' => $visaoMovel,
            'visaoPedida' => $visaoPedida,
            'destinatarios' => $destinatarios,
            'semAutorizacao' => $semAutorizacao,

            'rotulo' => $this->rotulo($visao, $data, $segunda),
            'anterior' => $this->vizinho($visao, $data, -1),
            'proximo' => $this->vizinho($visao, $data, 1),
            'anteriorMovel' => $visaoMovel === 'mes' ? $data->subMonthNoOverflow() : $data->subWeek(),
            'proximoMovel' => $visaoMovel === 'mes' ? $data->addMonthNoOverflow() : $data->addWeek(),

            'contagens' => $this->contagens($porDia, $periodoDe, $periodoAte),
            'proximosAlertas' => $this->calendario->proximosAlertas($usuario),

            'mini' => [
                'mes' => $mini,
                'anterior' => $mini->subMonthNoOverflow(),
                'proximo' => $mini->addMonthNoOverflow(),
                // Destaque: o dia, na visão Dia; a semana de trabalho nas outras.
                'semanas' => $visao === 'dia'
                    ? $this->semanasDoMes($mini, $porDia, $feriados, $hoje, $data, $data)
                    : $this->semanasDoMes($mini, $porDia, $feriados, $hoje, $segunda, $segunda->addDays(4)),
            ],

            'grade' => $colunas ? $this->grade($colunas, $porDia, $feriados, $hoje, $agora, $destinatarios) : null,
            'mes' => in_array('mes', [$visao, $visaoMovel], true)
                ? $this->semanasDoMes($data->startOfMonth(), $porDia, $feriados, $hoje, $data, $data, completo: true)
                : null,

            'faixa' => array_map(fn (CarbonImmutable $dia) => [
                'data' => $dia,
                'hoje' => $dia->equalTo($hoje),
                'atual' => $dia->equalTo($data),
                'tipos' => $this->tiposDoDia($porDia[$dia->format('Y-m-d')] ?? collect()),
            ], $semana),

            // Celular: a lista do dia escolhido (Dia e Mês) ou dos sete dias (Semana).
            'listas' => array_map(
                fn (CarbonImmutable $dia) => $this->lista($dia, $porDia, $feriados, $hoje, $agora, $destinatarios),
                $visaoMovel === 'semana' ? $semana : [$data],
            ),
        ];
    }

    /** Aceita só "Y-m-d" válido; qualquer outra coisa vira null. */
    public static function lerData(?string $valor, string $formato = 'Y-m-d'): ?CarbonImmutable
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            $data = CarbonImmutable::createFromFormat('!' . $formato, $valor);
        } catch (\Throwable) {
            return null;
        }

        return $data && $data->format($formato) === $valor ? $data : null;
    }

    // ------------------------------------------------------------- períodos

    /** @return array<int, CarbonImmutable> */
    private function dias(CarbonImmutable $inicio, int $quantos): array
    {
        return array_map(fn (int $i) => $inicio->addDays($i), range(0, $quantos - 1));
    }

    /** Do domingo antes do dia 1º ao sábado depois do último dia. */
    private function limitesDoMes(CarbonImmutable $data): array
    {
        return [
            $data->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY),
            $data->endOfMonth()->startOfDay()->endOfWeek(CarbonInterface::SATURDAY)->startOfDay(),
        ];
    }

    private function vizinho(string $visao, CarbonImmutable $data, int $sentido): CarbonImmutable
    {
        return match ($visao) {
            'dia' => $data->addDays($sentido),
            'mes' => $data->startOfMonth()->addMonthsNoOverflow($sentido),
            default => $data->addWeeks($sentido),
        };
    }

    private function rotulo(string $visao, CarbonImmutable $data, CarbonImmutable $segunda): string
    {
        if ($visao === 'dia') {
            return DataPt::extenso($data, true);
        }

        if ($visao === 'mes') {
            return DataPt::mesAno($data, true);
        }

        $sexta = $segunda->addDays(4);

        return $segunda->month === $sexta->month
            ? sprintf('%s–%s de %s de %d', $segunda->format('d'), $sexta->format('d'), DataPt::mes($sexta), $sexta->year)
            : sprintf('%s de %s – %s de %s de %d', $segunda->format('d'), DataPt::mes($segunda),
                $sexta->format('d'), DataPt::mes($sexta), $sexta->year);
    }

    // ------------------------------------------------------------ contagens

    /** Quantos eventos de cada tipo há no período exibido (cancelados não contam). */
    private function contagens(Collection $porDia, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $contagens = array_fill_keys(array_keys(AgendaEvento::TIPOS), 0);

        foreach ($porDia as $chave => $ocorrencias) {
            if ($chave < $de->format('Y-m-d') || $chave > $ate->format('Y-m-d')) {
                continue;
            }
            foreach ($ocorrencias as $o) {
                if (! $o->cancelada()) {
                    $contagens[$o->evento->tipo]++;
                }
            }
        }

        return $contagens;
    }

    /** Tipos presentes no dia, na ordem fixa das cores (para os pontinhos). */
    private function tiposDoDia(Collection $ocorrencias): array
    {
        $presentes = $ocorrencias->reject->cancelada()->map(fn (Ocorrencia $o) => $o->evento->tipo)->unique();

        return array_values(array_filter(array_keys(AgendaEvento::TIPOS), fn ($tipo) => $presentes->contains($tipo)));
    }

    // ------------------------------------------------- grade (dia e semana)

    private function grade(array $colunas, Collection $porDia, Collection $feriados, CarbonImmutable $hoje, CarbonImmutable $agora, int $destinatarios): array
    {
        $inicio = self::HORA_INICIAL;
        $fim = self::HORA_FINAL;
        $dias = [];

        foreach ($colunas as $dia) {
            $chave = $dia->format('Y-m-d');
            $todas = $porDia[$chave] ?? collect();
            $comHora = $todas->reject->semHorario()->values();

            // A grade cresce para caber o que começa antes das 8h ou termina depois das 18h.
            foreach ($comHora as $o) {
                $ini = $o->minutoInicio();
                $inicio = min($inicio, intdiv($ini, 60));
                $fim = max($fim, (int) ceil(($ini + max($o->evento->duracao(), self::MINUTOS_MINIMOS)) / 60));
            }

            $dias[] = [
                'data' => $dia,
                'hoje' => $dia->equalTo($hoje),
                'feriados' => $feriados[$chave] ?? collect(),
                'diaTodo' => $todas->filter->semHorario()->values(),
                'comHora' => $comHora,
            ];
        }

        $fim = min(24, $fim);
        $porMinuto = self::ALTURA_HORA / 60;

        foreach ($dias as &$dia) {
            $dia['blocos'] = array_map(function (array $b) use ($inicio, $porMinuto, $destinatarios) {
                return [
                    'o' => $b['o'],
                    'topo' => round(($b['inicio'] - $inicio * 60) * $porMinuto, 1),
                    'altura' => round(($b['fim'] - $b['inicio']) * $porMinuto - 3, 1),
                    'esquerda' => round($b['coluna'] / $b['colunas'] * 100, 3),
                    'largura' => round(100 / $b['colunas'], 3),
                    'curto' => $b['o']->evento->tipo !== AgendaEvento::COMPROMISSO,
                    'subtitulo' => $b['o']->subtitulo($destinatarios),
                ];
            }, $this->encaixar($dia['comHora']));
            unset($dia['comHora']);
        }
        unset($dia);

        $minutoAgora = $agora->hour * 60 + $agora->minute;

        return [
            'dias' => $dias,
            'horas' => range($inicio, $fim - 1),
            'inicio' => $inicio,
            'altura' => ($fim - $inicio) * self::ALTURA_HORA,
            // Linha de agora: só quando hoje está na tela e a hora cabe na grade.
            'agora' => $minutoAgora >= $inicio * 60 && $minutoAgora <= $fim * 60
                ? ['topo' => round(($minutoAgora - $inicio * 60) * $porMinuto, 1), 'hora' => $agora->format('H:i')]
                : null,
            'temDiaTodo' => collect($dias)->contains(fn ($d) => $d['diaTodo']->isNotEmpty() || $d['feriados']->isNotEmpty()),
        ];
    }

    /**
     * Divide a largura entre os eventos que se sobrepõem no mesmo dia.
     *
     * @return array<int, array{o: Ocorrencia, inicio: int, fim: int, coluna: int, colunas: int}>
     */
    private function encaixar(Collection $ocorrencias): array
    {
        $blocos = $ocorrencias->map(fn (Ocorrencia $o) => [
            'o' => $o,
            'inicio' => $o->minutoInicio(),
            'fim' => $o->minutoInicio() + max($o->evento->duracao(), self::MINUTOS_MINIMOS),
            'coluna' => 0,
            'colunas' => 1,
        ])->sortBy([['inicio', 'asc'], ['fim', 'desc']])->values()->all();

        $grupo = [];      // índices dos blocos que se tocam
        $fimDoGrupo = -1;
        $fechar = function () use (&$blocos, &$grupo) {
            $colunas = $grupo ? max(array_map(fn ($i) => $blocos[$i]['coluna'], $grupo)) + 1 : 1;
            foreach ($grupo as $i) {
                $blocos[$i]['colunas'] = $colunas;
            }
            $grupo = [];
        };

        foreach ($blocos as $i => $bloco) {
            if ($bloco['inicio'] >= $fimDoGrupo) {
                $fechar();
                $fimDoGrupo = -1;
            }

            // Primeira coluna livre entre os blocos do grupo que ainda não terminaram.
            $ocupadas = [];
            foreach ($grupo as $j) {
                if ($blocos[$j]['fim'] > $bloco['inicio']) {
                    $ocupadas[$blocos[$j]['coluna']] = true;
                }
            }
            $coluna = 0;
            while (isset($ocupadas[$coluna])) {
                $coluna++;
            }

            $blocos[$i]['coluna'] = $coluna;
            $grupo[] = $i;
            $fimDoGrupo = max($fimDoGrupo, $bloco['fim']);
        }
        $fechar();

        return $blocos;
    }

    // ------------------------------------------------------- mês e mini mês

    /**
     * Semanas de domingo a sábado. No mini calendário cada dia leva só as
     * marcas; no mês inteiro ($completo) leva também os eventos.
     */
    private function semanasDoMes(
        CarbonImmutable $mes, Collection $porDia, Collection $feriados, CarbonImmutable $hoje,
        CarbonImmutable $periodoDe, CarbonImmutable $periodoAte, bool $completo = false,
    ): array {
        [$de, $ate] = $this->limitesDoMes($mes);
        $semanas = [];

        for ($dia = $de; $dia->lte($ate); $dia = $dia->addDay()) {
            $chave = $dia->format('Y-m-d');
            $ocorrencias = ($porDia[$chave] ?? collect())->reject->cancelada()->values();

            $celula = [
                'data' => $dia,
                'noMes' => $dia->month === $mes->month,
                'hoje' => $dia->equalTo($hoje),
                'noPeriodo' => $dia->betweenIncluded($periodoDe, $periodoAte),
                'temData' => $ocorrencias->contains(fn (Ocorrencia $o) => $o->evento->tipo === AgendaEvento::DATA_IMPORTANTE),
            ];

            if ($completo) {
                $faixas = collect($feriados[$chave] ?? [])
                    ->map(fn (Feriado $f) => ['feriado' => $f])
                    ->concat($ocorrencias->filter->semHorario()->map(fn (Ocorrencia $o) => ['o' => $o]));
                $linhas = $faixas->concat($ocorrencias->reject->semHorario()->map(fn (Ocorrencia $o) => ['o' => $o]))->values();

                $celula['linhas'] = $linhas->take(self::LINHAS_DO_MES);
                $celula['mais'] = max(0, $linhas->count() - self::LINHAS_DO_MES);
                $celula['tipos'] = $this->tiposDoDia($ocorrencias);
                $celula['feriado'] = isset($feriados[$chave]);
            }

            $semanas[intdiv((int) $de->diffInDays($dia), 7)][] = $celula;
        }

        return $semanas;
    }

    // ------------------------------------------------------ lista (celular)

    private function lista(CarbonImmutable $dia, Collection $porDia, Collection $feriados, CarbonImmutable $hoje, CarbonImmutable $agora, int $destinatarios): array
    {
        $chave = $dia->format('Y-m-d');
        $todas = $porDia[$chave] ?? collect();
        $minutoAgora = $agora->hour * 60 + $agora->minute;
        $ehHoje = $dia->equalTo($hoje);
        $itens = [];
        $linhaPosta = false;

        foreach ($todas->reject->semHorario()->values() as $o) {
            // A linha de agora entra antes do primeiro evento que ainda não começou.
            if ($ehHoje && ! $linhaPosta && $o->minutoInicio() > $minutoAgora) {
                $itens[] = ['agora' => $agora->format('H:i')];
                $linhaPosta = true;
            }
            $itens[] = ['o' => $o, 'subtitulo' => $o->subtitulo($destinatarios)];
        }

        if ($ehHoje && ! $linhaPosta && $itens) {
            $itens[] = ['agora' => $agora->format('H:i')];
        }

        return [
            'data' => $dia,
            'hoje' => $ehHoje,
            'feriados' => $feriados[$chave] ?? collect(),
            'diaTodo' => $todas->filter->semHorario()->values(),
            'itens' => $itens,
        ];
    }
}
