@php
    use App\Support\Asset;

    // Contato da empresa, o mesmo do site atual.
    $telefone = '(31) 98429-4085';
    $email = 'cdalgoritmosedados@gmail.com';
    $whatsapp = 'https://wa.me/5531984294085?text=' . rawurlencode('Olá! Gostaria de conhecer a consultoria da Algoritmos e Dados para o meu município.');
    $usuario = auth()->user();
    $hoje = now('America/Sao_Paulo')->format('d/m/Y');
    $foto = fn (string $arquivo) => asset('assets/site/' . $arquivo);

    // Os objetivos e as imagens da página "Objetivos" do site atual.
    // modo: "foto" preenche o quadro; "marca" mostra a imagem inteira sobre fundo claro.
    $objetivos = [
        ['Identificação dos programas em execução nos ministérios e seleção daqueles sintonizados com o perfil e as demandas do município.',
            [['ministerios.jpg', 'Esplanada dos Ministérios, em Brasília', 'foto']]],
        ['Monitoramento e seleção dos editais publicados nos ministérios.',
            [['programas.jpg', 'Cartaz de edital publicado por ministério', 'marca']]],
        ['Identificação de fontes de financiamento para o município junto à CAIXA, ao BNDES e ao Banco do Brasil.',
            [['caixa.jpg', 'Marca da CAIXA', 'marca'], ['bndes.jpg', 'Marca do BNDES', 'marca'], ['bancobrasil.jpg', 'Marca do Banco do Brasil', 'marca']]],
        ['Monitoramento de programas e ações da administração direta e indireta do Governo do Estado de Minas Gerais.',
            [['minas-gerais.jpg', 'Cidade Administrativa do Governo de Minas Gerais', 'foto']]],
        ['Pesquisas que identificam boas práticas governamentais e projetos inovadores nas administrações municipais do país.',
            [['noticia.jpg', 'Notícia sobre projeto inovador de um município', 'marca']]],
        ['Identificação de parcerias com a iniciativa privada e o terceiro setor para eventos e projetos sociais.',
            [['lei-incentivo.jpg', 'Lei de Incentivo à Cultura', 'foto'], ['esporte.jpg', 'Projeto esportivo apoiado por empresa', 'foto']]],
        ['Monitoramento das mídias digitais de gestores municipais bem avaliados, identificando novos conteúdos e estilos de postagem.',
            [['midias.jpg', 'Publicações de gestor municipal em rede social', 'foto']]],
        ['Identificação de programas de entidades que premiam gestores públicos por iniciativas inovadoras.',
            [['premio.jpg', 'Prêmio Mineiro de Boas Práticas na Gestão Municipal, da AMM', 'marca'], ['sebrae.jpg', 'Prêmio Sebrae Prefeito Empreendedor', 'marca']]],
        ['Monitoramento dos índices municipais e indicadores da gestão pública (IDH, IDEB, FGV), comparados aos municípios do entorno e às médias do estado e do país.',
            [['grafico.jpg', 'Gráfico comparativo de indicadores', 'marca']]],
        ['Acompanhamento da agenda de seminários e eventos nacionais sobre eficiência e inovação na gestão pública.',
            [['agenda.jpg', 'Cartaz de seminário sobre gestão pública', 'marca']]],
        ['Monitoramento das pautas municipalistas, notas técnicas e boletins da Confederação Nacional de Municípios e da Associação Mineira de Municípios.',
            [['cnm.jpg', 'Marca da Confederação Nacional de Municípios', 'marca'], ['amm.jpg', 'Marca da Associação Mineira de Municípios', 'marca']]],
    ];
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
<meta property="og:image" content="{{ $foto('sabara-rua.jpg') }}">
@include('layouts.favicon')
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
    <img class="heroi-fundo" src="{{ $foto('sabara-rua.jpg') }}" width="820" height="400" alt="" aria-hidden="true" fetchpriority="high">

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

  {{-- Fotos das cidades, como no banner do site atual. --}}
  <ul class="faixa" aria-label="Fotos de Sabará (MG)">
    <li><img src="{{ $foto('sabara-ruinas.jpg') }}" width="320" height="400" alt="Ruínas de igreja de pedra com escadaria e portão azul, em Sabará"></li>
    <li><img src="{{ $foto('sabara-casario.jpg') }}" width="320" height="400" alt="Casarão colonial com janelas azuis e lampião, em Sabará"></li>
    <li><img src="{{ $foto('sabara-praca.jpg') }}" width="320" height="400" alt="Praça com coreto e árvores, em Sabará"></li>
    <li><img src="{{ $foto('sabara-igreja.jpg') }}" width="320" height="400" alt="Fachada barroca de igreja ao entardecer, em Sabará"></li>
  </ul>

  {{-- ========================================================= objetivos --}}
  <section class="secao secao-nevoa" id="objetivos" aria-labelledby="objetivos-titulo">
    <div class="wrap">
      <div class="cabeca">
        <h2 id="objetivos-titulo">Objetivos</h2>
        <p class="lide">
          Realizar pesquisas e monitoramentos permanentes das pautas de interesse do município, reunindo em um
          único ambiente digital informações úteis e práticas, capazes de contribuir com o êxito da gestão pública
          e com a prospecção do chefe do Poder Executivo municipal.
        </p>
      </div>

      <ul class="objetivos">
        @foreach ($objetivos as [$texto, $imagens])
          <li class="objetivo">
            <div @class(['objetivo-img', 'varias' => count($imagens) > 1, 'marcas' => $imagens[0][2] === 'marca'])>
              @foreach ($imagens as [$arquivo, $alt, $modo])
                <img class="{{ $modo }}" src="{{ $foto('objetivos/' . $arquivo) }}" alt="{{ $alt }}" loading="lazy">
              @endforeach
            </div>
            <p>{{ $texto }}</p>
          </li>
        @endforeach
        <li class="objetivo objetivo-chamada">
          <h3>Quer isso para o seu município?</h3>
          <p>Conte o que a prefeitura precisa e veja como o monitoramento funciona na prática.</p>
          <a class="botao botao-claro" href="{{ $whatsapp }}" target="_blank" rel="noopener">
            @include('site._icone', ['nome' => 'whatsapp']) Falar no WhatsApp
          </a>
        </li>
      </ul>
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
            <p>Todo esse conteúdo fica sempre atualizado em um painel de controle. O prefeito recebe login e senha de acesso.</p>
          </li>
          <li>
            <h3>Alerta no WhatsApp</h3>
            <p>Cada novidade chega como mensagem de alerta no WhatsApp do prefeito.</p>
          </li>
          <li>
            <h3>Reuniões periódicas</h3>
            <p>Para aprimorar a busca e o envio das informações e avaliar a efetividade do uso dos dados coletados.</p>
          </li>
        </ol>

        <div class="demonstracao">
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
            </div>
          </div>

          {{-- O alerta usa o texto do modelo de mensagem do painel. --}}
          <div class="alerta" aria-label="Exemplo de alerta enviado pelo WhatsApp">
            <div class="alerta-topo">
              <span class="alerta-avatar" aria-hidden="true">@include('layouts.logo')</span>
              <span><b>Algoritmos e Dados</b><small>Exemplo de alerta</small></span>
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
    </div>
  </section>

  {{-- ============================================================== sobre --}}
  <section class="secao" id="sobre" aria-labelledby="sobre-titulo">
    <div class="wrap sobre-grade">
      <div>
        <h2 id="sobre-titulo">Sobre a Algoritmos e Dados</h2>
        <p class="lide">
          Somos uma consultoria à gestão pública e empresarial. Pesquisamos e monitoramos as pautas de interesse
          do município e reunimos, em um único ambiente digital, informações úteis e práticas para o êxito da
          gestão e para a prospecção do chefe do Poder Executivo municipal.
        </p>
        <p class="frase">É preciso ficar antenado para não deixar as oportunidades passarem.</p>
      </div>
      <figure class="sobre-foto">
        <img src="{{ $foto('sabara-coreto.jpg') }}" width="750" height="400" loading="lazy" alt="Praça com coreto, árvores e casarios, em Sabará (MG)">
      </figure>
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
        <a href="#objetivos">Objetivos</a>
        <a href="#como-funciona">Como funciona</a>
        <a href="#sobre">Sobre</a>
        <a href="{{ $usuario ? route('painel.inicio') : '#acesso' }}">{{ $usuario ? 'Abrir o painel' : 'Acessar o painel' }}</a>
      </nav>
      <p class="rodape-legal">© {{ date('Y') }} Algoritmos e Dados · Consultoria à Gestão Pública e Empresarial</p>
    </div>
  </div>
</footer>

</body>
</html>
