<?php

namespace App\Http\Controllers;

use App\Http\Requests\GerarRelatorioRequest;
use App\Models\User;
use App\Services\Relatorios\PdfRelatorio;
use App\Services\Relatorios\PlanilhaRelatorio;
use App\Services\Relatorios\RelatorioPrefeitura;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Relatório da prefeitura: publicações do período e alertas entregues ao
 * cliente, em XLSX ou PDF. Os dados vêm de RelatorioPrefeitura.
 */
class RelatorioController extends Controller
{
    /**
     * Formulário de geração. Pela lista de publicações abre numa janela
     * (pedido via fetch); direto pelo endereço, ou no menu Mais, é a página.
     */
    public function index(Request $request): View
    {
        $dados = $this->dadosDoFormulario();

        return $request->ajax()
            ? view('relatorios._form', $dados + ['modal' => true])
            : view('relatorios.index', $dados);
    }

    /** Números da prévia, atualizados enquanto o formulário muda. */
    public function previa(Request $request): JsonResponse
    {
        $inicio = $this->data($request->query('inicio'));
        $fim = $this->data($request->query('fim'));

        if (! $inicio || ! $fim || $fim->lt($inicio)) {
            return response()->json(['publicacoes' => null, 'alertas' => null, 'arquivos' => []]);
        }

        $cliente = User::where('perfil', User::CLIENT)->find($request->integer('cliente'));
        $formatos = array_values(array_intersect(['xlsx', 'pdf'], (array) $request->query('formatos', [])));

        return response()->json([
            'publicacoes' => RelatorioPrefeitura::consultaPublicacoes($inicio, $fim)->count(),
            'alertas' => $cliente ? RelatorioPrefeitura::consultaAlertas($cliente, $inicio, $fim)->count() : null,
            'arquivos' => $cliente
                ? array_map(fn ($f) => RelatorioPrefeitura::montarNomeArquivo($cliente, $inicio, $fim, $f), $formatos)
                : [],
        ]);
    }

    /**
     * O arquivo é gerado inteiro antes da resposta: se algo falhar, sai uma
     * página de erro, e não um download pela metade.
     *
     * Com os dois formatos marcados, a página faz um pedido por formato e
     * baixa cada arquivo separado. Só quem envia os dois num pedido só (sem
     * JavaScript) recebe um .zip, porque uma resposta carrega um arquivo.
     */
    public function gerar(GerarRelatorioRequest $request, PlanilhaRelatorio $planilha, PdfRelatorio $pdf): BinaryFileResponse
    {
        $relatorio = new RelatorioPrefeitura($request->cliente(), $request->inicio(), $request->fim());
        $geradores = ['xlsx' => $planilha, 'pdf' => $pdf];
        $formatos = $request->formatos();

        if (count($formatos) > 1) {
            return $this->baixarJuntos($relatorio, $geradores);
        }

        $formato = $formatos[0];
        $arquivo = tempnam(sys_get_temp_dir(), 'relatorio');
        $geradores[$formato]->salvar($relatorio, $arquivo);

        return response()
            ->download($arquivo, $relatorio->nomeArquivo($formato), ['Content-Type' => $geradores[$formato]::MIME])
            ->deleteFileAfterSend();
    }

    /** @param array<string, PlanilhaRelatorio|PdfRelatorio> $geradores */
    private function baixarJuntos(RelatorioPrefeitura $relatorio, array $geradores): BinaryFileResponse
    {
        $zip = tempnam(sys_get_temp_dir(), 'relatorio');
        $temporarios = [];

        try {
            $pacote = new ZipArchive();
            if ($pacote->open($zip, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível montar o .zip do relatório.');
            }
            foreach ($geradores as $formato => $gerador) {
                $temporarios[] = $arquivo = tempnam(sys_get_temp_dir(), 'relatorio');
                $gerador->salvar($relatorio, $arquivo);
                $pacote->addFile($arquivo, $relatorio->nomeArquivo($formato));
            }
            $pacote->close();
        } finally {
            array_map('unlink', $temporarios);
        }

        return response()
            ->download($zip, $relatorio->nomeArquivo('zip'), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function dadosDoFormulario(): array
    {
        $hoje = CarbonImmutable::today(RelatorioPrefeitura::FUSO);
        $mesPassado = $hoje->subMonthNoOverflow();

        // Atalhos de período. O padrão é o mês anterior: o relatório costuma
        // ser fechado no começo do mês seguinte.
        $atalhos = [
            ['rotulo' => 'Mês anterior', 'inicio' => $mesPassado->startOfMonth(), 'fim' => $mesPassado->endOfMonth()],
            ['rotulo' => 'Mês atual', 'inicio' => $hoje->startOfMonth(), 'fim' => $hoje],
            ['rotulo' => 'Últimos 30 dias', 'inicio' => $hoje->subDays(29), 'fim' => $hoje],
        ];

        $inicio = $this->data(old('inicio')) ?? $atalhos[0]['inicio'];
        $fim = $this->data(old('fim')) ?? $atalhos[0]['fim'];

        return [
            'clientes' => User::where('perfil', User::CLIENT)->orderBy('nome')->get(['id', 'nome', 'contrato', 'ativo']),
            'atalhos' => $atalhos,
            'inicio' => $inicio,
            'fim' => $fim,
            'publicacoes' => $fim->gte($inicio) ? RelatorioPrefeitura::consultaPublicacoes($inicio, $fim)->count() : null,
        ];
    }

    private function data(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        $data = RelatorioPrefeitura::dia($valor);

        // createFromFormat aceita 2026-02-31 e vira março; isso é data inválida.
        return $data && $data->format('Y-m-d') === $valor ? $data : null;
    }
}
