{{-- Lista de um dia (celular). --}}
<section class="ag-lista" aria-label="{{ \App\Support\DataPt::extenso($lista['data']) }}">
  <h2 class="label">{{ \App\Support\DataPt::extenso($lista['data']) }}@if ($lista['hoje']) · hoje @endif</h2>

  @foreach ($lista['feriados'] as $f)
    <p class="ag-faixa-item t-data t-feriado">@include('agenda._icone', ['nome' => 'bandeira'])<span>{{ $f->descricaoNaAgenda() }}</span></p>
  @endforeach
  @foreach ($lista['diaTodo'] as $o)
    <a class="ag-faixa-item {{ $o->evento->classeTipo() }}" data-tipo="{{ $o->evento->tipo }}" href="{{ $detalhe($o) }}" data-agenda-detalhe>
      @include('agenda._icone', ['nome' => $o->evento->icone()])<span>{{ $o->evento->nome() }}</span>
    </a>
  @endforeach

  @forelse ($lista['itens'] as $item)
    @if (isset($item['agora']))
      <div class="ag-l-agora" aria-label="Agora, {{ $item['agora'] }}"><span>{{ $item['agora'] }}</span></div>
    @else
      @php($o = $item['o'])
      <a href="{{ $detalhe($o) }}" data-agenda-detalhe data-tipo="{{ $o->evento->tipo }}"
         @class(['ag-l-item', $o->evento->classeTipo(), 'apagado' => $o->apagada()])>
        <span class="ag-l-hora">
          <b>{{ $o->evento->hora_inicio }}</b>
          @if ($o->evento->hora_fim)<small>{{ $o->evento->hora_fim }}</small>@endif
        </span>
        <span class="ag-l-cartao">
          <b>@include('agenda._icone', ['nome' => $o->evento->icone()]){{ $o->evento->nome() }}
            @if ($o->evento->repete())<span class="rep" title="Repete">@include('agenda._icone', ['nome' => 'repetir'])</span>@endif
          </b>
          @if ($item['subtitulo'])<small>{{ $item['subtitulo'] }}</small>@endif
        </span>
      </a>
    @endif
  @empty
    @if ($lista['feriados']->isEmpty() && $lista['diaTodo']->isEmpty())
      <p class="ag-vazio">Nada marcado neste dia.</p>
    @endif
  @endforelse
</section>
