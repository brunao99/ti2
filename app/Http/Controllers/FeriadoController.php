<?php

namespace App\Http\Controllers;

use App\Models\AgendaEvento;
use App\Models\Feriado;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Calendário de feriados: feriados e pontos facultativos que aparecem na
 * faixa "dia todo" da agenda e contam no ajuste dos prazos.
 */
class FeriadoController extends Controller
{
    public function index(Request $request): View
    {
        $hoje = AgendaEvento::hoje();
        $todos = Feriado::orderBy('data')->get();

        // Do primeiro ano cadastrado até o ano que vem: as datas que repetem valem em todos.
        $primeiro = $todos->min(fn (Feriado $f) => $f->data->year) ?? $hoje->year;
        $ultimo = max($hoje->year + 1, $todos->max(fn (Feriado $f) => $f->data->year) ?? $hoje->year);
        $anos = range(min($primeiro, $hoje->year), $ultimo);

        $ano = (int) $request->query('ano', $hoje->year);
        $ano = in_array($ano, $anos, true) ? $ano : $hoje->year;
        $tipo = array_key_exists($request->query('tipo'), Feriado::TIPOS) ? $request->query('tipo') : '';
        $abrangencia = array_key_exists($request->query('abrangencia'), Feriado::ABRANGENCIAS) ? $request->query('abrangencia') : '';
        $busca = trim((string) $request->query('busca'));

        $datas = $todos
            ->filter(fn (Feriado $f) => $f->valeEm($ano))
            ->filter(fn (Feriado $f) => $tipo === '' || $f->tipo === $tipo)
            ->filter(fn (Feriado $f) => $abrangencia === '' || $f->abrangencia === $abrangencia)
            ->filter(fn (Feriado $f) => $busca === '' || str_contains(self::semAcento($f->descricao), self::semAcento($busca)))
            ->map(fn (Feriado $f) => ['feriado' => $f, 'data' => $f->dataEm($ano)])
            ->sortBy(fn (array $d) => $d['data']->format('Y-m-d') . str_pad((string) $d['feriado']->id, 8, '0', STR_PAD_LEFT))
            ->values();

        $proxima = $datas->first(fn (array $d) => $d['data']->gte($hoje));

        return view('feriados.index', [
            'hoje' => $hoje,
            'ano' => $ano,
            'anos' => $anos,
            'tipo' => $tipo,
            'abrangencia' => $abrangencia,
            'busca' => $busca,
            'porMes' => $datas->groupBy(fn (array $d) => $d['data']->month),
            'proxima' => $proxima,
            'resumo' => [
                'feriados' => $datas->filter(fn ($d) => $d['feriado']->ehFeriado())->count(),
                'pontos' => $datas->reject(fn ($d) => $d['feriado']->ehFeriado())->count(),
                'restantes' => $datas->filter(fn ($d) => $d['data']->gte($hoje))->count(),
            ],
            'filtros' => array_filter(['ano' => $ano, 'tipo' => $tipo, 'abrangencia' => $abrangencia, 'busca' => $busca]),
        ]);
    }

    public function criar(Request $request): View
    {
        $feriado = new Feriado([
            'data' => AgendaEvento::hoje(),
            'tipo' => 'feriado',
            'abrangencia' => 'nacional',
            'repete_todo_ano' => true,
        ]);

        return $this->formulario($request, $feriado);
    }

    public function editar(Request $request, Feriado $feriado): View
    {
        return $this->formulario($request, $feriado);
    }

    public function salvar(Request $request): JsonResponse|RedirectResponse
    {
        $feriado = Feriado::create($this->validar($request));

        return $this->depoisDeSalvar($request, $feriado, 'Feriado salvo. Ele já aparece no calendário e na agenda.');
    }

    public function atualizar(Request $request, Feriado $feriado): JsonResponse|RedirectResponse
    {
        $feriado->update($this->validar($request, $feriado));

        return $this->depoisDeSalvar($request, $feriado, 'Feriado atualizado.');
    }

    public function excluir(Request $request, Feriado $feriado): RedirectResponse
    {
        $ano = $request->integer('ano') ?: $feriado->data->year;
        $feriado->delete();

        return redirect()->route('feriados.index', ['ano' => $ano])->with('ok', 'Data removida do calendário de feriados.');
    }

