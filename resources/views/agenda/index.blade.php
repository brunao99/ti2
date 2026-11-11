@extends('layouts.painel')

@php
    use App\Models\AgendaEvento;
    use App\Support\DataPt;

    // Endereço da agenda mantendo a visualização escolhida (sem escolha, cada
    // tela usa o seu padrão: Semana de trabalho no computador, Dia no celular).
    $ir = fn (array $p = []) => route('agenda.index', array_filter($p + ['visao' => $visaoPedida]));
    $novo = fn (string $tipo) => route('agenda.eventos.criar', array_filter([
        'tipo' => $tipo, 'data' => $data->format('Y-m-d'), 'visao' => $visaoPedida,
    ]));
    $detalhe = fn ($o) => route('agenda.eventos.detalhe', array_filter([
        'evento' => $o->evento->id, 'data' => $o->chave(), 'visao' => $visaoPedida,
    ]));
    $visoes = ['dia' => 'Dia', 'semana' => 'Semana de trabalho', 'semana-completa' => 'Semana', 'mes' => 'Mês'];
@endphp

@section('titulo', 'Agenda')
@section('classe-conteudo', 'content-agenda')

@section('cabecalho')
  <h1>Agenda</h1>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('feriados.index') }}">
    @include('agenda._icone', ['nome' => 'feriados']) Calendário de feriados
  </a>

  @if ($temPublicacao)
    <a class="btn btn-ghost" href="{{ $novo(AgendaEvento::ALERTA) }}" data-modal-agenda>
      @include('agenda._icone', ['nome' => 'enviar']) Programar alerta
    </a>
  @else
    <span class="btn btn-ghost" aria-disabled="true" title="Para programar um alerta é preciso ter ao menos uma publicação publicada.">
      @include('agenda._icone', ['nome' => 'enviar']) Programar alerta
    </span>
  @endif

  {{-- details/summary: o menu abre mesmo sem JavaScript. --}}
  <details class="ag-novo">
    <summary class="btn">@include('agenda._icone', ['nome' => 'mais']) Novo @include('agenda._icone', ['nome' => 'baixo'])</summary>
    <div class="ag-novo-menu" role="menu">
      @include('agenda._tipos-menu')
    </div>
  </details>
@endsection

