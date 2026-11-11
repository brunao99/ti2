<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Feriado extends Model
{
    public const TIPOS = [
        'feriado'           => 'Feriado',
        'ponto_facultativo' => 'Ponto facultativo',
    ];

    public const ABRANGENCIAS = [
        'nacional'  => 'Nacional',
        'estadual'  => 'Estadual',
        'municipal' => 'Municipal',
    ];

    public const ESTADOS = [
        'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal', 'Espírito Santo',
        'Goiás', 'Maranhão', 'Mato Grosso', 'Mato Grosso do Sul', 'Minas Gerais', 'Pará', 'Paraíba',
        'Paraná', 'Pernambuco', 'Piauí', 'Rio de Janeiro', 'Rio Grande do Norte', 'Rio Grande do Sul',
        'Rondônia', 'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
    ];

    /** Sede da Algoritmos e Dados: primeira opção de município. */
    public const MUNICIPIO_SEDE = 'Belo Horizonte/MG';

    protected $fillable = ['descricao', 'data', 'tipo', 'abrangencia', 'local', 'expediente', 'repete_todo_ano'];

    protected function casts(): array
    {
        return [
            'data' => 'immutable_date',
            'repete_todo_ano' => 'boolean',
        ];
    }

    /**
     * Municípios oferecidos no cadastro: a sede e as prefeituras atendidas
     * (o nome do usuário cliente é o município, como no relatório).
     *
     * @return array<int, string>
     */
    public static function municipios(): array
    {
        return collect([self::MUNICIPIO_SEDE])
            ->merge(User::where('perfil', User::CLIENT)->orderBy('nome')->pluck('nome'))
            ->merge(self::where('abrangencia', 'municipal')->whereNotNull('local')->pluck('local'))
            ->unique()
            ->values()
            ->all();
    }

    public function ehFeriado(): bool
    {
        return $this->tipo === 'feriado';
    }

    /** A data vale neste ano? As que repetem valem do ano do cadastro em diante. */
    public function valeEm(int $ano): bool
    {
        return $this->repete_todo_ano ? $this->data->year <= $ano : $this->data->year === $ano;
    }

    /** O dia deste feriado no ano pedido (29/02 cai em 28/02 nos anos comuns). */
    public function dataEm(int $ano): CarbonImmutable
    {
        if ($this->data->year === $ano) {
            return $this->data;
        }

        $dia = min($this->data->day, CarbonImmutable::create($ano, $this->data->month, 1)->daysInMonth);

        return CarbonImmutable::create($ano, $this->data->month, $dia);
    }

    /** Etiqueta da coluna Tipo: "Nacional", "Municipal · Belo Horizonte", "Ponto facultativo". */
    public function etiqueta(): string
    {
        if (! $this->ehFeriado()) {
            return 'Ponto facultativo';
        }

        $nome = self::ABRANGENCIAS[$this->abrangencia] ?? 'Feriado';
        $local = $this->localCurto();

        return $local ? "{$nome} · {$local}" : $nome;
    }

    /** Classe de cor da etiqueta. */
    public function classeEtiqueta(): string
    {
        return $this->ehFeriado() ? 'fer-' . $this->abrangencia : 'fer-facultativo';
    }

    /** "Belo Horizonte/MG" → "Belo Horizonte". */
    public function localCurto(): ?string
    {
        return $this->local ? trim(explode('/', $this->local)[0]) : null;
    }

    /** Texto do feriado na faixa "dia todo" da agenda: "Finados — feriado nacional". */
    public function descricaoNaAgenda(): string
    {
        $tipo = $this->ehFeriado()
            ? 'feriado ' . mb_strtolower(self::ABRANGENCIAS[$this->abrangencia] ?? '')
            : 'ponto facultativo';

        return $this->descricao . ' — ' . trim($tipo);
    }

    /**
     * Feriados e pontos facultativos de um intervalo, por dia ("Y-m-d").
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function noIntervalo(CarbonImmutable $de, CarbonImmutable $ate): Collection
    {
        $de = $de->startOfDay();
        $ate = $ate->startOfDay();
        $porDia = collect();

        foreach (self::orderBy('data')->get() as $feriado) {
            for ($ano = $de->year; $ano <= $ate->year; $ano++) {
                if (! $feriado->valeEm($ano)) {
                    continue;
                }

                $dia = $feriado->dataEm($ano);

                if ($dia->betweenIncluded($de, $ate)) {
                    $chave = $dia->format('Y-m-d');
                    $porDia[$chave] = ($porDia[$chave] ?? collect())->push($feriado);
                }
            }
        }

        return $porDia->sortKeys();
    }

    /**
     * Dias não úteis por feriado (ponto facultativo não conta), como
     * conjunto de "Y-m-d". Usado para puxar prazos para o dia útil anterior.
     *
     * @return array<string, true>
     */
    public static function diasDeFeriado(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return self::noIntervalo($de, $ate)
            ->filter(fn (Collection $doDia) => $doDia->contains(fn (self $f) => $f->ehFeriado()))
            ->map(fn () => true)
            ->all();
    }
}
