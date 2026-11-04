@extends('layouts.painel')

@php
    $info = \App\Models\Postagem::TIPOS[$tipo];
@endphp

@section('titulo', 'Publicações — ' . $info['curto'])
@section('cabecalho')
  <div class="cab-tipo">
    <h1>Publicações</h1>
    @include('postagens._tipo-atual', ['classe' => ''])
  </div>
@endsection

@section('acoes')
  @if (auth()->user()->podeGerenciarPostagens())
    <a class="btn btn-ghost" href="{{ route('relatorios.index') }}" data-modal-relatorio>Gerar relatório</a>
    <a class="btn" href="{{ route('postagens.create', ['tipo' => $tipo]) }}">Nova publicação</a>
  @endif
@endsection

@section('conteudo')

  <div class="m-only m-cabecalho">
    <h1>Publicações</h1>
    @if (auth()->user()->podeGerenciarPostagens())
      <a class="btn btn-sm" href="{{ route('postagens.create', ['tipo' => $tipo]) }}">+ Nova postagem</a>
    @endif
  </div>
  @include('postagens._tipo-atual', ['classe' => 'm-only m-tipo'])
  @if (auth()->user()->podeGerenciarPostagens())
    <a class="m-only btn btn-ghost btn-block m-relatorio" href="{{ route('relatorios.index') }}" data-modal-relatorio>Gerar relatório</a>
  @endif

  {{-- Busca por título dentro do tipo aberto. Via GET, para sobreviver ao
       recarregar e poder ser compartilhada por link. --}}
  <form method="GET" action="{{ route('postagens.index') }}" class="filters">
    <input type="hidden" name="tipo" value="{{ $tipo }}">
    <div class="filtro-busca">
      <input class="inp-box" type="search" name="busca" value="{{ $busca }}"
             placeholder="Buscar por título em {{ $info['curto'] }}" aria-label="Buscar por título">
      <button class="btn btn-sm" type="submit">Buscar</button>
      @if ($busca !== '')
        <a class="btn btn-ghost btn-sm" href="{{ route('postagens.index', ['tipo' => $tipo]) }}">Limpar</a>
      @endif
    </div>
  </form>

  {{-- No mobile a tabela vira cartões e o cabeçalho some, levando junto a
       ordenação. Este seletor devolve o controle lá. Cada opção já é a URL
       pronta, com os filtros atuais preservados. --}}
  <div class="m-only ordenar-mobile">
    <label class="label" for="ordenar">Ordenar por</label>
    <select class="inp-box" id="ordenar" onchange="location.href = this.value">
      @php
          $opcoes = [
              ['data_publicacao', 'desc', 'Data — mais recentes'],
              ['data_publicacao', 'asc',  'Data — mais antigas'],
              ['titulo',          'asc',  'Título — A a Z'],
              ['titulo',          'desc', 'Título — Z a A'],
              ['situacao',        'asc',  'Situação'],
          ];
      @endphp
      @foreach ($opcoes as [$campo, $dir, $rotulo])
        <option value="{{ request()->fullUrlWithQuery(['ordem' => $campo, 'dir' => $dir]) }}"
                @selected($ordem === $campo && $direcao === $dir)>{{ $rotulo }}</option>
      @endforeach
    </select>
  </div>

  @forelse ($porMes as $mes => $postagens)
    <div data-mes>
      <div class="month">
        <h2>{{ $postagens->first()->mesPorExtenso() }}</h2>
        <span>{{ $postagens->count() }} {{ Str::plural('publicação', $postagens->count()) }}</span>
      </div>

      <table>
        <thead>
          <tr>
            @include('postagens._cabecalho-ordenavel', ['campo' => 'titulo',          'rotulo' => 'Título',   'largura' => null])
            @include('postagens._cabecalho-ordenavel', ['campo' => 'data_publicacao', 'rotulo' => 'Data',     'largura' => '130px'])
            @include('postagens._cabecalho-ordenavel', ['campo' => 'situacao',        'rotulo' => 'Situação', 'largura' => '140px'])
            <th style="width:140px">Alerta</th>
            <th style="width:250px">Ações</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($postagens as $postagem)
            <tr>
              <td class="t-title">
                <a class="{{ $postagem->classeCor() }}" href="{{ route('postagens.show', $postagem) }}">{{ $postagem->titulo }}</a>
              </td>
              <td class="t-date">{{ $postagem->data_publicacao->format('d/m/Y') }}</td>
              <td>
                <span class="badge {{ $postagem->ehRascunho() ? 'badge-draft' : 'badge-ok' }}">
                  {{ $postagem->ehRascunho() ? 'Rascunho' : 'Publicado' }}
                </span>
              </td>
              <td class="muted">
                <span class="dot {{ $postagem->alerta_enviado ? 'on' : '' }}"></span>
                {{ $postagem->alerta_enviado ? 'Enviado' : 'Não enviado' }}
              </td>
              <td>
                <div class="acts">
                  <a class="btn btn-ghost btn-sm" href="{{ route('postagens.show', $postagem) }}">Visualizar</a>

                  {{-- Client não vê botão de edição. --}}
                  @if (auth()->user()->podeGerenciarPostagens())
                    @if ($postagem->situacao === 'publicado' && ! $postagem->alerta_enviado)
                      <a class="btn btn-sm" href="{{ route('alertas.enviar', $postagem) }}" data-modal-envio>Enviar alerta</a>
                    @endif

                    <a class="btn btn-ghost btn-sm" href="{{ route('postagens.edit', $postagem) }}">Editar</a>

                    <form method="POST" action="{{ route('postagens.destroy', $postagem) }}"
                          onsubmit="return confirm('Excluir esta publicação? Não dá para desfazer.')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm" type="submit">Excluir</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @empty
    <div class="empty show">
      @if ($busca !== '')
        <h3>Nada encontrado para “{{ $busca }}”</h3>
        <p>A busca procura no título das publicações de {{ $info['nome'] }}.</p>
      @else
        <h3>Nenhuma publicação de {{ $info['nome'] }}</h3>
        <p>Quando houver, elas aparecem aqui, agrupadas por mês.</p>
      @endif
    </div>
  @endforelse

  {{-- Troca de tipo sem sair da listagem. A janela deixa a navegação
       (barra lateral e barra inferior) visível e clicável. --}}
  <div class="modal modal-tipos" id="modal-tipos">
    <div class="modal-box tipos-box" role="dialog" aria-modal="true" aria-labelledby="tipos-titulo">
      <header class="modal-head">
        <div>
          <p class="label">Publicações</p>
          <h2 id="tipos-titulo">Trocar tipo</h2>
        </div>
        <button type="button" class="x" data-fecha aria-label="Fechar">✕</button>
      </header>
      <div class="modal-body">
        @include('postagens._tipos', ['resumo' => $resumo, 'atual' => $tipo])
      </div>
    </div>
  </div>

@endsection
