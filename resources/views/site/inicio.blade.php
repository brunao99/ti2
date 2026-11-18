@php
    use App\Support\Asset;

    // Contato da empresa, o mesmo do site atual.
    $telefone = '(31) 98429-4085';
    $email = 'cdalgoritmosedados@gmail.com';
    $whatsapp = 'https://wa.me/5531984294085?text=' . rawurlencode('Olá! Gostaria de conhecer a consultoria da Algoritmos e Dados para o meu município.');

    // Quem já entrou no painel volta direto para ele.
    $painel = auth()->check() ? route('painel.inicio') : route('login');
    $rotuloPainel = auth()->check() ? 'Abrir o painel' : 'Acessar o painel';

    $hoje = now('America/Sao_Paulo')->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Algoritmos e Dados — Consultoria em gestão pública para prefeituras</title>
<meta name="description" content="Consultoria que monitora programas dos ministérios e do Governo de Minas, editais e financiamentos e entrega ao prefeito o que serve ao município, em um painel com alertas no WhatsApp.">
<meta name="theme-color" content="#133049">
<meta property="og:type" content="website">
<meta property="og:title" content="Algoritmos e Dados — Consultoria em gestão pública para prefeituras">
<meta property="og:description" content="Programas, editais e financiamentos para o seu município, em um painel com alertas no WhatsApp.">
<meta property="og:image" content="{{ asset('assets/site/sabara-rua.jpg') }}">
@include('layouts.favicon')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500&display=swap">
<link rel="stylesheet" href="{{ Asset::url('assets/site.css') }}">
<script src="{{ Asset::url('assets/site.js') }}" defer></script>
</head>
<body class="site">

<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<header class="topo" data-topo>
  <div class="wrap topo-in">
    <a class="marca" href="{{ url('/') }}" aria-label="Algoritmos e Dados, início">
      <span class="marca-nome">Algoritmos e Dados</span>
      @include('layouts.logo')
    </a>

    <nav class="menu" id="menu" aria-label="Seções da página">
      <a href="#monitoramos">O que monitoramos</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#sobre">Sobre</a>
      <a href="#contato">Contato</a>
      <a class="menu-painel" href="{{ $painel }}">{{ $rotuloPainel }}</a>
    </nav>

    <a class="botao botao-linha topo-painel" href="{{ $painel }}">{{ $rotuloPainel }}</a>

    <button class="menu-botao" type="button" aria-label="Menu" aria-controls="menu" aria-expanded="false" data-menu-botao>
      <span class="menu-botao-icone" aria-hidden="true"><i></i><i></i></span>
      <span class="menu-botao-texto">Menu</span>
    </button>
  </div>
</header>

