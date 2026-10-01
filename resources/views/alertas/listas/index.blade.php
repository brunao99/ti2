@extends('layouts.painel')

@section('titulo', 'Listas de destinatários')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.index') }}"><span>←</span> Alertas</a>
  <h1 style="margin-top:.3rem">Listas de destinatários</h1>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('alertas.contatos') }}">Gerenciar contatos</a>
  <a class="btn" href="{{ route('alertas.listas.nova') }}">Nova lista</a>
@endsection

@section('conteudo')
  @forelse ($listas as $lista)
    <article class="doc" style="margin-bottom:1rem">
      <h2>{{ $lista->nome }}</h2>
      <p class="muted">{{ $lista->descricao ?: 'Sem descrição' }} · {{ $lista->contatos_count }} contato(s)</p>
      <a class="btn btn-ghost btn-sm" href="{{ route('alertas.listas.editar', $lista) }}">Editar</a>
    </article>
  @empty
    <div class="empty show"><h3>Nenhuma lista cadastrada</h3></div>
  @endforelse
@endsection
