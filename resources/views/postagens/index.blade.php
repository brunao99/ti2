@extends('layouts.painel')

@section('titulo', 'Publicações')
@section('cabecalho')
  <h1>Publicações</h1>
@endsection

@section('acoes')
  @if (auth()->user()->podeGerenciarPostagens())
    <a class="btn" href="{{ route('postagens.create') }}">Nova publicação</a>
  @endif
@endsection

@section('conteudo')

  <div class="m-only m-cabecalho">
    <h1>Publicações</h1>
    @if (auth()->user()->podeGerenciarPostagens())
      <a class="btn btn-sm" href="{{ route('postagens.create') }}">+ Nova postagem</a>
    @endif
  </div>

  {{-- Filtro por tipo e busca por título. Tudo via GET, para o filtro
       sobreviver ao recarregar e poder ser compartilhado por link. --}}
  {{-- Caixas de marcação em vez de escolha única: com dez tipos, o mais comum
       é querer ver dois ou três juntos. Nada marcado = todos. --}}
  <form method="GET" action="{{ route('postagens.index') }}" class="filters">

    <div class="filtro-busca">
      <input class="inp-box" type="search" name="busca" value="{{ $busca }}"
             placeholder="Buscar por título">
      <button class="btn btn-sm" type="submit">Filtrar</button>
      @if ($tiposAtivos || $busca !== '')
        <a class="btn btn-ghost btn-sm" href="{{ route('postagens.index') }}">Limpar</a>
      @endif
    </div>

    <fieldset class="filtro-tipos">
      <legend class="label">
        Tipos
        @if ($tiposAtivos)
          <span class="conta-sel">{{ count($tiposAtivos) }} de {{ count(\App\Models\Postagem::TIPOS) }}</span>
        @endif
      </legend>

      <div class="tipos-grade">
        @foreach (\App\Models\Postagem::TIPOS as $valor => $rotulo)
          <label class="pill-check" title="{{ $rotulo['nome'] }}">
            <input type="checkbox" name="tipos[]" value="{{ $valor }}"
                   @checked(in_array($valor, $tiposAtivos, true))>
            <span>{{ $rotulo['curto'] }}</span>
          </label>
        @endforeach
      </div>
    </fieldset>
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
              ['tipo',            'asc',  'Tipo'],
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
            @include('postagens._cabecalho-ordenavel', ['campo' => 'tipo',            'rotulo' => 'Tipo',     'largura' => '150px'])
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
              <td><span class="tag" title="{{ $postagem->rotuloTipo() }}">{{ $postagem->rotuloTipoCurto() }}</span></td>
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
      <h3>Nenhuma publicação encontrada</h3>
      <p>Ajuste o filtro de tipo ou limpe o campo de busca.</p>
    </div>
  @endforelse

@endsection
