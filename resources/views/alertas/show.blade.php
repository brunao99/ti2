@extends('layouts.painel')

@section('titulo', 'Detalhes do disparo')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.index') }}"><span>←</span> Histórico</a>
  <h1 style="margin-top:.3rem">Detalhes do disparo</h1>
@endsection

@section('conteudo')
  <article class="doc">
    <div class="meta">
      <span class="badge {{ $disparo->classeStatus() }}">{{ $disparo->rotuloStatus() }}</span>
      <span>{{ $disparo->created_at->format('d/m/Y H:i') }}</span>
    </div>

    <h1>{{ $disparo->postagem->titulo }}</h1>
    <p class="muted">
      Modelo: {{ $disparo->modelo->nome }}
      · Responsável: {{ $disparo->administrador->nome }}
    </p>

    <div class="hr"></div>
    <p><b>Mensagem enviada</b></p>
    <div class="corpo" style="white-space:pre-wrap">{{ $disparo->mensagem }}</div>

    <div class="hr"></div>
    <p><b>Resultado</b></p>
    <div class="cabecalho-linha">
      <p class="muted">
        {{ $disparo->total_enviados }} enviados ·
        {{ $disparo->total_falhas }} falhas ·
        {{ $disparo->destinatarios->where('status', 'ignorado')->count() }} ignorados
      </p>
      @include('alertas._reenviar')
    </div>
  </article>

  <table style="margin-top:1rem">
    <thead>
      <tr><th>Usuário</th><th>Telefone</th><th>Status</th><th>Tentativas</th><th>Resultado</th></tr>
    </thead>
    <tbody>
      @foreach ($disparo->destinatarios as $destinatario)
        <tr>
          <td>{{ $destinatario->nome }}</td>
          <td class="mono">{{ \App\Support\Telefone::formatar($destinatario->telefone) }}</td>
          <td>
            <span @class(['txt-falha' => $destinatario->status === 'falha'])>{{ ucfirst($destinatario->status) }}</span>
          </td>
          <td>{{ $destinatario->tentativas ?: '—' }}</td>
          <td class="muted">{{ $destinatario->resultado }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
