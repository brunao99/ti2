{{-- Visualização Mês (celular): o mês com os pontos; a lista do dia escolhido vem abaixo. --}}
<table class="ag-m-grade">
  <thead><tr>@foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $l)<th>{{ $l }}</th>@endforeach</tr></thead>
  <tbody>
    @foreach ($mes as $semana)
      <tr>
        @foreach ($semana as $d)
          <td>
            <a href="{{ route('agenda.index', ['data' => $d['data']->format('Y-m-d'), 'visao' => 'mes']) }}"
               @class(['fora' => ! $d['noMes'], 'hoje' => $d['hoje'], 'atual' => $d['data']->equalTo($data), 'feriado' => $d['feriado']])
               aria-label="{{ \App\Support\DataPt::extenso($d['data'], true) }}">
              <b>{{ $d['data']->day }}</b>
              <span class="pontos" aria-hidden="true">
                @foreach ($d['tipos'] as $t)<i class="{{ \App\Models\AgendaEvento::classeDoTipo($t) }}" data-tipo="{{ $t }}"></i>@endforeach
              </span>
            </a>
          </td>
        @endforeach
      </tr>
    @endforeach
  </tbody>
</table>
