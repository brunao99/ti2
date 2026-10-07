<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar — Painel Administrativo</title>
<link rel="stylesheet" href="{{ \App\Support\Asset::url('assets/style.css') }}">
</head>
<body>

<header class="site-head">
  <a class="brand" href="{{ route('login') }}">
    <span>
      <span class="brand-name">Algoritmos e Dados</span>
      <span class="brand-sub">consultoria</span>
    </span>
    @include('layouts.logo')
  </a>
  <nav class="site-nav">
    <a href="{{ route('login') }}" class="on">Home</a>
  </nav>
</header>

<section class="hero">
  <div class="hero-in">
    <h1>Transparência e acesso à informação para todo cidadão</h1>
    <div class="rule"></div>
  </div>
  <div class="hero-curve"></div>
</section>

<main class="wrap">
  <div class="login-card">
    <p class="label">Acesso restrito</p>
    <h2 style="margin:.3rem 0 2rem">Painel Administrativo</h2>

    @if ($errors->any())
      <div class="err show">
        @foreach ($errors->all() as $erro)
          <p>{{ $erro }}</p>
        @endforeach
      </div>
    @endif

    <form method="POST" action="{{ route('login.entrar') }}">
      @csrf

      <div class="field">
        <label class="label" for="usuario">Nome de usuário</label>
        <input class="inp" id="usuario" name="usuario" value="{{ old('usuario') }}"
               placeholder="seu.usuario" autocomplete="username" required autofocus>
      </div>

      <div class="field">
        <label class="label" for="senha">Senha</label>
        <input class="inp" id="senha" name="senha" type="password"
               placeholder="••••••••" autocomplete="current-password" required>
      </div>

      <div class="row-between" style="margin-bottom:2rem">
        <label class="check">
          <input type="checkbox" name="lembrar" value="1" @checked(old('lembrar'))>
          <span>Lembrar de mim</span>
        </label>
        <a href="#" onclick="return false">Esqueci minha senha</a>
      </div>

      <button class="btn btn-block" type="submit">Entrar</button>
    </form>
  </div>
</main>

<footer class="site-foot">
  <div class="foot-in">
    <div>
      <h2>Contato</h2>
      <div class="rule"></div>
      <p>cdalgoritmosedados@gmail.com</p>
      <p>(31) 98429-4085</p>
    </div>
    <div>
      <p class="label" style="color:rgba(255,255,255,.85)">Fale com a gente</p>
      <div class="socials"><i>W</i><i>@</i></div>
      <p style="margin-top:.8rem">@algoritmosedados</p>
    </div>
  </div>
</footer>

<script src="{{ \App\Support\Asset::url('assets/app.js') }}"></script>
</body>
</html>
