<?php

namespace App\Http\Controllers;

use App\Models\Postagem;
use App\Support\SanitizadorHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostagemController extends Controller
{
    /**
     * Listagem agrupada por mês — Telas 05 e 06.
     * O Client enxerga apenas publicadas; Master e ADM veem os rascunhos também.
     */
    public function index(Request $request): View
    {
        // Os tipos chegam como tipos[] das caixas marcadas. Filtramos contra a
        // lista válida para que um parâmetro inventado na URL não vire consulta.
        $tipos = array_values(array_intersect(
            (array) $request->query('tipos', []),
            Postagem::tiposValidos()
        ));

        $busca = trim((string) $request->query('busca', ''));

        $consulta = Postagem::query()
            ->with('autor')
            ->dosTipos($tipos)
            ->buscando($busca)
            ->orderByDesc('data_publicacao');

        if ($request->user()->ehClient()) {
            $consulta->publicadas();
        }

        $porMes = $consulta->get()->groupBy('mes_referencia');

        return view('postagens.index', [
            'porMes'       => $porMes,
            'tiposAtivos'  => $tipos,
            'busca'        => $busca,
        ]);
    }

    public function show(Postagem $postagem, Request $request): View
    {
        // Rascunho é invisível para o Client, mesmo com o link direto.
        if ($request->user()->ehClient() && $postagem->ehRascunho()) {
            abort(404);
        }

        return view('postagens.show', ['postagem' => $postagem->load('anexos', 'autor')]);
    }

    public function create(): View
    {
        return view('postagens.form', ['postagem' => new Postagem()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $postagem = Postagem::create($this->validado($request) + [
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('postagens.show', $postagem)
            ->with('ok', 'Publicação criada.');
    }

    public function edit(Postagem $postagem): View
    {
        return view('postagens.form', ['postagem' => $postagem]);
    }

    public function update(Request $request, Postagem $postagem): RedirectResponse
    {
        $postagem->update($this->validado($request));

        return redirect()
            ->route('postagens.show', $postagem)
            ->with('ok', 'Publicação atualizada.');
    }

    public function destroy(Postagem $postagem): RedirectResponse
    {
        $postagem->delete();

        return redirect()
            ->route('postagens.index')
            ->with('ok', 'Publicação removida.');
    }

    // ------------------------------------------------------------- validação

    private function validado(Request $request): array
    {
        $dados = $request->validate([
            'tipo'            => ['required', Rule::in(Postagem::tiposValidos())],
            'titulo'          => ['required', 'string', 'max:255'],
            'texto'           => ['nullable', 'string', 'max:200000'],
            'data_publicacao' => ['required', 'date'],
            'mes_referencia'  => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'situacao'        => ['required', 'in:rascunho,publicado'],
        ], [
            'titulo.required'          => 'O título é obrigatório.',
            'data_publicacao.required' => 'Informe a data da publicação.',
            'tipo.required'            => 'Escolha o tipo de conteúdo.',
            'texto.max'                => 'O texto da publicação ficou longo demais.',
        ]);

        // O texto chega como HTML do editor. Gravar cru abriria XSS para quem
        // montasse o POST na mão, então a limpeza é feita aqui — na entrada,
        // não na exibição, para que o banco só guarde conteúdo já confiável.
        $dados['texto'] = SanitizadorHtml::limpar($dados['texto'] ?? '');

        return $dados;
    }
}
