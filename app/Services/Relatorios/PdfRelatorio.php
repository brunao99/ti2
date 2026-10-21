<?php

namespace App\Services\Relatorios;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * Relatório em PDF, para enviar à prefeitura. Mesmo conteúdo da planilha;
 * o corpo é a view relatorios/pdf e a faixa do topo e o rodapé são
 * desenhados em cada página depois da paginação, como no blueprint.
 *
 * Fontes (Lato e Oswald, licença OFL) e logo ficam em resources/relatorios.
 * O Dompdf guarda as métricas das fontes em storage/fonts na primeira vez.
 */
class PdfRelatorio
{
    public const MIME = 'application/pdf';

    private const MM = 72 / 25.4;

    public function salvar(RelatorioPrefeitura $relatorio, string $caminho): void
    {
        file_put_contents($caminho, $this->conteudo($relatorio));
    }

    public function conteudo(RelatorioPrefeitura $relatorio): string
    {
        $cache = storage_path('fonts');
        File::ensureDirectoryExists($cache);

        $opcoes = new Options();
        $opcoes->setChroot([resource_path('relatorios')]);
        $opcoes->setFontDir($cache);
        $opcoes->setFontCache($cache);
        $opcoes->setIsRemoteEnabled(false);
        $opcoes->setDefaultFont('Lato');
        $opcoes->setDefaultPaperSize('a4');

        $pdf = new Dompdf($opcoes);
        $pdf->addInfo('Title', $relatorio->nomeArquivo('pdf'));
        $pdf->addInfo('Author', RelatorioPrefeitura::EMPRESA);
        $pdf->addInfo('Subject', RelatorioPrefeitura::TITULO);
        $pdf->loadHtml(view('relatorios.pdf', [
            'relatorio' => $relatorio,
            'fontes' => resource_path('relatorios/fontes'),
            'publicacoes' => $this->linhasDePublicacao($relatorio),
            'alertas' => $this->linhasDeAlerta($relatorio),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $pdf->getCanvas()->page_script(fn (int $pagina, int $total, Canvas $canvas, FontMetrics $metricas) => $this->moldura($pagina, $total, $canvas, $metricas));

        return $pdf->output();
    }

    // ----------------------------------------------------------------- linhas

    private function linhasDePublicacao(RelatorioPrefeitura $relatorio): array
    {
        return $relatorio->publicacoes()->values()->map(fn ($postagem, $i) => [
            'numero' => $i + 1,
            'data' => $postagem->data_publicacao->format('d/m/Y'),
            'categoria' => $postagem->rotuloTipo(),
            'conteudo' => mb_strtoupper($postagem->titulo),
            'link' => $postagem->link,
            'linkTexto' => $this->linkEmLinhas($postagem->link),
        ])->all();
    }

    private function linhasDeAlerta(RelatorioPrefeitura $relatorio): array
    {
        $numero = $relatorio->publicacoes()->count();

        return $relatorio->alertas()->map(function ($alerta) use ($relatorio, &$numero) {
            $link = $relatorio->linkDoAlerta($alerta);

            return [
                'numero' => ++$numero,
                'data' => $relatorio->dataDoAlerta($alerta)->format('d/m/Y'),
                'conteudo' => $relatorio->conteudoDoAlerta($alerta),
                'link' => $link,
                'linkTexto' => $this->linkEmLinhas($link),
            ];
        })->all();
    }

    /**
     * O Dompdf só quebra linha em espaço, e endereço não tem espaço: sem isso
     * o link vazaria da coluna. Até duas linhas de 28 caracteres.
     *
     * @return array<int, string>
     */
    private function linkEmLinhas(?string $url): array
    {
        return $url ? mb_str_split(RelatorioPrefeitura::linkCurto($url, 56), 28) : [];
    }

    // ------------------------------------------------- faixa do topo e rodapé

    private function moldura(int $pagina, int $total, Canvas $canvas, FontMetrics $metricas): void
    {
        $mm = self::MM;
        $largura = $canvas->get_width();
        $altura = $canvas->get_height();
        $branco = [1, 1, 1];
        $cinza = [0x6B / 255, 0x72 / 255, 0x80 / 255];

        $lato = $metricas->getFont('Lato');
        $oswald = $metricas->getFont('Oswald');

        // Faixa azul-marinho, filete azul, logo e título.
        $canvas->filled_rectangle(0, 0, $largura, 30 * $mm, [0x13 / 255, 0x30 / 255, 0x49 / 255]);
        $canvas->filled_rectangle(0, 30 * $mm, $largura, 1.2 * $mm, [0x2B / 255, 0x6C / 255, 0xB0 / 255]);

        $logoLargura = 62 * $mm;
        $logoAltura = $logoLargura * 176 / 1266;
        $canvas->image(resource_path('relatorios/logo-branca.png'), 15 * $mm, 15 * $mm - $logoAltura / 2, $logoLargura, $logoAltura);

        $titulo = mb_strtoupper(RelatorioPrefeitura::TITULO);
        $canvas->text($largura - 15 * $mm - $metricas->getTextWidth($titulo, $oswald, 13), 9.6 * $mm, $titulo, $oswald, 13, $branco);

        $subtitulo = RelatorioPrefeitura::SUBTITULO;
        $canvas->text($largura - 15 * $mm - $metricas->getTextWidth($subtitulo, $lato, 7.5), 17.6 * $mm, $subtitulo,
            $lato, 7.5, [0xB9 / 255, 0xC7 / 255, 0xD6 / 255]);

        // Rodapé: empresa à esquerda, página à direita.
        $canvas->line(15 * $mm, $altura - 14 * $mm, $largura - 15 * $mm, $altura - 14 * $mm, [0xDD / 255, 0xE3 / 255, 0xEA / 255], 0.6);

        $empresa = RelatorioPrefeitura::EMPRESA . '  ·  CNPJ ' . RelatorioPrefeitura::CNPJ . '  ·  ' . RelatorioPrefeitura::SITE;
        $canvas->text(15 * $mm, $altura - 12 * $mm, $empresa, $lato, 7, $cinza);

        $paginas = "Página {$pagina} de {$total}";
        $canvas->text($largura - 15 * $mm - $metricas->getTextWidth($paginas, $lato, 7), $altura - 12 * $mm, $paginas, $lato, 7, $cinza);
    }
}
