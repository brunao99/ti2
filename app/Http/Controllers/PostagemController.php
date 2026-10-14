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

        [$campo, $direcao] = $this->ordenacao($request);

        $consulta = Postagem::query()
            ->with('autor')
            ->dosTipos($tipos)
            ->buscando($busca);

        // A listagem é agrupada por mês, então a ordenação tem de respeitar
        // isso. Ordenar por data já coloca os meses na ordem certa; para os
        // outros campos, fixamos o mês primeiro e ordenamos dentro dele —
        // senão publicações de meses diferentes se intercalariam e os
        // cabeçalhos de mês apareceriam repetidos.
        if ($campo === 'data_publicacao') {
            $consulta->orderBy('data_publicacao', $direcao);
        } else {
            $consulta->orderByDesc('mes_referencia')
                     ->orderBy($campo, $direcao)
                     ->orderByDesc('data_publicacao');
        }

        if ($request->user()->ehClient()) {
            $consulta->publicadas();
        }

        $porMes = $consulta->get()->groupBy('mes_referencia');

        return view('postagens.index', [
            'porMes'       => $porMes,
            'tiposAtivos'  => $tipos,
            'busca'        => $busca,
            'ordem'        => $campo,
            'direcao'      => $direcao,
        ]);
    }

    /**
     * Campo e direção da ordenação, vindos da URL.
     *
     * A lista de permissão não é formalidade: o nome do campo entra direto no
     * ORDER BY, e aceitar o que vier da query string seria injeção de SQL.
     * Qualquer valor fora dela cai no padrão.
     *
     * @return array{0: string, 1: string}
     */
    private function ordenacao(Request $request): array
    {
        $permitidos = ['tipo', 'titulo', 'data_publicacao', 'situacao'];

        $campo = (string) $request->query('ordem', 'data_publicacao');
        if (! in_array($campo, $permitidos, true)) {
            $campo = 'data_publicacao';
        }

        $direcao = strtolower((string) $request->query('dir', 'desc'));
        if (! in_array($direcao, ['asc', 'desc'], true)) {
            $direcao = 'desc';
        }

        return [$campo, $direcao];
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
        // Link colado sem protocolo (instagram.com/p/…) ganha https://, senão
        // a validação recusaria e o link não abriria no relatório.
        $link = trim((string) $request->input('link'));
        if ($link !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $link)) {
            $link = 'https://' . $link;
        }
        $request->merge(['link' => $link !== '' ? $link : null]);

        $dados = $request->validate([
            'tipo'            => ['required', Rule::in(Postagem::tiposValidos())],
            'titulo'          => ['required', 'string', 'max:255'],
            'texto'           => ['nullable', 'string', 'max:200000'],
            'data_publicacao' => ['required', 'date'],
            'mes_referencia'  => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'situacao'        => ['required', 'in:rascunho,publicado'],
            'link'            => ['nullable', 'url:http,https', 'max:2048'],
        ], [
            'titulo.required'          => 'O título é obrigatório.',
            'data_publicacao.required' => 'Informe a data da publicação.',
            'tipo.required'            => 'Escolha o tipo de conteúdo.',
            'texto.max'                => 'O texto da publicação ficou longo demais.',
            'link.url'                 => 'Informe um link válido, por exemplo https://www.gov.br/...',
            'link.max'                 => 'O link ficou longo demais.',
        ]);

        // O texto chega como HTML do editor. Gravar cru abriria XSS para quem
        // montasse o POST na mão, então a limpeza é feita aqui — na entrada,
        // não na exibição, para que o banco só guarde conteúdo já confiável.
        $dados['texto'] = SanitizadorHtml::limpar($dados['texto'] ?? '');

        return $dados;
    }
}
