{{--
  Cartões dos tipos de publicação: na tela de escolha e na janela de troca.
  Cada cartão leva à listagem do tipo. $atual marca o tipo aberto agora.
--}}
<ul class="tipos-lista">
  @foreach (\App\Models\Postagem::TIPOS as $valor => $info)
    @php($r = $resumo[$valor])
    <li>
      <a href="{{ route('postagens.index', ['tipo' => $valor]) }}"
         @class(['tipo-card', 'on' => $valor === ($atual ?? null)])
         style="--cor-tipo: var(--c-{{ $info['cor'] }})"
         @if ($valor === ($atual ?? null)) aria-current="true" @endif>
        <b>{{ $info['nome'] }}</b>
        @if ($r['total'] === 0)
          <span class="tipo-num muted">Nenhuma publicação ainda</span>
        @else
          <span class="tipo-num">
            {{ $r['total'] }} {{ $r['total'] === 1 ? 'publicação' : 'publicações' }}@if ($r['rascunhos'] > 0) · {{ $r['rascunhos'] }} {{ $r['rascunhos'] === 1 ? 'rascunho' : 'rascunhos' }}@endif
          </span>
          <span class="tipo-ultima">Mais recente em {{ $r['ultima']->format('d/m/Y') }}</span>
        @endif
        @if ($valor === ($atual ?? null))
          <span class="tipo-aberto">Aberto agora</span>
        @endif
      </a>
    </li>
  @endforeach
</ul>
