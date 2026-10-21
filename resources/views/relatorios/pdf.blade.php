{{--
  Corpo do relatório em PDF (Dompdf). A faixa do topo e o rodapé são
  desenhados em cada página por App\Services\Relatorios\PdfRelatorio; aqui
  fica só o que flui de uma página para a outra.
--}}
@php($R = \App\Services\Relatorios\RelatorioPrefeitura::class)
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $relatorio->nomeArquivo('pdf') }}</title>
<style>
  @font-face { font-family: 'Lato'; font-weight: normal; font-style: normal; src: url('{{ $fontes }}/Lato-Regular.ttf') format('truetype'); }
  @font-face { font-family: 'Lato'; font-weight: bold; font-style: normal; src: url('{{ $fontes }}/Lato-Bold.ttf') format('truetype'); }
  @font-face { font-family: 'Oswald'; font-weight: normal; font-style: normal; src: url('{{ $fontes }}/Oswald-SemiBold.ttf') format('truetype'); }

  @page { margin: 40mm 15mm 20mm 15mm; }
  body { font-family: 'Lato', sans-serif; font-size: 9pt; line-height: 1.3; color: #1a2b3c; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; text-align: left; }

  .rotulo { font-weight: bold; font-size: 6.5pt; letter-spacing: .6pt; color: #6b7280; text-transform: uppercase; }

  .info { background: #f4f6f9; border-bottom: 1.4pt solid #133049; }
  .info td { padding: 7pt 8pt 8pt; }
  .info .valor { font-weight: bold; font-size: 10.5pt; color: #133049; margin-top: 3pt; }
  .empresa { font-size: 8pt; color: #6b7280; margin: 5pt 0 14pt; }

  .kpis td { background: #f4f6f9; border-top: 2pt solid #2b6cb0; padding: 8pt 8pt 9pt; width: 33.3%; }
  .kpis td.espaco { background: none; border: 0; width: 4pt; padding: 0; }
  .kpis .numero { font-family: 'Oswald'; font-size: 22pt; line-height: 1.1; color: #133049; margin-top: 4pt; }

  .secao { font-family: 'Oswald'; font-size: 13pt; text-transform: uppercase; padding-bottom: 4pt; margin: 22pt 0 8pt; }
  .secao-navy { color: #133049; border-bottom: 1.4pt solid #133049; }
  .secao-teal { color: #1a6383; border-bottom: 1.4pt solid #1a6383; }

  .dados th { color: #fff; font-weight: bold; font-size: 7.5pt; padding: 6pt; }
  .dados-navy th { background: #133049; }
  .dados-teal th { background: #1a6383; }
  .dados td { background: #fafbfc; border-bottom: .5pt solid #dde3ea; padding: 6pt; font-size: 8.5pt; vertical-align: middle; }
  .dados tr { page-break-inside: avoid; }
  .dados .centro { text-align: center; }
  .dados .conteudo { font-weight: bold; }
  .dados a { color: #2b6cb0; font-size: 8pt; }
  .dados .vazio { color: #6b7280; text-align: center; padding: 10pt; }

  .fechamento { margin-top: 34pt; page-break-inside: avoid; text-align: right; }
  .fechamento .local { font-size: 9.5pt; }
  .fechamento .assinatura { width: 70mm; margin: 40pt 0 0 auto; border-top: .8pt solid #133049; padding-top: 5pt; }
  .fechamento b { color: #133049; font-size: 9.5pt; }
  .fechamento .cnpj { font-size: 8pt; color: #6b7280; }
</style>
</head>
<body>

  <table class="info">
    <tr>
      <td style="width:29%"><div class="rotulo">Município</div><div class="valor">{{ $relatorio->cliente->nome }}</div></td>
      <td style="width:17%"><div class="rotulo">Contrato</div><div class="valor">{{ $relatorio->cliente->contrato }}</div></td>
      <td style="width:31%"><div class="rotulo">Período</div><div class="valor">{{ $relatorio->periodo() }}</div></td>
      <td style="width:23%"><div class="rotulo">Emitido em</div><div class="valor">{{ $relatorio->emitidoEm->format('d/m/Y H:i') }}</div></td>
    </tr>
  </table>
  <p class="empresa">{{ $R::EMPRESA }}&nbsp; · &nbsp;CNPJ {{ $R::CNPJ }}&nbsp; · &nbsp;{{ $R::SITE }}</p>

  <table class="kpis">
    <tr>
      <td><div class="rotulo">Publicações no período</div><div class="numero">{{ count($publicacoes) }}</div></td>
      <td class="espaco"></td>
      <td><div class="rotulo">Alertas entregues</div><div class="numero">{{ count($alertas) }}</div></td>
      <td class="espaco"></td>
      <td><div class="rotulo">Categorias abordadas</div><div class="numero">{{ $relatorio->totalCategorias() }}</div></td>
    </tr>
  </table>

  <div class="secao secao-navy">Publicações do período</div>
  <table class="dados dados-navy">
    <thead>
      <tr>
        <th class="centro" style="width:7%">Nº</th>
        <th class="centro" style="width:12%">Data</th>
        <th style="width:20%">Categoria</th>
        <th style="width:38%">Conteúdo</th>
        <th style="width:23%">Fonte</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($publicacoes as $linha)
        <tr>
          <td class="centro">{{ $linha['numero'] }}</td>
          <td class="centro">{{ $linha['data'] }}</td>
          <td>{{ $linha['categoria'] }}</td>
          <td class="conteudo">{{ $linha['conteudo'] }}</td>
          <td>@if ($linha['link'])<a href="{{ $linha['link'] }}">{!! collect($linha['linkTexto'])->map(fn ($parte) => e($parte))->join('<br>') !!}</a>@endif</td>
        </tr>
      @empty
        <tr><td class="vazio" colspan="5">Nenhuma publicação no período.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="secao secao-teal">Alertas enviados por WhatsApp</div>
  <table class="dados dados-teal">
    <thead>
      <tr>
        <th class="centro" style="width:7%">Nº</th>
        <th class="centro" style="width:14%">Data do envio</th>
        <th style="width:56%">Conteúdo</th>
        <th style="width:23%">Fonte</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($alertas as $linha)
        <tr>
          <td class="centro">{{ $linha['numero'] }}</td>
          <td class="centro">{{ $linha['data'] }}</td>
          <td class="conteudo">{{ $linha['conteudo'] }}</td>
          <td>@if ($linha['link'])<a href="{{ $linha['link'] }}">{!! collect($linha['linkTexto'])->map(fn ($parte) => e($parte))->join('<br>') !!}</a>@endif</td>
        </tr>
      @empty
        <tr><td class="vazio" colspan="4">Nenhum alerta entregue no período.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="fechamento">
    <p class="local">{{ $R::LOCAL }}, {{ $relatorio->dataPorExtenso() }}</p>
    <div class="assinatura">
      <b>{{ $R::EMPRESA }}</b>
      <div class="cnpj">CNPJ {{ $R::CNPJ }}</div>
    </div>
  </div>

</body>
</html>
