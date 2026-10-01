<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo') — Painel Administrativo</title>
<link rel="stylesheet" href="{{ asset('assets/style.css') }}">
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

    <div class="side-user">
      <span class="avatar">{{ $eu->iniciais() }}</span>
      <span class="quem">
        <b>{{ $eu->nome }}</b>
        <span>{{ $eu->rotuloPerfil() }}</span>
      </span>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-sair" title="Sair" aria-label="Sair do painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
      </form>
    </div>
  </aside>

  <div class="main">
    <div class="mtop">
      <span>
        <span class="brand-name">Algoritmos e Dados</span>
        <span class="brand-sub">consultoria</span>
      </span>
      <button class="toggle" title="Alternar tema"><span class="s">☀</span><span class="m">☾</span></button>
      <span class="avatar" style="width:38px;height:38px">{{ $eu->iniciais() }}</span>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-sair" title="Sair" aria-label="Sair do painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
      </form>
    </div>

    <div class="topbar">
      <div>@yield('cabecalho')</div>
      <div class="topbar-actions">
        <button class="toggle" title="Alternar tema"><span class="s">☀</span><span class="m">☾</span></button>
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
      <a href="{{ route('painel.inicio') }}" @class(['on' => request()->routeIs('painel.*')])>
        @include('layouts.icones.casa')
        Início
      </a>
      <a href="{{ route('postagens.index') }}" @class(['on' => request()->routeIs('postagens.*')])>
        @include('layouts.icones.documento')
        Publicações
      </a>
      {{-- A barra comporta poucos itens; o resto dos módulos vive em "Mais".
           Usuários e Alertas ficam lá dentro, por isso acendem este item. --}}
      <a href="{{ route('painel.mais') }}"
         @class(['on' => request()->routeIs('painel.mais') || request()->routeIs('usuarios.*') || request()->routeIs('alertas.*')])>
        @include('layouts.icones.mais')
        Mais
      </a>
    </nav>
  </div>
</div>

<script src="{{ asset('assets/app.js') }}"></script>
@stack('scripts')
</body>
</html>
