<?php

namespace App\Services\Agenda;

use App\Models\AgendaEvento;
use App\Support\DataPt;
use Carbon\CarbonImmutable;

/**
 * Explica em texto a repetição de um evento: é o "Resumo da repetição" da
 * janela Novo evento e a linha "Repete" do detalhe.
 */
class ResumoRepeticao
{
    /**
     * Frase do formulário, com <b> nos destaques. Null quando não repete.
     * O evento pode ser um rascunho ainda não salvo.
     */
    public function frase(AgendaEvento $evento): ?string
    {
        if (! $evento->repete() || ! $evento->data) {
            return null;
        }

        $regra = $evento->recorrencia();
        $total = $regra->total();
        $inicio = '<b>' . $evento->data->format('d/m/Y') . '</b>';
        $ritmo = $this->ritmo($evento) . $this->horario($evento);

        if ($total === null) {
            // Sem fim: nos intervalos longos, as próximas datas ajudam mais que a regra.
            $frase = in_array($evento->repetir, ['bimestral', 'trimestral'], true)
                ? "A partir de {$inicio}, {$ritmo}: " . $this->lista(array_slice($this->datas($regra, 4), 1), 'd/m') . '…'
                : "A partir de {$inicio}, {$ritmo}, sem data para terminar.";
        } elseif ($evento->termina === 'vezes') {
            $frase = "A partir de {$inicio}, {$ritmo}. <b>{$total} {$evento->unidade($total)}</b>, até "
                . $regra->ultima()->format('d/m/Y') . '.';
        } else {
            $frase = "A partir de {$inicio}, {$ritmo}, até " . $evento->termina_em->format('d/m/Y') . '. '
                . "<b>{$total} {$evento->unidade($total)}</b>: "
                . ($total <= 6
                    ? $this->lista($this->datas($regra, $total), 'd/m', ' e ')
                    : $this->lista($this->datas($regra, 4), 'd/m') . '…')
                . ($total <= 6 ? '.' : '');
        }

        if ($evento->tipo === AgendaEvento::DATA_IMPORTANTE) {
            $frase .= ' Cai em fim de semana ou feriado? Mostra no dia útil anterior.';
        }

        return $frase;
    }

    /** Linha "Repete" do detalhe: "Semanal, toda quarta às 15:30 · envio 1 de 4, até 27/11". */
    public function linha(Ocorrencia $ocorrencia): ?string
    {
        $evento = $ocorrencia->evento;

        if (! $evento->repete()) {
            return null;
        }

        $regra = $evento->recorrencia();
        $linha = \App\Support\Recorrencia::OPCOES[$evento->repetir] . ', ' . $this->ritmo($evento) . $this->horario($evento);
        $numero = $ocorrencia->indice + 1;

        return $ocorrencia->total === null
            ? "{$linha} · {$evento->unidade(1)} {$numero}, sem data para terminar"
            : "{$linha} · {$evento->unidade(1)} {$numero} de {$ocorrencia->total}, até " . $regra->ultima()->format('d/m');
    }

    /** Dica de cada opção da lista "Repetir", conforme a data escolhida. */
    public function dicas(CarbonImmutable $data): array
    {
        return [
            'nao' => '',
            'semanal' => 'toda ' . DataPt::dia($data),
            'quinzenal' => 'a cada 2 semanas',
            'mensal' => 'todo dia ' . $data->day,
            'bimestral' => 'a cada 2 meses',
            'trimestral' => 'a cada 3 meses',
        ];
    }

    private function ritmo(AgendaEvento $evento): string
    {
        $dia = DataPt::dia($evento->data);
        $artigo = in_array($evento->data->dayOfWeek, [0, 6], true) ? 'o' : 'a'; // todo sábado, toda quarta

        return match ($evento->repetir) {
            'semanal' => ($artigo === 'a' ? 'toda ' : 'todo ') . $dia,
            'quinzenal' => 'a cada 2 semanas n' . $artigo . ' ' . $dia,
            'mensal' => 'todo dia ' . $evento->data->day,
            'bimestral' => 'a cada 2 meses',
            'trimestral' => 'a cada 3 meses',
            default => '',
        };
    }

    private function horario(AgendaEvento $evento): string
    {
        if ($evento->tipo === AgendaEvento::DATA_IMPORTANTE) {
            return '';
        }

        if ($evento->tipo === AgendaEvento::COMPROMISSO) {
            if ($evento->dia_inteiro || ! $evento->hora_inicio) {
                return ', o dia inteiro';
            }

            return $evento->hora_fim
                ? ", das {$evento->hora_inicio} às {$evento->hora_fim}"
                : " às {$evento->hora_inicio}";
        }

        return $evento->hora_inicio ? " às {$evento->hora_inicio}" : '';
    }

    /** @return array<int, CarbonImmutable> as primeiras $quantas datas da regra */
    private function datas(\App\Support\Recorrencia $regra, int $quantas): array
    {
        $datas = [];
        for ($n = 0; $n < $quantas; $n++) {
            $datas[] = $regra->data($n);
        }

        return $datas;
    }

    /** @param array<int, CarbonImmutable> $datas */
    private function lista(array $datas, string $formato, ?string $ultimo = null): string
    {
        $textos = array_map(fn (CarbonImmutable $d) => $d->format($formato), $datas);

        if ($ultimo === null || count($textos) < 2) {
            return implode(', ', $textos);
        }

        $fim = array_pop($textos);

        return implode(', ', $textos) . $ultimo . $fim;
    }
}
