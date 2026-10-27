<?php

namespace App\Services\Relatorios;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Relatório em XLSX no layout da planilha que a Algoritmos já envia às
 * prefeituras: cabeçalho em duas linhas, tabela com faixa verde, publicações,
 * o bloco "ALERTAS POR WHATSAPP" e o local e data no fim. O PDF segue o
 * layout novo (PdfRelatorio); os dados dos dois vêm de RelatorioPrefeitura.
 *
 * Texto que vem do sistema entra sempre como texto explícito: um título que
 * comece com "=" viraria fórmula no Excel.
 */
class PlanilhaRelatorio
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    // Cores e larguras do modelo do cliente.
    private const VERDE = '00B050';
    private const AZUL_LINK = '0070C0';
    private const LARGURAS = ['A' => 5.33, 'B' => 11.11, 'C' => 55.11, 'D' => 24.44, 'E' => 73.11];
    private const ALTURA_LINHA = 39;

    private Worksheet $aba;

    public function salvar(RelatorioPrefeitura $relatorio, string $caminho): void
    {
        $planilha = new Spreadsheet();
        $planilha->getProperties()
            ->setCreator(RelatorioPrefeitura::EMPRESA)
            ->setCompany(RelatorioPrefeitura::EMPRESA)
            ->setTitle($relatorio->nomeArquivo('xlsx'))
            ->setSubject(RelatorioPrefeitura::TITULO);
        $planilha->getDefaultStyle()->getFont()->setName('Arial')->setSize(8);

        $this->aba = $planilha->getActiveSheet();
        $this->aba->setTitle('Relatório');
        foreach (self::LARGURAS as $coluna => $largura) {
            $this->aba->getColumnDimension($coluna)->setWidth($largura);
        }

        $this->cabecalho($relatorio);
        $linha = $this->publicacoes($relatorio, 4);
        $linha = $this->alertas($relatorio, $linha);
        $linha = $this->fechamento($relatorio, $linha);
        $this->impressao($linha);

        $this->aba->setSelectedCell('A1');
        (new Xlsx($planilha))->save($caminho);
        $planilha->disconnectWorksheets();
    }

    // ----------------------------------------------------------------- blocos

    /** Linhas 1 a 3: empresa e contrato, descrição e período, títulos das colunas. */
    private function cabecalho(RelatorioPrefeitura $relatorio): void
    {
        $empresa = sprintf('%s - CNPJ %s - CONTRATO %s - MUNICÍPIO: %s',
            mb_strtoupper(RelatorioPrefeitura::EMPRESA), RelatorioPrefeitura::CNPJ,
            $relatorio->cliente->contrato, $relatorio->cliente->nome);
        $this->texto('A1', $empresa, 'A1:E1');
        $this->estilo('A1:E1', fonte: 'Calibri', tamanho: 10, negrito: true);

        $descricao = 'Relatório sintético referente a disponibilização de informações e oportunidades para a gestão '
            . 'pública postadas no site: ' . RelatorioPrefeitura::SITE . ' - Período: ' . $relatorio->periodo();
        $this->texto('A2', $descricao, 'A2:E2');
        $this->estilo('A2:E2', fonte: 'Times New Roman', tamanho: 9);
        $this->aba->getCell('A2')->getHyperlink()->setUrl('http://' . RelatorioPrefeitura::SITE . '/');

        foreach (['A' => 'Nº', 'B' => 'DATA', 'C' => 'LINK', 'D' => 'CATEGORIAS', 'E' => 'CONTEÚDO'] as $coluna => $titulo) {
            $this->texto("{$coluna}3", $titulo);
        }
        $this->estilo('A3:E3', tamanho: 9, negrito: true, cor: 'FFFFFF');
        $this->preencher('A3:E3', self::VERDE);

        $this->aba->getRowDimension(1)->setRowHeight(29.25);
        $this->aba->getRowDimension(2)->setRowHeight(29.25);
        $this->aba->getRowDimension(3)->setRowHeight(29.25);
    }

    private function publicacoes(RelatorioPrefeitura $relatorio, int $r): int
    {
        if ($relatorio->publicacoes()->isEmpty()) {
            return $this->vazio($r, 'Nenhuma publicação no período.');
        }

        foreach ($relatorio->publicacoes()->values() as $i => $postagem) {
            $titulo = mb_strtoupper($postagem->titulo);
            $this->linha($r, $i + 1, $postagem->data_publicacao, $postagem->link, $postagem->rotuloTipo(), $titulo);
            $r++;
        }

        return $r;
    }

    /** Faixa verde "ALERTAS POR WHATSAPP" e os alertas; a numeração continua. */
    private function alertas(RelatorioPrefeitura $relatorio, int $r): int
    {
        $this->texto("A{$r}", 'ALERTAS POR WHATSAPP', "A{$r}:E{$r}");
        $this->estilo("A{$r}:E{$r}", fonte: 'Times New Roman', tamanho: 14, negrito: true, cor: 'FFFFFF');
        $this->preencher("A{$r}:E{$r}", self::VERDE);
        $this->aba->getRowDimension($r)->setRowHeight(39.3);
        $r++;

        if ($relatorio->alertas()->isEmpty()) {
            return $this->vazio($r, 'Nenhum alerta entregue no período.');
        }

        $numero = $relatorio->publicacoes()->count();
        foreach ($relatorio->alertas() as $alerta) {
            $this->linha($r, ++$numero, $relatorio->dataDoAlerta($alerta), $relatorio->linkDoAlerta($alerta),
                null, $relatorio->conteudoDoAlerta($alerta));
            $r++;
        }

        return $r;
    }

    private function fechamento(RelatorioPrefeitura $relatorio, int $r): int
    {
        $this->texto("D{$r}", RelatorioPrefeitura::LOCAL . ', ' . $relatorio->dataPorExtenso());
        $this->estilo("A{$r}:E{$r}", fonte: 'Times New Roman', tamanho: 9, negrito: true);
        $this->aba->getRowDimension($r)->setRowHeight(97.8);

        return $r;
    }

    private function impressao(int $ultimaLinha): void
    {
        $pagina = $this->aba->getPageSetup();
        $pagina->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pagina->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pagina->setFitToWidth(1);
        $pagina->setFitToHeight(0);
        $pagina->setPrintArea("A1:E{$ultimaLinha}");
        $pagina->setRowsToRepeatAtTopByStartAndEnd(3, 3);

        $this->aba->getPageMargins()->setLeft(0.71)->setRight(0.71)->setTop(0.75)->setBottom(0.75);
    }

    // ------------------------------------------------------------- linhas

    /** Uma linha da tabela: Nº, data, link, categoria e conteúdo. */
    private function linha(int $r, int $numero, \DateTimeInterface $data, ?string $link, ?string $categoria, string $conteudo): void
    {
        $this->estilo("A{$r}:E{$r}");
        $this->aba->getStyle("A{$r}")->getFont()->setName('Times New Roman')->setSize(10);
        $this->aba->getStyle("E{$r}")->getFont()->setBold(true);

        $this->aba->setCellValue("A{$r}", $numero);

        $dia = \DateTimeImmutable::createFromFormat('!Y-m-d', $data->format('Y-m-d'));
        $this->aba->setCellValue("B{$r}", Date::dateTimeToExcel($dia));
        $this->aba->getStyle("B{$r}")->getNumberFormat()->setFormatCode('dd/mm/yyyy;@');

        if ($link) {
            $this->texto("C{$r}", $link);
            $this->aba->getCell("C{$r}")->getHyperlink()->setUrl($link);
            $this->aba->getStyle("C{$r}")->getFont()->setUnderline(true)->getColor()->setRGB(self::AZUL_LINK);
        }

        if ($categoria) {
            $this->texto("D{$r}", $categoria);
        }

        $this->texto("E{$r}", $conteudo);

        // Altura fixa do modelo; cresce só quando o link ou o conteúdo não cabem em três linhas.
        $linhas = max(ceil(mb_strlen((string) $link) / 70), ceil(mb_strlen($conteudo) / 85), 1);
        $this->aba->getRowDimension($r)->setRowHeight(max(self::ALTURA_LINHA, $linhas * 11 + 6));
    }

    private function vazio(int $r, string $mensagem): int
    {
        $this->texto("A{$r}", $mensagem, "A{$r}:E{$r}");
        $this->estilo("A{$r}:E{$r}");
        $this->aba->getStyle("A{$r}")->getFont()->setItalic(true);
        $this->aba->getRowDimension($r)->setRowHeight(self::ALTURA_LINHA);

        return $r + 1;
    }

    // ------------------------------------------------------------- células

    private function texto(string $celula, string $valor, ?string $mesclar = null): void
    {
        $this->aba->setCellValueExplicit($celula, $valor, DataType::TYPE_STRING);
        if ($mesclar) {
            $this->aba->mergeCells($mesclar);
        }
    }

    /** Centralizado, com quebra de linha e borda fina em volta de cada célula, como no modelo. */
    private function estilo(
        string $intervalo,
        string $fonte = 'Arial',
        float $tamanho = 8,
        bool $negrito = false,
        string $cor = '000000',
    ): void {
        $estilo = $this->aba->getStyle($intervalo);
        $estilo->getFont()->setName($fonte)->setSize($tamanho)->setBold($negrito)->getColor()->setRGB($cor);
        $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $estilo->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
    }

    private function preencher(string $intervalo, string $cor): void
    {
        $this->aba->getStyle($intervalo)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cor);
    }
}
