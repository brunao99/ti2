{{-- Visualização Mês (computador). --}}
<div class="ag-mes">
  <div class="ag-mes-cab">
    @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $d)<span>{{ $d }}</span>@endforeach
  </div>

  @foreach ($mes as $semana)
    <div class="ag-mes-semana">
      @foreach ($semana as $d)
        <div @class(['ag-mes-dia', 'fora' => ! $d['noMes'], 'hoje' => $d['hoje']])>
          <a class="num" href="{{ route('agenda.index', ['data' => $d['data']->format('Y-m-d'), 'visao' => 'dia']) }}"
             aria-label="{{ \App\Support\DataPt::extenso($d['data'], true) }}">{{ $d['data']->day }}</a>

          @if ($d['noMes'])
            @foreach ($d['linhas'] as $l)
              @if (isset($l['feriado']))
                <span class="ag-faixa-item t-data t-feriado" title="{{ $l['feriado']->descricaoNaAgenda() }}">
                  @include('agenda._icone', ['nome' => 'bandeira'])<span>{{ $l['feriado']->descricao }}</span>
                </span>
              @elseif ($l['o']->semHorario())
                <a class="ag-faixa-item {{ $l['o']->evento->classeTipo() }}" data-tipo="{{ $l['o']->evento->tipo }}"
                   href="{{ $detalhe($l['o']) }}" data-agenda-detalhe title="{{ $l['o']->evento->nome() }}">
                  @include('agenda._icone', ['nome' => $l['o']->evento->icone()])<span>{{ $l['o']->evento->nome() }}</span>
                </a>
              @else
                <a @class(['ag-mes-ev', $l['o']->evento->classeTipo(), 'apagado' => $l['o']->apagada()])
                   data-tipo="{{ $l['o']->evento->tipo }}" href="{{ $detalhe($l['o']) }}" data-agenda-detalhe
                   title="{{ $l['o']->evento->hora_inicio }} {{ $l['o']->evento->nome() }}">
                  <i aria-hidden="true"></i><span class="hora">{{ $l['o']->evento->hora_inicio }}</span><span>{{ $l['o']->evento->nome() }}</span>
                </a>
              @endif
            @endforeach

            @if ($d['mais'])
              <a class="ag-mes-mais" href="{{ route('agenda.index', ['data' => $d['data']->format('Y-m-d'), 'visao' => 'dia']) }}">+{{ $d['mais'] }} mais</a>
            @endif
          @endif
        </div>
      @endforeach
    </div>
  @endforeach
</div>