<main id="conteudo">

  {{-- ============================================================ início --}}
  <section class="secao heroi" id="inicio" aria-labelledby="heroi-titulo">
    <div class="wrap heroi-grade">
      <div class="heroi-texto">
        <h1 id="heroi-titulo" class="entra">Consultoria em gestão pública para prefeituras</h1>
        <p class="heroi-lide entra">
          Acompanhamos programas dos ministérios e do Governo de Minas, editais e fontes de financiamento na CAIXA,
          no BNDES e no Banco do Brasil. O que serve ao seu município chega ao prefeito em um painel próprio,
          com alerta no WhatsApp.
        </p>
        <div class="acoes entra">
          <a class="botao botao-cheio" href="{{ $whatsapp }}" target="_blank" rel="noopener">
            @include('site._icone', ['nome' => 'whatsapp']) Falar no WhatsApp
          </a>
          <a class="botao botao-linha" href="{{ $painel }}">{{ $rotuloPainel }}</a>
        </div>
      </div>

      <div class="heroi-imagem">
        <figure class="quadro quadro-foto">
          <img src="{{ asset('assets/site/sabara-rua.jpg') }}" width="820" height="400"
               alt="Rua de pedra com casarios coloniais e igrejas ao fundo, em Sabará (MG)" fetchpriority="high">
        </figure>

        {{-- O alerta usa o texto do modelo de mensagem do painel. --}}
        <div class="alerta" data-alerta aria-label="Exemplo de alerta enviado pelo WhatsApp">
          <div class="alerta-topo">
            <span class="alerta-avatar" aria-hidden="true">@include('layouts.logo')</span>
            <span>
              <b>Algoritmos e Dados</b>
              <small>Exemplo de alerta</small>
            </span>
          </div>
          <p class="alerta-msg">
            Olá! Foi publicada uma nova informação no Portal Algoritmos e Dados:
            <b>Edital de chamamento público 012/2026</b> (Ministérios e autarquias — Editais),
            publicada em {{ $hoje }}.
            <span class="alerta-hora">09:12 <span class="vistos" aria-label="lida">@include('site._icone', ['nome' => 'vistos'])</span></span>
          </p>
        </div>
      </div>
    </div>
  </section>

  {{-- ======================================================= monitoramos --}}
  <section class="secao secao-nevoa" id="monitoramos" aria-labelledby="monitoramos-titulo">
    <div class="wrap">
      <div class="cabeca">
        <h2 id="monitoramos-titulo">O que monitoramos para o seu município</h2>
        <p class="lide">Onze frentes de pesquisa, acompanhadas de forma permanente e selecionadas pelo perfil e pelas demandas de cada prefeitura.</p>
      </div>

      <div class="colunas">
        <div>
          <h3>Recursos e oportunidades</h3>
          <ul class="lista">
            <li>Programas em execução nos ministérios que combinam com o perfil do município</li>
            <li>Editais publicados nos ministérios</li>
            <li>Fontes de financiamento na CAIXA, no BNDES e no Banco do Brasil</li>
            <li>Programas e ações do Governo de Minas Gerais, na administração direta e indireta</li>
            <li>Parcerias com a iniciativa privada e o terceiro setor para eventos e projetos sociais</li>
            <li>Prêmios de entidades que reconhecem gestores por iniciativas inovadoras</li>
          </ul>
        </div>
        <div>
          <h3>Referências para a gestão</h3>
          <ul class="lista">
            <li>Boas práticas e projetos inovadores de prefeituras de todo o país</li>
            <li>IDH, IDEB e índices da FGV comparados aos municípios vizinhos e às médias do estado e do país</li>
            <li>Pautas municipalistas, notas técnicas e boletins da CNM e da AMM</li>
            <li>Agenda de seminários e eventos sobre eficiência e inovação na gestão pública</li>
            <li>Mídias digitais de gestores bem avaliados: novos conteúdos e estilos de postagem</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  {{-- ===================================================== como funciona --}}
  <section class="secao secao-marinho" id="como-funciona" aria-labelledby="como-titulo">
    <div class="wrap">
      <div class="cabeca">
        <h2 id="como-titulo">Como a informação chega até a prefeitura</h2>
      </div>

      <div class="divisao">
        <ol class="passos">
          <li>
            <h3>Pesquisa e seleção</h3>
            <p>A equipe acompanha ministérios, governo do estado, bancos públicos e entidades municipalistas e separa o que combina com o município.</p>
          </li>
          <li>
            <h3>Painel do prefeito</h3>
            <p>Tudo fica reunido e atualizado em um painel, organizado por tipo de publicação. O prefeito entra com login e senha próprios.</p>
          </li>
          <li>
            <h3>Alerta e reuniões</h3>
            <p>Cada novidade chega como alerta no WhatsApp. Em reuniões periódicas, avaliamos a busca e o uso das informações.</p>
          </li>
        </ol>

        {{-- O painel como ele é: listagem de um tipo, com a faixa na cor do tipo. --}}
        <div class="janela" aria-label="Exemplo da listagem de publicações do painel">
          <div class="janela-barra">
            <span class="janela-pontos" aria-hidden="true"><i></i><i></i><i></i></span>
            <span>Painel · exemplo</span>
          </div>
          <div class="janela-corpo">
            <p class="janela-tipo">Ministérios e autarquias — Editais</p>
            <p class="janela-mes">Novembro de 2026</p>
            <ul class="janela-lista">
              <li>
                <span class="j-titulo">Edital de chamamento público 012/2026</span>
                <span class="j-data">{{ $hoje }}</span>
                <span class="j-selo j-enviado">Alerta enviado</span>
              </li>
              <li>
                <span class="j-titulo">Seleção de propostas para equipamentos de saúde</span>
                <span class="j-data">12/11/2026</span>
                <span class="j-selo j-enviado">Alerta enviado</span>
              </li>
              <li>
                <span class="j-titulo">Chamada pública para projetos de esporte e lazer</span>
                <span class="j-data">05/11/2026</span>
                <span class="j-selo">Publicado</span>
              </li>
            </ul>
            <p class="janela-mes">Outubro de 2026</p>
            <ul class="janela-lista">
              <li>
                <span class="j-titulo">Programa de apoio à pavimentação urbana</span>
                <span class="j-data">28/10/2026</span>
                <span class="j-selo">Publicado</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- ============================================================== sobre --}}
  <section class="secao" id="sobre" aria-labelledby="sobre-titulo">
    <div class="wrap">
      <div class="sobre-grade">
        <div>
          <h2 id="sobre-titulo">Sobre a Algoritmos e Dados</h2>
          <p class="lide">
            Somos uma consultoria à gestão pública e empresarial. Pesquisamos e monitoramos as pautas de interesse
            do município e reunimos, em um único ambiente digital, informações úteis e práticas para o êxito da
            gestão e para a prospecção do chefe do Poder Executivo municipal.
          </p>
        </div>
        <p class="frase">É preciso ficar antenado para não deixar as oportunidades passarem.</p>
      </div>

      <ul class="mural" aria-label="Fotos de Sabará (MG)">
        <li><img src="{{ asset('assets/site/sabara-ruinas.jpg') }}" width="320" height="400" alt="Ruínas de igreja de pedra com escadaria e portão azul, em Sabará"></li>
        <li><img src="{{ asset('assets/site/sabara-casario.jpg') }}" width="320" height="400" alt="Casarão colonial com janelas azuis e lampião, em Sabará"></li>
        <li><img src="{{ asset('assets/site/sabara-praca.jpg') }}" width="320" height="400" alt="Praça com coreto e árvores, em Sabará"></li>
        <li><img src="{{ asset('assets/site/sabara-igreja.jpg') }}" width="320" height="400" alt="Fachada barroca de igreja ao entardecer, em Sabará"></li>
      </ul>
    </div>
  </section>
