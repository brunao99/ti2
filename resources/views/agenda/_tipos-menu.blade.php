{{-- Os 4 tipos de evento: menu "Novo" (computador) e painel do + (celular). --}}
@foreach (\App\Models\AgendaEvento::TIPOS as $tipo => $info)
  @if ($tipo === \App\Models\AgendaEvento::ALERTA && ! $temPublicacao)
    <span class="ag-tipo-item {{ \App\Models\AgendaEvento::classeDoTipo($tipo) }}" aria-disabled="true"
          title="Para programar um alerta é preciso ter ao menos uma publicação publicada.">
      <span class="ag-tipo-ico">@include('agenda._icone', ['nome' => $info['icone']])</span>
      <span><b>{{ $info['nome'] }}</b><small>Publique uma notícia antes de programar</small></span>
    </span>
  @else
    <a class="ag-tipo-item {{ \App\Models\AgendaEvento::classeDoTipo($tipo) }}" role="menuitem" data-modal-agenda
       href="{{ route('agenda.eventos.criar', array_filter(['tipo' => $tipo, 'data' => $data->format('Y-m-d'), 'visao' => $visaoPedida])) }}">
      <span class="ag-tipo-ico">@include('agenda._icone', ['nome' => $info['icone']])</span>
      <span><b>{{ $info['nome'] }}</b><small>{{ $info['desc'] }}</small></span>
    </a>
  @endif
@endforeach
