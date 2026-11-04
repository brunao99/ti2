{{-- Tipo aberto na listagem; abre a janela de troca. Sem JavaScript, leva à tela de escolha. --}}
<a class="tipo-atual {{ $classe }}" href="{{ route('postagens.index', ['escolher' => 1]) }}" data-abre="modal-tipos"
   style="--cor-tipo: var(--c-{{ $info['cor'] }})" title="Trocar o tipo de publicação">
  <i aria-hidden="true"></i>
  <span class="tipo-atual-nome">{{ $info['nome'] }}</span>
  <span class="tipo-atual-trocar">Trocar tipo</span>
</a>