    /**
     * Copia para o ano seguinte as datas que valem só no ano exibido (as
     * móveis). As que repetem todo ano já valem lá e não são duplicadas.
     */
    public function copiar(Request $request): RedirectResponse
    {
        $ano = $request->integer('ano') ?: AgendaEvento::hoje()->year;
        $destino = $ano + 1;
        $copiadas = 0;

        $doDestino = Feriado::whereYear('data', $destino)->get();

        foreach (Feriado::whereYear('data', $ano)->where('repete_todo_ano', false)->orderBy('data')->get() as $feriado) {
            $jaExiste = $doDestino->contains(fn (Feriado $f) => $f->descricao === $feriado->descricao
                && $f->abrangencia === $feriado->abrangencia && $f->local === $feriado->local
                && $f->data->format('m-d') === $feriado->data->format('m-d'));

            if ($jaExiste) {
                continue;
            }

            Feriado::create(['data' => $feriado->dataEm($destino)] + $feriado->only(
                ['descricao', 'tipo', 'abrangencia', 'local', 'expediente', 'repete_todo_ano']
            ));
            $copiadas++;
        }

        $mensagem = $copiadas === 0
            ? "Nada para copiar: as datas de {$ano} que repetem todo ano já valem em {$destino}."
            : ($copiadas === 1 ? '1 data copiada' : "{$copiadas} datas copiadas")
                . " para {$destino}, no mesmo dia e mês. Confira as datas móveis (Carnaval, Paixão de Cristo, Corpus Christi), que mudam a cada ano.";

        return redirect()->route('feriados.index', ['ano' => $destino])->with('ok', $mensagem);
    }

    private function formulario(Request $request, Feriado $feriado): View
    {
        $dados = [
            'feriado' => $feriado,
            'municipios' => Feriado::municipios(),
            'ano' => $request->integer('ano') ?: null,
        ];

        return $request->ajax()
            ? view('feriados._form', $dados + ['modal' => true])
            : view('feriados.form', $dados);
    }

    private function validar(Request $request, ?Feriado $atual = null): array
    {
        $abrangencia = $request->input('abrangencia');

        $dados = $request->validate([
            'descricao' => ['required', 'string', 'max:150'],
            'data' => ['required', 'date_format:Y-m-d'],
            'tipo' => ['required', Rule::in(array_keys(Feriado::TIPOS))],
            'abrangencia' => ['required', Rule::in(array_keys(Feriado::ABRANGENCIAS))],
            'local' => [
                Rule::requiredIf(in_array($abrangencia, ['estadual', 'municipal'], true)), 'nullable', 'string', 'max:120',
                $abrangencia === 'estadual' ? Rule::in(Feriado::ESTADOS) : 'string',
            ],
            'expediente' => ['nullable', 'string', 'max:150'],
            'repete_todo_ano' => ['nullable', 'boolean'],
        ], [
            'descricao.required' => 'Informe a descrição do feriado.',
            'descricao.max' => 'A descrição pode ter no máximo 150 caracteres.',
            'data.*' => 'Informe uma data válida.',
            'tipo.*' => 'Escolha se é feriado ou ponto facultativo.',
            'abrangencia.*' => 'Escolha a abrangência.',
            'local.required' => $abrangencia === 'estadual' ? 'Escolha o estado.' : 'Escolha o município.',
            'local.in' => 'Escolha um estado da lista.',
            'local.max' => 'O local pode ter no máximo 120 caracteres.',
            'expediente.max' => 'A observação de expediente pode ter no máximo 150 caracteres.',
        ]);

        $dados['local'] = $dados['abrangencia'] === 'nacional' ? null : $dados['local'];
        $dados['expediente'] = $dados['expediente'] ?? null;
        $dados['repete_todo_ano'] = $request->boolean('repete_todo_ano');

        // Não pode repetir outra data igual na mesma abrangência.
        $data = CarbonImmutable::parse($dados['data']);
        $igual = Feriado::query()
            ->when($atual, fn ($q) => $q->whereKeyNot($atual->id))
            ->where('abrangencia', $dados['abrangencia'])
            ->get()
            ->first(fn (Feriado $f) => $f->local === $dados['local'] && (
                $f->data->equalTo($data)
                || (($f->repete_todo_ano || $dados['repete_todo_ano']) && $f->data->format('m-d') === $data->format('m-d'))
            ));

        if ($igual) {
            throw ValidationException::withMessages([
                'data' => "Já existe uma data nesse dia com a mesma abrangência: {$igual->descricao}.",
            ]);
        }

        return $dados;
    }

    private function depoisDeSalvar(Request $request, Feriado $feriado, string $mensagem): JsonResponse|RedirectResponse
    {
        $destino = route('feriados.index', ['ano' => $request->integer('ano') ?: $feriado->data->year]);
        $request->session()->flash('ok', $mensagem);

        return $request->expectsJson()
            ? response()->json(['ir' => $destino])
            : redirect($destino);
    }

    private static function semAcento(string $texto): string
    {
        return mb_strtolower(strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ú' => 'u', 'ç' => 'c', 'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'É' => 'e', 'Ê' => 'e',
            'Í' => 'i', 'Ó' => 'o', 'Õ' => 'o', 'Ô' => 'o', 'Ú' => 'u', 'Ç' => 'c',
        ]));
    }
}
