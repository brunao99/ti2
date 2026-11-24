@php
    use App\Support\Asset;

    // Contato da empresa, o mesmo do site atual.
    $telefone = '(31) 98429-4085';
    $email = 'cdalgoritmosedados@gmail.com';
    $whatsapp = 'https://wa.me/5531984294085?text=' . rawurlencode('Olá! Gostaria de conhecer a consultoria da Algoritmos e Dados para o meu município.');
    $usuario = auth()->user();
    $hoje = now('America/Sao_Paulo')->format('d/m/Y');
    $foto = fn (string $arquivo) => asset('assets/site/' . $arquivo);

    // [PARTE 3 - Gabriel] lista dos objetivos
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Algoritmos e Dados — Consultoria em gestão pública para prefeituras</title>
<meta name="theme-color" content="#133049">
@include('layouts.favicon')
@include('layouts.social')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600&display=swap">
<link rel="stylesheet" href="{{ Asset::url('assets/site.css') }}">
<script src="{{ Asset::url('assets/site.js') }}" defer></script>
</head>
<body class="site">

<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<header class="topo" data-topo>
  <div class="wrap topo-in">
    <a class="marca" href="{{ route('inicio') }}" aria-label="Algoritmos e Dados, início">
      <span class="marca-nome">Algoritmos e Dados</span>
      @include('layouts.logo')
    </a>

    <nav class="menu" id="menu" aria-label="Seções da página">
      <a href="#objetivos">Objetivos</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#sobre">Sobre</a>
      <a href="#contato">Contato</a>
      <a class="menu-acesso" href="{{ $usuario ? route('painel.inicio') : '#acesso' }}">{{ $usuario ? 'Abrir o painel' : 'Acessar o painel' }}</a>
    </nav>

    <a class="botao botao-cheio botao-pequeno topo-whats" href="{{ $whatsapp }}" target="_blank" rel="noopener">
      @include('site._icone', ['nome' => 'whatsapp']) Fale conosco
    </a>

    <button class="menu-botao" type="button" aria-label="Menu" aria-controls="menu" aria-expanded="false" data-menu-botao>
      <span class="menu-botao-icone" aria-hidden="true"><i></i><i></i></span>
      <span class="menu-botao-texto">Menu</span>
    </button>
  </div>
</header>

<main id="conteudo">

  {{-- [PARTE 2 - Victor] primeira tela e acesso ao painel --}}

  {{-- [PARTE 3 - Gabriel] objetivos --}}

  {{-- [PARTE 4 - Lucas] como funciona e sobre --}}
</main>

{{-- [PARTE 4 - Lucas] contato e rodapé --}}

</body>
</html>
