@extends('layouts.painel')

@section('titulo', 'Modelos de mensagem')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.index') }}"><span>←</span> Alertas</a>
  <h1 style="margin-top:.3rem">Modelos de mensagem</h1>
@endsection

@section('acoes')
  <a class="btn" href="{{ route('alertas.modelos.novo') }}">Novo modelo</a>
@endsection

@section('conteudo')
  @forelse ($modelos as $modelo)
    <article class="doc" style="margin-bottom:1rem">
      <h2>{{ $modelo->nome }}</h2>
      <p class="corpo" style="white-space:pre-wrap">{{ $modelo->mensagem }}</p>
      <a class="btn btn-ghost btn-sm" href="{{ route('alertas.modelos.editar', $modelo) }}">Editar</a>
    </article>
  @empty
    <div class="empty show"><h3>Nenhum modelo cadastrado</h3></div>
  @endforelse
@endsection
