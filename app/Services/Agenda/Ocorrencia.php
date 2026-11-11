<?php

namespace App\Services\Agenda;

use App\Models\AgendaEvento;
use App\Models\AgendaOcorrencia;
use Carbon\CarbonImmutable;

/**
 * Uma data de um evento, pronta para aparecer na agenda.
 *
 * $data é a data que sai da regra de repetição e identifica a ocorrência.
 * $dia é onde ela aparece: quase sempre o mesmo dia, mas um prazo que cai em
 * fim de semana ou feriado é mostrado no dia útil anterior.
 */
final class Ocorrencia
{
    public const PROGRAMADO = 'programado';

    /** Um alerta que passou mais que isso do horário sem sair é dado como falho. */
    public const TOLERANCIA_HORAS = 24;

    public function __construct(
        public readonly AgendaEvento $evento,
        public readonly CarbonImmutable $data,
        public readonly CarbonImmutable $dia,
        public readonly int $indice,
        public readonly ?int $total,
        public readonly ?AgendaOcorrencia $registro,
        public readonly CarbonImmutable $agora,
    ) {
    }

    /** Identifica a ocorrência nos endereços: a data da regra. */
    public function chave(): string
    {
        return $this->data->format('Y-m-d');
    }

    public function instante(): CarbonImmutable
    {
        return $this->evento->instante($this->dia);
    }

    public function jaPassou(): bool
    {
        return $this->instante()->lt($this->agora);
    }

    public function cancelada(): bool
    {
        return $this->registro?->situacao === AgendaOcorrencia::CANCELADO;
    }

    /**
     * Situação do alerta programado; null para os outros tipos.
     * Enviado e falha vêm do disparo registrado na Central de alertas.
     */
    public function situacao(): ?string
    {
        if (! $this->evento->ehAlerta()) {
            return null;
        }

        return match ($this->registro?->situacao) {
            AgendaOcorrencia::CANCELADO => AgendaOcorrencia::CANCELADO,
            AgendaOcorrencia::ENVIADO => AgendaOcorrencia::ENVIADO,
            AgendaOcorrencia::FALHOU => AgendaOcorrencia::FALHOU,
            default => $this->instante()->lt($this->agora->subHours(self::TOLERANCIA_HORAS))
                ? AgendaOcorrencia::FALHOU
                : self::PROGRAMADO,
        };
    }

    public function rotuloSituacao(): ?string
    {
        return match ($this->situacao()) {
            self::PROGRAMADO => 'Programado',
            AgendaOcorrencia::ENVIADO => 'Enviado',
            AgendaOcorrencia::CANCELADO => 'Cancelado',
            AgendaOcorrencia::FALHOU => 'Falhou',
            default => null,
        };
    }

    public function programado(): bool
    {
        return $this->situacao() === self::PROGRAMADO;
    }

    /** Alerta que já saiu, falhou ou foi cancelado aparece apagado na grade. */
    public function apagada(): bool
    {
        return $this->evento->ehAlerta() && ! $this->programado();
    }

    /** Só dá para cancelar o que ainda não aconteceu. */
    public function podeCancelar(): bool
    {
        if ($this->cancelada()) {
            return false;
        }

        return $this->evento->ehAlerta() ? $this->programado() : ! $this->jaPassou();
    }

    public function podeEnviarAgora(): bool
    {
        return $this->evento->ehAlerta() && $this->programado() && $this->evento->postagem !== null;
    }

    public function semHorario(): bool
    {
        return $this->evento->semHorario();
    }

    /** Minutos desde a meia-noite, para posicionar o bloco na grade. */
    public function minutoInicio(): int
    {
        [$h, $m] = array_map('intval', explode(':', $this->evento->hora_inicio ?: '00:00'));

        return $h * 60 + $m;
    }

    /** Segunda linha do bloco: local do compromisso ou situação do alerta. */
    public function subtitulo(int $destinatarios): ?string
    {
        if (! $this->evento->ehAlerta()) {
            return $this->evento->tipo === AgendaEvento::COMPROMISSO ? $this->evento->local : $this->evento->observacao;
        }

        $disparo = $this->registro?->disparo;

        return match ($this->situacao()) {
            AgendaOcorrencia::ENVIADO => 'Enviado · ' . ($disparo?->total_enviados ?? 0)
                . (($disparo?->total_enviados ?? 0) === 1 ? ' entregue' : ' entregues'),
            AgendaOcorrencia::CANCELADO => 'Cancelado',
            AgendaOcorrencia::FALHOU => 'Falhou',
            default => 'Programado · ' . $destinatarios . ($destinatarios === 1 ? ' destinatário' : ' destinatários'),
        };
    }
}
