@extends('layouts.painel')

@section('titulo', 'Central de alertas')

@section('cabecalho')
  <h1>Central de alertas</h1>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('alertas.modelos') }}">Modelos</a>
@endsection

@section('conteudo')

  <div class="m-only m-cabecalho">
    <h1>Central de alertas</h1>
    <a class="btn btn-ghost btn-sm" href="{{ route('alertas.modelos') }}">Modelos</a>
  </div>

  {{-- ------------------------------------------------------------ medidor --}}
  <section class="medidor" aria-labelledby="medidor-titulo">
    <div class="medidor-principal">
      <p class="label" id="medidor-titulo">Taxa de entrega</p>

      @if ($medidor['taxa'] === null)
        <p class="medidor-taxa">—</p>
        <p class="muted">Nenhuma mensagem enviada ainda.</p>
      @else
        <p class="medidor-taxa">
          {{ rtrim(rtrim(number_format($medidor['taxa'], 1, ',', ''), '0'), ',') }}<span>%</span>
        </p>

        {{-- Barra: entregues × falharam. Ignorados ficam fora, nada foi tentado. --}}
        <div class="medidor-barra" role="img"
             aria-label="{{ $medidor['enviados'] }} mensagens entregues e {{ $medidor['falhas'] }} com falha">
          @if ($medidor['enviados'])
            <span class="seg seg-ok" style="flex-grow:{{ $medidor['enviados'] }}"
                  title="{{ $medidor['enviados'] }} entregues"></span>
          @endif
          @if ($medidor['falhas'])
            <span class="seg seg-falha" style="flex-grow:{{ $medidor['falhas'] }}"
                  title="{{ $medidor['falhas'] }} com falha"></span>
          @endif
        </div>

        <ul class="medidor-legenda">
          <li>
            <span class="ponto ponto-ok" aria-hidden="true">✓</span>
            <b>{{ $medidor['enviados'] }}</b> {{ $medidor['enviados'] === 1 ? 'entregue' : 'entregues' }}
          </li>
          <li>
            <span class="ponto ponto-falha" aria-hidden="true">✕</span>
            <b>{{ $medidor['falhas'] }}</b> {{ $medidor['falhas'] === 1 ? 'falhou' : 'falharam' }}
          </li>
        </ul>
      @endif
    </div>

    <div class="medidor-numeros">
      <div>
        <span class="label">Disparos</span>
        <b>{{ $medidor['disparos'] }}</b>
      </div>
      <div @class(['alerta-pendente' => $medidor['pendentes'] > 0])>
        <span class="label">Com falha a reenviar</span>
        <b>{{ $medidor['pendentes'] }}</b>
      </div>
      <div>
        <span class="label">Ignorados</span>
        <b>{{ $medidor['ignorados'] }}</b>
        <small>sem autorização</small>
      </div>
    </div>
  </section>

  {{-- ------------------------------------------------------------ histórico --}}
  <div class="sec-head">
    <h2>Histórico de disparos</h2>
  </div>

  @forelse ($disparos as $disparo)
    <article @class(['disparo', 'disparo-falha' => $disparo->temFalhas()])>
      <div class="disparo-info">
        <div class="meta">
          <span class="badge {{ $disparo->classeStatus() }}">{{ $disparo->rotuloStatus() }}</span>
          <span>{{ $disparo->created_at->format('d/m/Y H:i') }}</span>
          <span>{{ $disparo->administrador->nome }}</span>
        </div>
        <h3>{{ $disparo->postagem->titulo }}</h3>
        <p class="muted">
          Modelo: {{ $disparo->modelo->nome }}
          · <b class="disparo-conta">{{ $disparo->total_enviados }} de {{ $disparo->total_destinatarios }}</b> entregues
          @if ($disparo->total_falhas)
            · <span class="txt-falha">{{ $disparo->total_falhas }} com falha</span>
          @endif
        </p>
      </div>

      <div class="disparo-acoes">
        @include('alertas._reenviar')
        <a class="btn btn-ghost btn-sm" href="{{ route('alertas.show', $disparo) }}">Ver detalhes</a>
      </div>
    </article>
  @empty
    <div class="empty show">
      <h3>Nenhum disparo registrado</h3>
      <p>O histórico aparecerá aqui depois do primeiro envio.</p>
    </div>
  @endforelse
@endsection