@section('conteudo')
<div class="agenda" data-agenda>

  {{-- ======================================================= computador --}}
  <div class="ag-desk">

    <aside class="ag-lado">
      {{-- Mini calendário: a semana exibida fica destacada; o ponto marca data importante. --}}
      <section class="ag-mini" aria-label="Calendário do mês">
        <header>
          <b>{{ DataPt::mesAno($mini['mes']) }}</b>
          <span>
            <a href="{{ $ir(['data' => $data->format('Y-m-d'), 'mini' => $mini['anterior']->format('Y-m')]) }}" aria-label="Mês anterior">@include('agenda._icone', ['nome' => 'esquerda'])</a>
            <a href="{{ $ir(['data' => $data->format('Y-m-d'), 'mini' => $mini['proximo']->format('Y-m')]) }}" aria-label="Próximo mês">@include('agenda._icone', ['nome' => 'direita'])</a>
          </span>
        </header>
        <table>
          <thead><tr>@foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $l)<th>{{ $l }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($mini['semanas'] as $semana)
              <tr>
                @foreach ($semana as $d)
                  <td>
                    <a href="{{ $ir(['data' => $d['data']->format('Y-m-d')]) }}"
                       @class(['fora' => ! $d['noMes'], 'periodo' => $d['noPeriodo'], 'hoje' => $d['hoje']])
                       @if ($d['hoje']) aria-current="date" @endif
                       aria-label="{{ DataPt::extenso($d['data'], true) }}{{ $d['temData'] ? ', com data importante' : '' }}">
                      {{ $d['data']->day }}
                      @if ($d['temData'])<i class="ponto" aria-hidden="true"></i>@endif
                    </a>
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>

      <section class="ag-mostrar">
        <p class="label">Mostrar</p>
        @foreach (AgendaEvento::TIPOS as $tipo => $info)
          <label class="{{ AgendaEvento::classeDoTipo($tipo) }}">
            <input type="checkbox" checked data-mostrar="{{ $tipo }}">
            <span class="caixa" aria-hidden="true">@include('agenda._icone', ['nome' => 'certo'])</span>
            {{ $info['nome'] }}
            <span class="qtd">{{ $contagens[$tipo] }}</span>
          </label>
        @endforeach
      </section>

      <section class="ag-proximos">
        <p class="label">Próximos alertas</p>
        @forelse ($proximosAlertas as $o)
          <a class="ag-prox" href="{{ $detalhe($o) }}" data-agenda-detalhe>
            @include('agenda._icone', ['nome' => 'enviar'])
            <span>
              <b>{{ $o->evento->nome() }}</b>
              <small>{{ DataPt::relativo($o->dia, $hoje) }}, {{ $o->evento->hora_inicio }} · {{ $destinatarios }} {{ $destinatarios === 1 ? 'destinatário' : 'destinatários' }}</small>
            </span>
          </a>
        @empty
          <p class="ag-vazio">Nenhum alerta programado.</p>
        @endforelse
      </section>
    </aside>

    <section class="ag-principal">
      <div class="ag-barra">
        <a class="btn btn-ghost btn-sm" href="{{ $ir(['data' => $hoje->format('Y-m-d')]) }}">Hoje</a>
        <a class="ag-seta" href="{{ $ir(['data' => $anterior->format('Y-m-d')]) }}" aria-label="Período anterior">@include('agenda._icone', ['nome' => 'esquerda'])</a>
        <a class="ag-seta" href="{{ $ir(['data' => $proximo->format('Y-m-d')]) }}" aria-label="Próximo período">@include('agenda._icone', ['nome' => 'direita'])</a>

        {{-- O período abre o seletor de data do navegador. --}}
        <label class="ag-periodo">
          <span>{{ $rotulo }}</span> @include('agenda._icone', ['nome' => 'baixo'])
          <input type="date" value="{{ $data->format('Y-m-d') }}" data-ir-para="{{ $ir(['data' => '__DATA__']) }}" aria-label="Ir para a data">
        </label>

        <nav class="ag-visoes" aria-label="Visualização">
          @foreach ($visoes as $chave => $nome)
            <a href="{{ route('agenda.index', ['data' => $data->format('Y-m-d'), 'visao' => $chave]) }}"
               @class(['on' => $visao === $chave]) @if ($visao === $chave) aria-current="page" @endif>{{ $nome }}</a>
          @endforeach
        </nav>
      </div>

      @if ($grade)
        @include('agenda._grade')
      @else
        @include('agenda._mes')
      @endif
    </section>
  </div>

  {{-- ========================================================== celular --}}
  <div class="ag-movel">
    <div class="ag-m-topo">
      <h1>Agenda</h1>
      <nav class="ag-visoes" aria-label="Visualização">
        @foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês'] as $chave => $nome)
          <a href="{{ route('agenda.index', ['data' => $data->format('Y-m-d'), 'visao' => $chave]) }}"
             @class(['on' => $visaoMovel === $chave]) @if ($visaoMovel === $chave) aria-current="page" @endif>{{ $nome }}</a>
        @endforeach
      </nav>
    </div>

    <div class="ag-m-mes">
      <b>{{ DataPt::mesAno($data) }}</b>
      <span>
        <a href="{{ route('agenda.index', array_filter(['data' => $anteriorMovel->format('Y-m-d'), 'visao' => $visaoPedida])) }}" aria-label="Anterior">@include('agenda._icone', ['nome' => 'esquerda'])</a>
        <a href="{{ route('agenda.index', array_filter(['data' => $proximoMovel->format('Y-m-d'), 'visao' => $visaoPedida])) }}" aria-label="Próximo">@include('agenda._icone', ['nome' => 'direita'])</a>
      </span>
    </div>

    @if ($visaoMovel === 'mes')
      @include('agenda._mes-movel')
    @else
      {{-- Faixa da semana: cada ponto é um tipo de evento do dia. --}}
      <ol class="ag-faixa">
        @foreach ($faixa as $f)
          <li>
            <a href="{{ route('agenda.index', array_filter(['data' => $f['data']->format('Y-m-d'), 'visao' => $visaoPedida])) }}"
               @class(['atual' => $f['atual'], 'hoje' => $f['hoje']])
               aria-label="{{ DataPt::extenso($f['data']) }}" @if ($f['atual']) aria-current="date" @endif>
              <small>{{ mb_strtoupper(mb_substr(DataPt::dia($f['data']), 0, 1)) }}</small>
              <b>{{ $f['data']->format('d') }}</b>
              <span class="pontos" aria-hidden="true">
                @foreach ($f['tipos'] as $t)<i class="{{ AgendaEvento::classeDoTipo($t) }}" data-tipo="{{ $t }}"></i>@endforeach
              </span>
            </a>
          </li>
        @endforeach
      </ol>
    @endif

    @foreach ($listas as $lista)
      @include('agenda._lista', ['lista' => $lista])
    @endforeach

    <a class="ag-fab" href="#ag-adicionar" data-abre="ag-adicionar" aria-label="Adicionar à agenda">@include('agenda._icone', ['nome' => 'mais'])</a>
  </div>

  {{-- Painel do botão + no celular. --}}
  <div class="modal ag-folha" id="ag-adicionar">
    <div class="ag-folha-caixa" role="dialog" aria-modal="true" aria-labelledby="ag-adicionar-titulo">
      <span class="alca" aria-hidden="true"></span>
      <h2 id="ag-adicionar-titulo">Adicionar à agenda</h2>
      <div class="ag-folha-itens">@include('agenda._tipos-menu')</div>
      <button type="button" class="btn btn-ghost btn-block" data-fecha>Cancelar</button>
    </div>
  </div>

  {{-- Cartão de detalhe do evento (computador): preenchido ao clicar. --}}
  <div class="ag-pop" data-agenda-pop hidden></div>
</div>
@endsection
