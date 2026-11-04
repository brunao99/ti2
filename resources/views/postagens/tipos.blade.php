@extends('layouts.painel')

{{-- Primeira tela de Publicações depois do login: escolher o tipo. --}}

@section('titulo', 'Publicações')
@section('cabecalho')
  <h1>Publicações</h1>
@endsection

@section('acoes')
  @if (auth()->user()->podeGerenciarPostagens())
    <a class="btn btn-ghost" href="{{ route('relatorios.index') }}" data-modal-relatorio>Gerar relatório</a>
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
  @if (auth()->user()->podeGerenciarPostagens())
    <a class="m-only btn btn-ghost btn-block m-relatorio" href="{{ route('relatorios.index') }}" data-modal-relatorio>Gerar relatório</a>
  @endif

  <div class="tipos-escolha">
    <p class="label">Escolha o tipo de publicação</p>
    <p class="tipos-ajuda">Cada tipo tem a sua listagem. Para trocar depois, use o botão do tipo ao lado do título.</p>
    @include('postagens._tipos', ['resumo' => $resumo, 'atual' => $atual])
  </div>

@endsection
