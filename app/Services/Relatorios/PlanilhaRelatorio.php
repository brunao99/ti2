<?php

namespace App\Services\Relatorios;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Relatório em XLSX, no layout do blueprint estilizado: faixa azul-marinho
 * com a logo, identificação, indicadores, publicações, alertas e fechamento.
 * Tudo numa aba, pronta para imprimir em A4.
 *
 * Texto que vem do sistema entra sempre como texto explícito: um título que
 * comece com "=" viraria fórmula no Excel.
 */
class PlanilhaRelatorio
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    // Identidade: as mesmas cores do painel (public/assets/style.css).
    private const NAVY = '133049';
    private const AZUL = '2B6CB0';
    private const TEAL = '1A6383';
    private const TINTA = '1A2B3C';
    private const CINZA = '6B7280';
    private const LINHA = 'DDE3EA';
    private const FUNDO = 'F4F6F9';
    private const ZEBRA = 'FAFBFC';
    private const SUAVE = 'B9C7D6';
    private const FONTE = 'Arial';

    /** Largura das colunas A–E, em caracteres. */
    private const LARGURAS = ['A' => 7, 'B' => 13, 'C' => 28, 'D' => 58, 'E' => 42];

    private Worksheet $aba;

    public function salvar(RelatorioPrefeitura $relatorio, string $caminho): void
    {
        $planilha = new Spreadsheet();
        $planilha->getProperties()
            ->setCreator(RelatorioPrefeitura::EMPRESA)
            ->setCompany(RelatorioPrefeitura::EMPRESA)
            ->setTitle($relatorio->nomeArquivo('xlsx'))
            ->setSubject(RelatorioPrefeitura::TITULO);
        $planilha->getDefaultStyle()->getFont()->setName(self::FONTE)->setSize(9)->getColor()->setRGB(self::TINTA);

        $this->aba = $planilha->getActiveSheet();
        $this->aba->setTitle('Relatório');
        $this->aba->setShowGridlines(false);
        foreach (self::LARGURAS as $coluna => $largura) {
            $this->aba->getColumnDimension($coluna)->setWidth($largura);
        }

        $this->topo();
        $linha = $this->identificacao($relatorio, 6);
        $linha = $this->indicadores($relatorio, $linha + 1);
        $linha = $this->publicacoes($relatorio, $linha + 1);
        $linha = $this->alertas($relatorio, $linha + 1);
        $linha = $this->fechamento($relatorio, $linha + 1);
        $this->impressao($linha);

        $this->aba->setSelectedCell('A1');
        (new Xlsx($planilha))->save($caminho);
        $planilha->disconnectWorksheets();
    }

    // ----------------------------------------------------------------- blocos

    private function topo(): void
    {
        foreach ([1, 2, 3] as $r) {
            $this->faixa($r, self::NAVY, 22);
        }

        $logo = new Drawing();
        $logo->setName('Algoritmos e Dados');
        $logo->setPath(resource_path('relatorios/logo-branca.png'));
        $logo->setHeight(36);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(12);
        $logo->setOffsetY(26);
        $logo->setWorksheet($this->aba);

        $this->texto('D1', mb_strtoupper(RelatorioPrefeitura::TITULO), 'D1:E2');
        $this->estilo('D1', tamanho: 13, negrito: true, cor: 'FFFFFF', alinhamento: Alignment::HORIZONTAL_RIGHT);
        $this->texto('D3', RelatorioPrefeitura::SUBTITULO, 'D3:E3');
        $this->estilo('D3', tamanho: 8, cor: self::SUAVE, alinhamento: Alignment::HORIZONTAL_RIGHT);

        $this->faixa(4, self::AZUL, 4);
        $this->aba->getRowDimension(5)->setRowHeight(10);
    }

    private function identificacao(RelatorioPrefeitura $relatorio, int $r): int
    {
        $campos = [
            ['A', 'B', 'Município', $relatorio->cliente->nome],
            ['C', 'C', 'Contrato', (string) $relatorio->cliente->contrato],
            ['D', 'D', 'Período', $relatorio->periodo()],
            ['E', 'E', 'Emitido em', $relatorio->emitidoEm->format('d/m/Y H:i')],
        ];

        foreach ($campos as [$de, $ate, $rotulo, $valor]) {
            $this->texto("{$de}{$r}", mb_strtoupper($rotulo), "{$de}{$r}:{$ate}{$r}");
            $this->estilo("{$de}{$r}", tamanho: 7, negrito: true, cor: self::CINZA);
            $this->texto($de . ($r + 1), $valor, $de . ($r + 1) . ':' . $ate . ($r + 1));
            $this->estilo($de . ($r + 1), tamanho: 11, negrito: true, cor: self::NAVY);
        }

        $this->preencher("A{$r}:E" . ($r + 1), self::FUNDO);
        $this->borda('A' . ($r + 1) . ':E' . ($r + 1), 'bottom', Border::BORDER_MEDIUM, self::NAVY);
        $this->aba->getRowDimension($r)->setRowHeight(18);
        $this->aba->getRowDimension($r + 1)->setRowHeight(26);

        $r += 2;
        $this->texto("A{$r}", RelatorioPrefeitura::EMPRESA . '  ·  CNPJ ' . RelatorioPrefeitura::CNPJ . '  ·  ' . RelatorioPrefeitura::SITE, "A{$r}:E{$r}");
        $this->estilo("A{$r}", tamanho: 8, cor: self::CINZA);
        $this->aba->getRowDimension($r)->setRowHeight(18);
        $this->aba->getRowDimension($r + 1)->setRowHeight(10);

        return $r + 1;
    }

    private function indicadores(RelatorioPrefeitura $relatorio, int $r): int
    {
        $kpis = [
            ['A', 'B', 'Publicações no período', $relatorio->publicacoes()->count()],
            ['C', 'C', 'Alertas entregues', $relatorio->alertas()->count()],
            ['D', 'D', 'Categorias abordadas', $relatorio->totalCategorias()],
        ];

        foreach ($kpis as [$de, $ate, $rotulo, $valor]) {
            $this->texto("{$de}{$r}", mb_strtoupper($rotulo), "{$de}{$r}:{$ate}{$r}");
            $this->estilo("{$de}{$r}", tamanho: 7, negrito: true, cor: self::CINZA);
            $this->aba->mergeCells($de . ($r + 1) . ':' . $ate . ($r + 1));
            $this->aba->setCellValue($de . ($r + 1), $valor);
            $this->estilo($de . ($r + 1), tamanho: 20, negrito: true, cor: self::NAVY);
        }

        $this->preencher("A{$r}:D" . ($r + 1), self::FUNDO);
        $this->borda("A{$r}:D{$r}", 'top', Border::BORDER_THICK, self::AZUL);
        $this->aba->getRowDimension($r)->setRowHeight(22);
        $this->aba->getRowDimension($r + 1)->setRowHeight(34);
        $this->aba->getRowDimension($r + 2)->setRowHeight(14);

        return $r + 2;
    }

    private function publicacoes(RelatorioPrefeitura $relatorio, int $r): int
    {
        $this->secao($r, 'Publicações do período', self::NAVY);
        $this->cabecalho($r + 1, ['Nº', 'Data', 'Categoria', 'Conteúdo', 'Fonte'], self::NAVY);
        $r += 2;

        if ($relatorio->publicacoes()->isEmpty()) {
            return $this->vazio($r, 'Nenhuma publicação no período.');
        }

        foreach ($relatorio->publicacoes() as $i => $postagem) {
            $this->linhaDeDados($r, 'D');
            $this->aba->setCellValue("A{$r}", $i + 1);
            $this->data("B{$r}", $postagem->data_publicacao);
            $this->texto("C{$r}", $postagem->rotuloTipo());
            $this->texto("D{$r}", mb_strtoupper($postagem->titulo));
            $this->link("E{$r}", $postagem->link);
            $this->aba->getRowDimension($r)->setRowHeight($this->altura([
                [$postagem->rotuloTipo(), self::LARGURAS['C']],
                [mb_strtoupper($postagem->titulo), self::LARGURAS['D'] * 0.8],
            ]));
            $r++;
        }

        return $r;
    }

    private function alertas(RelatorioPrefeitura $relatorio, int $r): int
    {
        $this->secao($r, 'Alertas enviados por WhatsApp', self::TEAL);
        $this->cabecalho($r + 1, ['Nº', 'Data do envio', 'Conteúdo', '', 'Fonte'], self::TEAL);
        $this->aba->mergeCells('C' . ($r + 1) . ':D' . ($r + 1));
        $r += 2;

        if ($relatorio->alertas()->isEmpty()) {
            return $this->vazio($r, 'Nenhum alerta entregue no período.');
        }

        // A numeração continua a das publicações.
        $numero = $relatorio->publicacoes()->count();

        foreach ($relatorio->alertas() as $alerta) {
            $conteudo = $relatorio->conteudoDoAlerta($alerta);

            $this->linhaDeDados($r, 'C');
            $this->aba->setCellValue("A{$r}", ++$numero);
            $this->data("B{$r}", $relatorio->dataDoAlerta($alerta));
            $this->texto("C{$r}", $conteudo, "C{$r}:D{$r}");
            $this->link("E{$r}", $relatorio->linkDoAlerta($alerta));
            $this->aba->getRowDimension($r)->setRowHeight($this->altura([
                [$conteudo, (self::LARGURAS['C'] + self::LARGURAS['D']) * 0.85],
            ]));
            $r++;
        }

        return $r;
    }

    private function fechamento(RelatorioPrefeitura $relatorio, int $r): int
    {
        $this->aba->getRowDimension($r)->setRowHeight(26);
        $r++;

        $this->texto("D{$r}", RelatorioPrefeitura::LOCAL . ', ' . $relatorio->dataPorExtenso(), "D{$r}:E{$r}");
        $this->estilo("D{$r}", tamanho: 9, alinhamento: Alignment::HORIZONTAL_RIGHT);
        $this->aba->getRowDimension($r + 1)->setRowHeight(34);

        $r += 2;
        $this->texto("D{$r}", RelatorioPrefeitura::EMPRESA, "D{$r}:E{$r}");
        $this->estilo("D{$r}", tamanho: 9, negrito: true, cor: self::NAVY, alinhamento: Alignment::HORIZONTAL_RIGHT);
        $this->borda("D{$r}:E{$r}", 'top', Border::BORDER_THIN, self::NAVY);

        $r++;
        $this->texto("D{$r}", 'CNPJ ' . RelatorioPrefeitura::CNPJ, "D{$r}:E{$r}");
        $this->estilo("D{$r}", tamanho: 8, cor: self::CINZA, alinhamento: Alignment::HORIZONTAL_RIGHT);

        return $r;
    }

    private function impressao(int $ultimaLinha): void
    {
        $pagina = $this->aba->getPageSetup();
        $pagina->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pagina->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pagina->setFitToWidth(1);
        $pagina->setFitToHeight(0);
        $pagina->setPrintArea("A1:E{$ultimaLinha}");

        $margens = $this->aba->getPageMargins();
        $margens->setLeft(0.4)->setRight(0.4)->setTop(0.4)->setBottom(0.6);

        $this->aba->getHeaderFooter()->setOddFooter(
            '&L&"Arial"&7' . RelatorioPrefeitura::EMPRESA . ' · ' . RelatorioPrefeitura::SITE
            . '&R&"Arial"&7Página &P de &N'
        );
    }

    // -------------------------------------------------------------- tabelas

    private function secao(int $r, string $titulo, string $cor): void
    {
        $this->texto("A{$r}", mb_strtoupper($titulo), "A{$r}:E{$r}");
        $this->estilo("A{$r}", tamanho: 11, negrito: true, cor: $cor, vertical: Alignment::VERTICAL_BOTTOM);
        $this->borda("A{$r}:E{$r}", 'bottom', Border::BORDER_MEDIUM, $cor);
        $this->aba->getRowDimension($r)->setRowHeight(24);
    }

    private function cabecalho(int $r, array $nomes, string $cor): void
    {
        foreach ($nomes as $i => $nome) {
            $celula = chr(65 + $i) . $r;
            $this->texto($celula, $nome);
            $this->estilo($celula, tamanho: 8, negrito: true, cor: 'FFFFFF',
                alinhamento: $i < 2 ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);
        }
        $this->preencher("A{$r}:E{$r}", $cor);
        $this->aba->getRowDimension($r)->setRowHeight(22);
    }

    /**
     * Zebra, filete e alinhamento de uma linha; a coluna de conteúdo vai em
     * negrito. Vem antes dos valores: o link tem estilo próprio por cima.
     */
    private function linhaDeDados(int $r, string $colunaConteudo): void
    {
        $this->preencher("A{$r}:E{$r}", self::ZEBRA);
        $this->borda("A{$r}:E{$r}", 'bottom', Border::BORDER_THIN, self::LINHA);
        $this->estilo("A{$r}:B{$r}", alinhamento: Alignment::HORIZONTAL_CENTER);
        $this->estilo("C{$r}:E{$r}");
        $this->aba->getStyle("{$colunaConteudo}{$r}")->getFont()->setBold(true);
    }

    private function vazio(int $r, string $mensagem): int
    {
        $this->texto("A{$r}", $mensagem, "A{$r}:E{$r}");
        $this->estilo("A{$r}", cor: self::CINZA);
        $this->aba->getStyle("A{$r}")->getFont()->setItalic(true);
        $this->preencher("A{$r}:E{$r}", self::ZEBRA);
        $this->borda("A{$r}:E{$r}", 'bottom', Border::BORDER_THIN, self::LINHA);
        $this->aba->getRowDimension($r)->setRowHeight(28);

        return $r + 1;
    }

    /**
     * Altura da linha pelo texto mais longo. O Excel não ajusta sozinho
     * linha com célula mesclada, então a conta é feita aqui.
     *
     * @param  array<int, array{0: string, 1: float}>  $textos  [texto, caracteres por linha]
     */
    private function altura(array $textos): float
    {
        $linhas = 1;
        foreach ($textos as [$texto, $porLinha]) {
            $linhas = max($linhas, (int) ceil(mb_strlen($texto) / max(1, $porLinha)));
        }

        return max(30, $linhas * 12 + 10);
    }

    // ---------------------------------------------------------------- células

    private function texto(string $celula, string $valor, ?string $mesclar = null): void
    {
        $this->aba->setCellValueExplicit($celula, $valor, DataType::TYPE_STRING);
        if ($mesclar) {
            $this->aba->mergeCells($mesclar);
        }
    }

    private function data(string $celula, \DateTimeInterface $data): void
    {
        $dia = \DateTimeImmutable::createFromFormat('!Y-m-d', $data->format('Y-m-d'));
        $this->aba->setCellValue($celula, Date::dateTimeToExcel($dia));
        $this->aba->getStyle($celula)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    }

    private function link(string $celula, ?string $url): void
    {
        if (! $url) {
            return;
        }

        // Endereço sem espaço não quebra linha: o texto curto cabe na coluna.
        $this->texto($celula, RelatorioPrefeitura::linkCurto($url, 45));
        $this->aba->getCell($celula)->getHyperlink()->setUrl($url)->setTooltip($url);
        $this->aba->getStyle($celula)->getFont()->setUnderline(true)->setSize(8)->getColor()->setRGB(self::AZUL);
    }

    private function estilo(
        string $intervalo,
        float $tamanho = 9,
        bool $negrito = false,
        string $cor = self::TINTA,
        string $alinhamento = Alignment::HORIZONTAL_LEFT,
        string $vertical = Alignment::VERTICAL_CENTER,
    ): void {
        $estilo = $this->aba->getStyle($intervalo);
        $estilo->getFont()->setName(self::FONTE)->setSize($tamanho)->setBold($negrito)->getColor()->setRGB($cor);
        $estilo->getAlignment()->setHorizontal($alinhamento)->setVertical($vertical)->setWrapText(true)
            ->setIndent($alinhamento === Alignment::HORIZONTAL_CENTER ? 0 : 1);
    }

    private function preencher(string $intervalo, string $cor): void
    {
        $this->aba->getStyle($intervalo)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cor);
    }

    private function borda(string $intervalo, string $lado, string $tipo, string $cor): void
    {
        $borda = match ($lado) {
            'top' => $this->aba->getStyle($intervalo)->getBorders()->getTop(),
            default => $this->aba->getStyle($intervalo)->getBorders()->getBottom(),
        };
        $borda->setBorderStyle($tipo)->getColor()->setRGB($cor);
    }

    private function faixa(int $r, string $cor, float $altura): void
    {
        $this->preencher("A{$r}:E{$r}", $cor);
        $this->aba->getRowDimension($r)->setRowHeight($altura);
    }
}
