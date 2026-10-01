@extends('layouts.painel')

@section('titulo', 'Gestão de Alertas')

@section('cabecalho')
  <h1>Gestão de alertas</h1>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('alertas.modelos') }}">Modelos</a>
  <a class="btn btn-ghost" href="{{ route('alertas.listas') }}">Listas</a>
  <a class="btn btn-ghost" href="{{ route('alertas.contatos') }}">Contatos</a>
@endsection

@section('conteudo')
  <div class="filters" style="margin-bottom:1.5rem">
    <div>
      <p class="label">Histórico de disparos</p>
      <p class="muted">Cada registro mostra a publicação, o modelo, a lista e o administrador responsável.</p>
    </div>
  </div>

  @forelse ($disparos as $disparo)
    <article class="doc" style="margin-bottom:1rem">
      <div class="meta">
        <span class="badge badge-ok">{{ ucfirst($disparo->status) }}</span>
        <span>{{ $disparo->created_at->format('d/m/Y H:i') }}</span>
        <span>{{ $disparo->administrador->nome }}</span>
      </div>
      <h2 style="margin-bottom:.4rem">{{ $disparo->postagem->titulo }}</h2>
      <p class="muted">
        Modelo: {{ $disparo->modelo->nome }} · Lista: {{ $disparo->lista->nome }}
        · {{ $disparo->total_enviados }}/{{ $disparo->total_destinatarios }} enviados
        @if ($disparo->total_falhas)
          · {{ $disparo->total_falhas }} falha(s)
        @endif
      </p>
      <a class="btn btn-ghost btn-sm" href="{{ route('alertas.show', $disparo) }}">Ver detalhes</a>
    </article>
  @empty
    <div class="empty show">
      <h3>Nenhum disparo registrado</h3>
      <p>O histórico aparecerá aqui depois do primeiro envio.</p>
    </div>
  @endforelse
@endsection
