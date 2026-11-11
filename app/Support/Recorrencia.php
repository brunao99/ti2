<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Regra de repetição da agenda: a partir de uma data, de quanto em quanto
 * tempo o evento volta e quando para.
 *
 * As datas não ficam gravadas; são calculadas daqui sempre que a agenda é
 * montada. A n-ésima data sai direto da primeira (e não da anterior), então
 * um evento mensal no dia 31 cai no último dia dos meses curtos e volta ao
 * dia 31 nos seguintes.
 */
final class Recorrencia
{
    /** Opções da lista suspensa "Repetir", na ordem da tela. */
    public const OPCOES = [
        'nao'        => 'Não repete',
        'semanal'    => 'Semanal',
        'quinzenal'  => 'Quinzenal',
        'mensal'     => 'Mensal',
        'bimestral'  => 'Bimestral',
        'trimestral' => 'Trimestral',
    ];

    /** [unidade, passo] de cada opção. */
    private const PASSO = [
        'semanal'    => ['semanas', 1],
        'quinzenal'  => ['semanas', 2],
        'mensal'     => ['meses', 1],
        'bimestral'  => ['meses', 2],
        'trimestral' => ['meses', 3],
    ];

    /** Trava de segurança para repetições "sem fim" e contagens. */
    private const LIMITE = 2000;

    public function __construct(
        public readonly CarbonImmutable $inicio,
        public readonly string $repetir = 'nao',
        public readonly string $termina = 'nunca',
        public readonly ?CarbonImmutable $terminaEm = null,
        public readonly ?int $vezes = null,
    ) {
    }

    public function repete(): bool
    {
        return isset(self::PASSO[$this->repetir]);
    }

    /** Data da ocorrência de índice $n (0 é a primeira). */
    public function data(int $n): CarbonImmutable
    {
        if ($n === 0 || ! $this->repete()) {
            return $this->inicio;
        }

        [$unidade, $passo] = self::PASSO[$this->repetir];

        return $unidade === 'semanas'
            ? $this->inicio->addWeeks($n * $passo)
            : $this->inicio->addMonthsNoOverflow($n * $passo);
    }

    /** Quantas ocorrências a regra tem; null quando não tem fim. */
    public function total(): ?int
    {
        if (! $this->repete()) {
            return 1;
        }

        if ($this->termina === 'vezes') {
            return max(1, (int) $this->vezes);
        }

        if ($this->termina === 'data' && $this->terminaEm) {
            $n = 0;
            while ($n < self::LIMITE && $this->data($n + 1)->lte($this->terminaEm)) {
                $n++;
            }

            return $n + 1;
        }

        return null;
    }

    /** Última data da regra; null quando não tem fim. */
    public function ultima(): ?CarbonImmutable
    {
        $total = $this->total();

        return $total === null ? null : $this->data($total - 1);
    }

    /**
     * Ocorrências entre duas datas (inclusive), como [índice => data].
     *
     * @return array<int, CarbonImmutable>
     */
    public function entre(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $de = $de->startOfDay();
        $ate = $ate->startOfDay();
        $total = $this->total();
        $datas = [];

        for ($n = $this->primeiroIndiceApartirDe($de); $n < self::LIMITE; $n++) {
            if ($total !== null && $n >= $total) {
                break;
            }

            $data = $this->data($n);

            if ($data->gt($ate)) {
                break;
            }

            if ($data->gte($de)) {
                $datas[$n] = $data;
            }
        }

        return $datas;
    }

    /**
     * As primeiras $quantas datas a partir de $de.
     *
     * @return array<int, CarbonImmutable>
     */
    public function proximas(CarbonImmutable $de, int $quantas): array
    {
        $de = $de->startOfDay();
        $total = $this->total();
        $datas = [];

        for ($n = $this->primeiroIndiceApartirDe($de); $n < self::LIMITE && count($datas) < $quantas; $n++) {
            if ($total !== null && $n >= $total) {
                break;
            }

            $data = $this->data($n);

            if ($data->gte($de)) {
                $datas[$n] = $data;
            }
        }

        return $datas;
    }

    /** Pula direto para perto de $de, em vez de contar desde a primeira data. */
    private function primeiroIndiceApartirDe(CarbonImmutable $de): int
    {
        if (! $this->repete() || $de->lte($this->inicio)) {
            return 0;
        }

        [$unidade, $passo] = self::PASSO[$this->repetir];

        $n = $unidade === 'semanas'
            ? intdiv((int) $this->inicio->diffInDays($de), 7 * $passo)
            : intdiv(max(0, ((int) $this->inicio->diffInMonths($de)) - 1), $passo);

        return max(0, $n - 1);
    }
}
