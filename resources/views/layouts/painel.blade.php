<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo') — Painel Administrativo</title>
<link rel="stylesheet" href="{{ \App\Support\Asset::url('assets/style.css') }}">
</head>
<body>

@php($eu = auth()->user())

<div class="app">
  <aside class="side">
    <div class="brand">
      <span>
        <span class="brand-name">Algoritmos e Dados</span>
        <span class="brand-sub">consultoria</span>
      </span>
      @include('layouts.logo')
    </div>

    <nav>
      {{-- routeIs('painel.inicio') e não 'painel.*', senão a tela Mais
           acenderia o Início junto. --}}
      <a href="{{ route('painel.inicio') }}" @class(['on' => request()->routeIs('painel.inicio')])>Início</a>
      <a href="{{ route('postagens.index') }}" @class(['on' => request()->routeIs('postagens.*')])>Publicações</a>

      @if ($eu->podeGerenciarPostagens())
        {{-- Quem dispara alerta é o painel, não a prefeitura que recebe —
             por isso o Client não vê este item. --}}
        <a href="{{ route('alertas.index') }}" @class(['on' => request()->routeIs('alertas.*')])>Central de alertas</a>
      @endif

      @if ($eu->podeGerenciarUsuarios())
        <a href="{{ route('usuarios.index') }}" @class(['on' => request()->routeIs('usuarios.*')])>Usuários</a>
      @endif
    </nav>

    {{-- O nome leva a Minha conta, que concentra dados, alertas, tema e sair. --}}
    <a href="{{ route('conta.edit') }}" title="Minha conta"
       @class(['side-user', 'on' => request()->routeIs('conta.*')])>
      <span class="avatar">{{ $eu->iniciais() }}</span>
      <span class="quem">
        <b>{{ $eu->nome }}</b>
        <span>{{ $eu->rotuloPerfil() }}</span>
      </span>
      <span class="seta" aria-hidden="true">›</span>
    </a>
  </aside>

  <div class="main">
    <div class="mtop">
      <span>
        <span class="brand-name">Algoritmos e Dados</span>
        <span class="brand-sub">consultoria</span>
      </span>
      <a href="{{ route('conta.edit') }}" class="avatar" style="width:38px;height:38px"
         title="Minha conta" aria-label="Minha conta">{{ $eu->iniciais() }}</a>
    </div>

    <div class="topbar">
      <div>@yield('cabecalho')</div>
      <div class="topbar-actions">
        @yield('acoes')
      </div>
    </div>

    <main class="content">
      @if (session('ok'))
        <p class="aviso aviso-ok">{{ session('ok') }}</p>
      @endif

      @if ($errors->any())
        <div class="err show">
          @foreach ($errors->all() as $erro)
            <p>{{ $erro }}</p>
          @endforeach
        </div>
      @endif

      @yield('conteudo')
    </main>

    <nav class="botnav">
      <a href="{{ route('painel.inicio') }}" @class(['on' => request()->routeIs('painel.inicio')])>
        @include('layouts.icones.casa')
        Início
      </a>
      <a href="{{ route('postagens.index') }}" @class(['on' => request()->routeIs('postagens.*')])>
        @include('layouts.icones.documento')
        Publicações
      </a>
      {{-- A barra comporta poucos itens; o resto dos módulos vive em "Mais".
           Usuários, Alertas e Minha conta ficam lá dentro, por isso acendem este item. --}}
      <a href="{{ route('painel.mais') }}"
         @class(['on' => request()->routeIs('painel.mais') || request()->routeIs('usuarios.*') || request()->routeIs('alertas.*') || request()->routeIs('conta.*')])>
        @include('layouts.icones.mais')
        Mais
      </a>
    </nav>
  </div>
</div>

<script src="{{ \App\Support\Asset::url('assets/app.js') }}"></script>
@stack('scripts')
</body>
</html>
