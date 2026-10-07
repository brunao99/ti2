@extends('layouts.painel')

@section('titulo', 'Detalhes do disparo')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.index') }}"><span>←</span> Histórico</a>
  <h1 style="margin-top:.3rem">Detalhes do disparo</h1>
@endsection

@section('conteudo')
  <article class="doc">
    <div class="meta">
      <span class="badge badge-ok">{{ ucfirst($disparo->status) }}</span>
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
    <p class="muted">
      {{ $disparo->total_enviados }} enviados ·
      {{ $disparo->total_falhas }} falhas ·
      {{ $disparo->destinatarios->where('status', 'ignorado')->count() }} ignorados por não terem autorizado
    </p>
  </article>

  <table style="margin-top:1rem">
    <thead>
      <tr><th>Usuário</th><th>Telefone</th><th>Status</th><th>Resultado</th></tr>
    </thead>
    <tbody>
      @foreach ($disparo->destinatarios as $destinatario)
        <tr>
          <td>{{ $destinatario->nome }}</td>
          <td class="mono">{{ \App\Support\Telefone::formatar($destinatario->telefone) }}</td>
          <td>{{ ucfirst($destinatario->status) }}</td>
          <td class="muted">{{ $destinatario->resultado }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
