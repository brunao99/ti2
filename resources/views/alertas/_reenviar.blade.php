{{-- Botão de reenvio: só aparece se o disparo tem falhas. --}}
@if ($disparo->temFalhas())
  <form method="POST" action="{{ route('alertas.reenviar', $disparo) }}" data-reenviar>
    @csrf
    <button type="submit" class="btn btn-sm btn-reenviar">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><polyline points="21 3 21 9 15 9"/></svg>
      Reenviar {{ $disparo->total_falhas }} {{ $disparo->total_falhas === 1 ? 'falha' : 'falhas' }}
    </button>
  </form>
@endif
