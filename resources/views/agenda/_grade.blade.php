{{-- Grade de horários (computador): Dia, Semana de trabalho ou Semana. As 24 horas, com rolagem. --}}
@php
    use App\Support\DataPt;
    $hora = \App\Services\Agenda\TelaAgenda::ALTURA_HORA;
@endphp
<div class="ag-grade" style="--ag-hora: {{ $hora }}px; --ag-colunas: {{ count($grade['dias']) }}">
 {{-- As 24 horas rolam dentro desta área; dias e faixa "dia todo" ficam presos no topo. --}}
 <div class="ag-rolagem" data-ag-rolagem data-rolar-para="{{ $grade['rolarPara'] }}" tabindex="0"
      aria-label="Grade de horários, de 00:00 às 24:00. Role para ver as outras horas.">
  <div class="ag-topo-fixo">
  <div class="ag-cab">
    <span></span>
    @foreach ($grade['dias'] as $dia)
      <div @class(['ag-cab-dia', 'hoje' => $dia['hoje'], 'fds' => $dia['data']->isWeekend()])>
        <b>{{ $dia['data']->format('d') }}</b>
        <span>
          {{ DataPt::diaCompleto($dia['data']) }}@if ($dia['feriados']->contains(fn ($f) => $f->ehFeriado()))<em> · feriado</em>@elseif ($dia['feriados']->isNotEmpty())<em> · ponto facultativo</em>@endif
        </span>
      </div>
    @endforeach
  </div>

  {{-- Faixa "dia todo": feriados, datas importantes e compromissos sem horário. --}}
  <div class="ag-diatodo">
    <span class="ag-diatodo-rot">dia todo</span>
    @foreach ($grade['dias'] as $dia)
      <div @class(['ag-diatodo-dia', 'fds' => $dia['data']->isWeekend()])>
        @foreach ($dia['feriados'] as $f)
          <span class="ag-faixa-item t-data t-feriado" title="{{ $f->descricaoNaAgenda() }}{{ $f->expediente ? ' · ' . $f->expediente : '' }}">
            @include('agenda._icone', ['nome' => 'bandeira'])<span>{{ $f->descricaoNaAgenda() }}</span>
          </span>
        @endforeach
        @foreach ($dia['diaTodo'] as $o)
          <a class="ag-faixa-item {{ $o->evento->classeTipo() }}" data-tipo="{{ $o->evento->tipo }}"
             href="{{ $detalhe($o) }}" data-agenda-detalhe title="{{ $o->evento->nome() }}">
            @include('agenda._icone', ['nome' => $o->evento->icone()])<span>{{ $o->evento->nome() }}</span>
          </a>
        @endforeach
      </div>
    @endforeach
  </div>

  </div>

  <div class="ag-corpo-grade" style="height: {{ $grade['altura'] }}px">
    <div class="ag-horas" aria-hidden="true">
      @foreach ($grade['horas'] as $h)
        <span style="top: {{ ($h - $grade['inicio']) * $hora }}px">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</span>
      @endforeach
    </div>

    @foreach ($grade['dias'] as $dia)
      <div @class(['ag-coluna', 'hoje' => $dia['hoje'], 'fds' => $dia['data']->isWeekend()]) aria-label="{{ DataPt::extenso($dia['data']) }}">
        @if ($dia['hoje'])
          <span class="ag-agora" style="top: {{ $grade['agora']['topo'] }}px" title="Agora, {{ $grade['agora']['hora'] }}"></span>
        @endif

        @foreach ($dia['blocos'] as $b)
          @php($o = $b['o'])
          <a href="{{ $detalhe($o) }}" data-agenda-detalhe data-tipo="{{ $o->evento->tipo }}"
             @class(['ag-ev', $o->evento->classeTipo(), 'curto' => $b['curto'], 'apagado' => $o->apagada(), 'cancelado' => $o->cancelada()])
             style="top: {{ $b['topo'] }}px; height: {{ $b['altura'] }}px; left: calc({{ $b['esquerda'] }}% + 4px); width: calc({{ $b['largura'] }}% - 8px)">
            @if ($b['curto'])
              <span class="linha">
                @include('agenda._icone', ['nome' => $o->evento->icone()])
                <span class="hora">{{ $o->evento->hora_inicio }}</span>
                <b>{{ $o->evento->nome() }}</b>
              </span>
            @else
              <b class="titulo">@include('agenda._icone', ['nome' => $o->evento->icone()]){{ $o->evento->nome() }}</b>
              @if ($b['subtitulo'])<span class="sub">{{ $b['subtitulo'] }}</span>@endif
            @endif
            @if ($o->evento->repete())
              <span class="rep" title="Repete">@include('agenda._icone', ['nome' => 'repetir'])</span>
            @endif
          </a>
        @endforeach
      </div>
    @endforeach
  </div>
 </div>
</div>
