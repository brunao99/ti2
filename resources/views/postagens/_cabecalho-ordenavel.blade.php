{{--
  Cabeçalho de coluna que ordena ao ser clicado.

  Recebe: $campo, $rotulo, $ordem, $direcao e opcionalmente $largura.

  fullUrlWithQuery preserva os filtros já aplicados (tipos marcados, busca) e
  só troca ordem/dir — sem isso, ordenar zeraria o filtro do usuário.
--}}
@php
    $ativo = $ordem === $campo;
    // Clicar na coluna já ativa inverte; numa nova coluna começa crescente.
    $proxima = ($ativo && $direcao === 'asc') ? 'desc' : 'asc';
@endphp

<th @if (! empty($largura)) style="width:{{ $largura }}" @endif
    aria-sort="{{ $ativo ? ($direcao === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a class="th-ordem @if ($ativo) on @endif"
       href="{{ request()->fullUrlWithQuery(['ordem' => $campo, 'dir' => $proxima]) }}"
       title="Ordenar por {{ $rotulo }}{{ $ativo && $direcao === 'asc' ? ' (decrescente)' : ' (crescente)' }}">
        {{ $rotulo }}<span class="seta" aria-hidden="true">{{ $ativo ? ($direcao === 'asc' ? '↑' : '↓') : '↕' }}</span>
    </a>
</th>
