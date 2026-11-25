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

  {{-- ============================================== início, com o acesso ao painel --}}
  <section class="heroi" id="inicio" aria-labelledby="heroi-titulo">
    <img class="heroi-fundo" src="{{ $foto('heroi-ouro-preto.jpg') }}" width="2000" height="1200" alt="" aria-hidden="true" fetchpriority="high">

    <div class="wrap heroi-grade">
      <div class="heroi-texto">
        <h1 id="heroi-titulo" class="entra">Consultoria em gestão pública para prefeituras</h1>
        <p class="heroi-lide entra">
          Programas dos ministérios e do Governo de Minas, editais e financiamentos na CAIXA, no BNDES e no
          Banco do Brasil, selecionados para o seu município e entregues ao prefeito em um painel próprio,
          com alerta no WhatsApp.
        </p>
        <div class="acoes entra">
          <a class="botao botao-claro" href="{{ $whatsapp }}" target="_blank" rel="noopener">
            @include('site._icone', ['nome' => 'whatsapp']) Falar no WhatsApp
          </a>
          <a class="botao botao-linha-clara" href="#objetivos">Conheça os objetivos</a>
        </div>
        <p class="heroi-frase entra">“É preciso ficar antenado para não deixar as oportunidades passarem.”</p>
      </div>

      {{-- Acesso ao painel: o login fica na primeira tela, como no site atual. --}}
      <div class="acesso entra" id="acesso">
        @if ($usuario)
          <h2>Você está no painel</h2>
          <p class="acesso-sub">Conectado como <b>{{ $usuario->nome }}</b>.</p>
          <a class="botao botao-cheio botao-bloco" href="{{ route('painel.inicio') }}">Abrir o painel</a>
          <form method="POST" action="{{ route('logout') }}" class="acesso-sair">
            @csrf
            <button type="submit" class="link-botao">Sair</button>
          </form>
        @else
          <h2>Acesso ao painel</h2>
          <p class="acesso-sub">Para prefeituras clientes. Use o usuário e a senha recebidos da consultoria.</p>

          @if ($errors->any())
            <div class="acesso-erro" role="alert">{{ $errors->first() }}</div>
          @endif

          <form method="POST" action="{{ route('login.entrar') }}" novalidate>
            @csrf
            <label class="campo" for="usuario">
              <span>Nome de usuário</span>
              <input id="usuario" name="usuario" value="{{ old('usuario') }}" autocomplete="username" required
                     @if ($errors->has('usuario')) aria-invalid="true" @endif>
            </label>
            <label class="campo" for="senha">
              <span>Senha</span>
              <span class="campo-senha">
                <input id="senha" name="senha" type="password" autocomplete="current-password" required
                       @if ($errors->has('senha')) aria-invalid="true" @endif>
                <button type="button" class="ver-senha" data-ver-senha aria-controls="senha" aria-pressed="false">Mostrar</button>
              </span>
            </label>
            <label class="lembrar">
              <input type="checkbox" name="lembrar" value="1" @checked(old('lembrar'))>
              <span>Lembrar de mim</span>
            </label>
            <button class="botao botao-cheio botao-bloco" type="submit">Entrar</button>
          </form>
          <p class="acesso-ajuda">Esqueceu a senha? Fale com a consultoria pelo <a href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>.</p>
        @endif
      </div>
    </div>
  </section>

  {{-- Fotos de cidades históricas de Minas Gerais. --}}
  <ul class="faixa" aria-label="Fotos de cidades históricas de Minas Gerais">
    <li><img src="{{ $foto('faixa-ouro-preto-rosario.jpg') }}" width="900" height="750" loading="lazy" alt="Igreja barroca de Nossa Senhora do Rosário, em Ouro Preto (MG)"></li>
    <li><img src="{{ $foto('faixa-sao-joao-del-rei-rua.jpg') }}" width="900" height="750" loading="lazy" alt="Rua de pedra com casarios coloniais, em São João del-Rei (MG)"></li>
    <li><img src="{{ $foto('faixa-ouro-preto-casario.jpg') }}" width="900" height="750" loading="lazy" alt="Casario e igreja na encosta de uma cidade histórica de Minas Gerais"></li>
    <li><img src="{{ $foto('faixa-sao-joao-del-rei-igreja.jpg') }}" width="900" height="750" loading="lazy" alt="Fachada barroca de igreja em São João del-Rei (MG)"></li>
  </ul>

  {{-- [PARTE 3 - Gabriel] objetivos --}}

  {{-- [PARTE 4 - Lucas] como funciona e sobre --}}
</main>

{{-- [PARTE 4 - Lucas] contato e rodapé --}}

</body>
</html>