</main>

{{-- ============================================== contato e rodapé --}}
<footer class="secao fechamento" id="contato" aria-labelledby="contato-titulo">
  <div class="wrap fechamento-in">
    <div class="contato">
      <h2 id="contato-titulo">Fale com a consultoria</h2>
      <p class="lide">Conte o que o seu município precisa. A conversa começa pelo WhatsApp ou por e-mail.</p>
      <div class="acoes">
        <a class="botao botao-claro" href="{{ $whatsapp }}" target="_blank" rel="noopener">
          @include('site._icone', ['nome' => 'whatsapp']) Falar no WhatsApp
        </a>
        <a class="botao botao-linha-clara" href="mailto:{{ $email }}">Enviar e-mail</a>
      </div>
      <dl class="contato-dados">
        <div><dt>WhatsApp e telefone</dt><dd><a href="tel:+5531984294085">{{ $telefone }}</a></dd></div>
        <div><dt>E-mail</dt><dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd></div>
      </dl>
    </div>

    <div class="rodape">
      <a class="marca marca-clara" href="#inicio" aria-label="Algoritmos e Dados, voltar ao início">
        <span class="marca-nome">Algoritmos e Dados</span>
        @include('layouts.logo')
      </a>
      <nav class="rodape-links" aria-label="Rodapé">
        <a href="#monitoramos">O que monitoramos</a>
        <a href="#como-funciona">Como funciona</a>
        <a href="#sobre">Sobre</a>
        <a href="{{ $painel }}">{{ $rotuloPainel }}</a>
      </nav>
      <p class="rodape-legal">© {{ date('Y') }} Algoritmos e Dados · Consultoria à Gestão Pública e Empresarial</p>
    </div>
  </div>
</footer>

</body>
</html>
