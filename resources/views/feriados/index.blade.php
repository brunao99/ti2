@extends('layouts.painel')

@php
    use App\Models\Feriado;
    use App\Support\DataPt;
@endphp

@section('titulo', 'Calendário de feriados')

@section('cabecalho')
  <div class="fer-cab">
    <a class="fer-voltar" href="{{ route('agenda.index') }}">@include('agenda._icone', ['nome' => 'esquerda']) Agenda</a>
    <h1>Calendário de feriados</h1>
    <p class="muted">Feriados, pontos facultativos e datas que tornam um dia não útil. Aparecem na agenda e contam nos prazos.</p>
  </div>
@endsection

@section('acoes')
  <form method="POST" action="{{ route('feriados.copiar') }}"
        data-confirmar="Copiar para {{ $ano + 1 }} as datas de {{ $ano }} que valem só neste ano (as móveis, como Carnaval)? As que repetem todo ano já valem em {{ $ano + 1 }}.">
    @csrf
    <input type="hidden" name="ano" value="{{ $ano }}">
    <button class="btn btn-ghost" type="submit">@include('agenda._icone', ['nome' => 'copiar']) Copiar para {{ $ano + 1 }}</button>
  </form>
  <a class="btn" href="{{ route('feriados.criar', ['ano' => $ano]) }}" data-modal-agenda>@include('agenda._icone', ['nome' => 'mais']) Novo feriado</a>
@endsection

@section('conteudo')

  <div class="m-only fer-m-cab">
    <a class="voltar" href="{{ route('agenda.index') }}"><span>←</span> Agenda</a>
    <h1>Calendário de feriados</h1>
    <p class="muted">Feriados e pontos facultativos. Aparecem na agenda e contam nos prazos.</p>
    <div class="fer-m-acoes">
      <a class="btn btn-sm" href="{{ route('feriados.criar', ['ano' => $ano]) }}" data-modal-agenda>+ Novo feriado</a>
      <form method="POST" action="{{ route('feriados.copiar') }}"
            data-confirmar="Copiar para {{ $ano + 1 }} as datas de {{ $ano }} que valem só neste ano?">
        @csrf
        <input type="hidden" name="ano" value="{{ $ano }}">
        <button class="btn btn-ghost btn-sm" type="submit">Copiar para {{ $ano + 1 }}</button>
      </form>
    </div>
  </div>

  {{-- Filtros: GET, para a escolha sobreviver ao recarregar. Mudam sozinhos ao escolher. --}}
  <form method="GET" action="{{ route('feriados.index') }}" class="fer-filtros" data-filtro-auto>
    <select class="inp-box" name="ano" aria-label="Ano">
      @foreach ($anos as $a)<option value="{{ $a }}" @selected($a === $ano)>{{ $a }}</option>@endforeach
    </select>
    <select class="inp-box" name="tipo" aria-label="Tipo">
      <option value="">Todos os tipos</option>
      @foreach (Feriado::TIPOS as $v => $r)<option value="{{ $v }}" @selected($tipo === $v)>{{ $r }}</option>@endforeach
    </select>
    <select class="inp-box" name="abrangencia" aria-label="Abrangência">
      <option value="">Todas as abrangências</option>
      @foreach (Feriado::ABRANGENCIAS as $v => $r)<option value="{{ $v }}" @selected($abrangencia === $v)>{{ $r }}</option>@endforeach
    </select>
    <input class="inp-box fer-busca" type="search" name="busca" value="{{ $busca }}" placeholder="Buscar feriado" aria-label="Buscar feriado">
    <noscript><button class="btn btn-sm" type="submit">Filtrar</button></noscript>

    <p class="fer-resumo">
      <span><b>{{ $resumo['feriados'] }}</b> {{ $resumo['feriados'] === 1 ? 'feriado' : 'feriados' }}</span>
      <span><b>{{ $resumo['pontos'] }}</b> {{ $resumo['pontos'] === 1 ? 'ponto facultativo' : 'pontos facultativos' }}</span>
      <span><b>{{ $resumo['restantes'] }}</b> até o fim do ano</span>
    </p>
  </form>

  @forelse ($porMes as $mes => $datas)
    <section class="fer-mes">
      <div class="month">
        <h2>{{ DataPt::mesAno($datas->first()['data']) }}</h2>
        <span>{{ $datas->count() }} {{ $datas->count() === 1 ? 'data' : 'datas' }}</span>
      </div>

      <table class="fer-tabela">
        <thead>
          <tr>
            <th style="width:150px">Data</th>
            <th>Descrição</th>
            <th style="width:230px">Tipo</th>
            <th style="width:160px">Repete</th>
            <th style="width:100px"><span class="sr">Ações</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($datas as $d)
            @php
                $f = $d['feriado'];
                $proximo = $proxima && $proxima['feriado']->is($f) && $proxima['data']->equalTo($d['data']);
            @endphp
            <tr @class(['passou' => $d['data']->lt($hoje), 'proximo' => $proximo])>
              <td class="fer-data"><b>{{ $d['data']->format('d/m/Y') }}</b><small>{{ DataPt::dia($d['data']) }}</small></td>
              <td class="fer-desc">
                <b>{{ $f->descricao }}</b>
                @if ($proximo)<span class="fer-prox">Próximo</span>@endif
                @if ($f->expediente)<small>{{ $f->expediente }}</small>@endif
              </td>
              <td><span class="fer-etiqueta {{ $f->classeEtiqueta() }}"><i></i>{{ $f->etiqueta() }}</span></td>
              <td class="fer-repete">
                @if ($f->repete_todo_ano)
                  @include('agenda._icone', ['nome' => 'repetir']) Todo ano
                @else
                  @include('agenda._icone', ['nome' => 'calendario']) Só em {{ $f->data->year }}
                @endif
              </td>
              <td class="fer-acoes">
                <a href="{{ route('feriados.editar', ['feriado' => $f, 'ano' => $ano]) }}" data-modal-agenda
                   aria-label="Editar {{ $f->descricao }}" title="Editar">@include('agenda._icone', ['nome' => 'lapis'])</a>
                <form method="POST" action="{{ route('feriados.excluir', $f) }}"
                      data-confirmar="Excluir “{{ $f->descricao }}” do calendário?{{ $f->repete_todo_ano ? ' A data sai de todos os anos.' : '' }}">
                  @csrf
                  @method('DELETE')
                  <input type="hidden" name="ano" value="{{ $ano }}">
                  <button type="submit" aria-label="Excluir {{ $f->descricao }}" title="Excluir">@include('agenda._icone', ['nome' => 'lixeira'])</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  @empty
    <div class="empty show">
      @if ($busca !== '' || $tipo !== '' || $abrangencia !== '')
        <h3>Nenhuma data encontrada</h3>
        <p>Nenhuma data de {{ $ano }} atende aos filtros escolhidos.</p>
      @else
        <h3>Nenhuma data em {{ $ano }}</h3>
        <p>Cadastre os feriados pelo botão “Novo feriado”.</p>
      @endif
    </div>
  @endforelse

@endsection
