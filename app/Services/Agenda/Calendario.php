<?php

namespace App\Services\Agenda;

use App\Models\AgendaEvento;
use App\Models\AgendaOcorrencia;
use App\Models\Feriado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monta a agenda de um período: abre cada evento nas datas da sua regra de
 * repetição e junta o que foi registrado para cada data (cancelamento, envio).
 */
class Calendario
{
    /** Quantos dias um prazo pode recuar até achar um dia útil. */
    private const FOLGA = 10;

    /**
     * Ocorrências que aparecem entre $de e $ate (inclusive), na ordem do dia:
     * primeiro as sem horário, depois por hora.
     *
     * @return Collection<int, Ocorrencia>
     */
    public function ocorrencias(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        $de = $de->startOfDay();
        $ate = $ate->startOfDay();
        $limite = $ate->addDays(self::FOLGA);

        $eventos = AgendaEvento::query()
            ->visiveisPara($usuario)
            ->with(['postagem', 'modelo', 'autor', 'cliente', 'participantes'])
            ->whereDate('data', '<=', $limite)
            ->where(fn ($q) => $q->where('repetir', '!=', 'nao')->orWhereDate('data', '>=', $de))
            ->get();

        return $this->abrir($eventos, $de, $ate)
            ->sortBy(fn (Ocorrencia $o) => [
                $o->dia->format('Y-m-d'),
                $o->semHorario() ? 0 : 1,
                $o->evento->hora_inicio ?? '',
                $o->evento->id,
            ])
            ->values();
    }

    /** Uma ocorrência pela data da regra; null se a data não pertence ao evento. */
    public function ocorrencia(AgendaEvento $evento, CarbonImmutable $data): ?Ocorrencia
    {
        $evento->loadMissing(['postagem', 'modelo', 'autor', 'cliente', 'participantes']);

        return $this->abrir(collect([$evento]), $data->subDays(self::FOLGA), $data, soNaData: $data)->first();
    }

    /**
     * Próximos envios de alerta ainda programados, do mais próximo ao mais distante.
     *
     * @return Collection<int, Ocorrencia>
     */
    public function proximosAlertas(User $usuario, int $quantos = 3): Collection
    {
        $agora = AgendaEvento::agora();
        $hoje = AgendaEvento::hoje();

        $eventos = AgendaEvento::query()
            ->visiveisPara($usuario)
            ->where('tipo', AgendaEvento::ALERTA)
            ->with(['postagem', 'modelo', 'autor'])
            ->get();

        $registros = $this->registros($eventos, $hoje, null);
        $proximos = collect();

        foreach ($eventos as $evento) {
            $regra = $evento->recorrencia();
            $total = $regra->total();

            // Alguns a mais que o pedido: parte pode estar cancelada ou já enviada.
            foreach ($regra->proximas($hoje, $quantos + 6) as $n => $data) {
                $ocorrencia = new Ocorrencia($evento, $data, $data, $n, $total,
                    $registros[$evento->id . ':' . $data->format('Y-m-d')] ?? null, $agora);

                if ($ocorrencia->programado() && $ocorrencia->instante()->gte($agora)) {
                    $proximos->push($ocorrencia);
                }
            }
        }

        return $proximos->sortBy(fn (Ocorrencia $o) => $o->instante()->getTimestamp())->take($quantos)->values();
    }

    /**
     * Quem recebe um alerta hoje: [autorizados, sem autorização].
     * A lista é refeita na hora de cada envio; este número é a previsão.
     *
     * @return array{0: int, 1: int}
     */
    public function destinatarios(): array
    {
        $alcancaveis = User::alcancaveisPorAlerta()->get();
        $aptos = $alcancaveis->filter->autorizouAlertas()->count();

        return [$aptos, $alcancaveis->count() - $aptos];
    }

    /**
     * @param  Collection<int, AgendaEvento>  $eventos
     * @return Collection<int, Ocorrencia>
     */
    private function abrir(Collection $eventos, CarbonImmutable $de, CarbonImmutable $ate, ?CarbonImmutable $soNaData = null): Collection
    {
        $agora = AgendaEvento::agora();
        $limite = $ate->addDays(self::FOLGA);
        $registros = $this->registros($eventos, $de, $limite);
        $feriados = null;
        $ocorrencias = collect();

        foreach ($eventos as $evento) {
            $regra = $evento->recorrencia();
            $total = $regra->total();
            $ehPrazo = $evento->tipo === AgendaEvento::DATA_IMPORTANTE;

            foreach ($regra->entre($de, $ehPrazo ? $limite : $ate) as $n => $data) {
                if ($soNaData && ! $data->isSameDay($soNaData)) {
                    continue;
                }

                $dia = $data;

                // Só as repetições recuam: a primeira data foi escolhida por quem cadastrou.
                if ($ehPrazo && $n > 0) {
                    $feriados ??= Feriado::diasDeFeriado($de->subDays(self::FOLGA), $limite);
                    $dia = $this->diaUtilAnterior($data, $feriados);
                }

                if (! $soNaData && ! $dia->betweenIncluded($de, $ate)) {
                    continue;
                }

                $registro = $registros[$evento->id . ':' . $data->format('Y-m-d')] ?? null;

                // Data cancelada some da agenda; o alerta cancelado continua, apagado.
                if ($registro?->situacao === AgendaOcorrencia::CANCELADO && ! $evento->ehAlerta() && ! $soNaData) {
                    continue;
                }

                $ocorrencias->push(new Ocorrencia($evento, $data, $dia, $n, $total, $registro, $agora));
            }
        }

        return $ocorrencias;
    }

    /** @return Collection<string, AgendaOcorrencia> por "evento:Y-m-d" */
    private function registros(Collection $eventos, CarbonImmutable $de, ?CarbonImmutable $ate): Collection
    {
        if ($eventos->isEmpty()) {
            return collect();
        }

        return AgendaOcorrencia::query()
            ->with('disparo')
            ->whereIn('agenda_evento_id', $eventos->pluck('id'))
            ->whereDate('data', '>=', $de)
            ->when($ate, fn ($q) => $q->whereDate('data', '<=', $ate))
            ->get()
            ->keyBy(fn (AgendaOcorrencia $r) => $r->agenda_evento_id . ':' . $r->data->format('Y-m-d'));
    }

    /** @param array<string, true> $feriados */
    private function diaUtilAnterior(CarbonImmutable $data, array $feriados): CarbonImmutable
    {
        for ($i = 0; $i < self::FOLGA; $i++) {
            if (! $data->isWeekend() && ! isset($feriados[$data->format('Y-m-d')])) {
                break;
            }
            $data = $data->subDay();
        }

        return $data;
    }
}
