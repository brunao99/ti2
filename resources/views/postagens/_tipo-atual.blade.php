{{-- Tipo aberto na listagem; abre a janela de troca. Sem JavaScript, leva à tela de escolha. --}}
<a class="tipo-atual {{ $classe }}" href="{{ route('postagens.index', ['escolher' => 1]) }}" data-abre="modal-tipos"
   style="--cor-tipo: var(--c-{{ $info['cor'] }})" title="Trocar o tipo de publicação">
  <i aria-hidden="true"></i>
  <span class="tipo-atual-nome">{{ $info['nome'] }}</span>
  <span class="tipo-atual-trocar">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/></svg>
    Trocar tipo
  </span>
</a>
