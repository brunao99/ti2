@php
    $imagemCompartilhamento = \App\Support\Asset::url('assets/images/compartilhamento.png');
    $tituloCompartilhamento = 'Algoritmos e Dados — Consultoria';
    $descricaoCompartilhamento = 'Transparência e acesso à informação para todo cidadão';
@endphp
<meta name="description" content="{{ $descricaoCompartilhamento }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="Algoritmos e Dados">
<meta property="og:title" content="{{ $tituloCompartilhamento }}">
<meta property="og:description" content="{{ $descricaoCompartilhamento }}">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:image" content="{{ $imagemCompartilhamento }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Algoritmos e Dados Consultoria — Transparência e acesso à informação para todo cidadão">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $tituloCompartilhamento }}">
<meta name="twitter:description" content="{{ $descricaoCompartilhamento }}">
<meta name="twitter:image" content="{{ $imagemCompartilhamento }}">
<meta name="twitter:image:alt" content="Algoritmos e Dados Consultoria — Transparência e acesso à informação para todo cidadão">
