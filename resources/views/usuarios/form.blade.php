@extends('layouts.painel')

@php($editando = $usuario->exists)

@section('titulo', $editando ? 'Editar usuário' : 'Novo usuário')

@section('cabecalho')
  <a class="voltar" href="{{ route('usuarios.index') }}"><span>←</span> Usuários</a>
  <h1 style="margin-top:.3rem">{{ $editando ? 'Editar usuário' : 'Novo usuário' }}</h1>
@endsection

@section('conteudo')

  <div class="m-only">
    <a class="voltar" href="{{ route('usuarios.index') }}"><span>←</span> Usuários</a>
    <h1 style="margin-top:.3rem">{{ $editando ? 'Editar usuário' : 'Novo usuário' }}</h1>
  </div>

  <form method="POST" style="max-width:640px"
        action="{{ $editando ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
    @csrf
    @if ($editando)
      @method('PUT')
    @endif

    <div class="field">
      <label class="label" for="nome">Nome completo</label>
      <input class="inp-box" id="nome" name="nome" value="{{ old('nome', $usuario->nome) }}" required>
    </div>

    <div class="field">
      <label class="label" for="usuario">Nome de usuário</label>
      <input class="inp-box" id="usuario" name="usuario"
             value="{{ old('usuario', $usuario->usuario) }}" required>
      <p class="muted" style="font-size:.85rem;margin-top:.3rem">É com ele que a pessoa faz login.</p>
    </div>

    <div class="field">
      <label class="label" for="email">E-mail</label>
      <input class="inp-box" id="email" name="email" type="email"
             value="{{ old('email', $usuario->email) }}" required>
    </div>

    <div class="field">
      <label class="label" for="password">Senha</label>
      <input class="inp-box" id="password" name="password" type="password"
             autocomplete="new-password" @required(! $editando)>
      @if ($editando)
        <p class="muted" style="font-size:.85rem;margin-top:.3rem">Deixe em branco para manter a senha atual.</p>
      @else
        <p class="muted" style="font-size:.85rem;margin-top:.3rem">Mínimo de 8 caracteres.</p>
      @endif
    </div>

    <div class="field">
      <label class="label" for="perfil">Perfil de acesso</label>
      <select class="inp-box" id="perfil" name="perfil" required>
        @foreach ($perfis as $valor)
          <option value="{{ $valor }}" @selected(old('perfil', $usuario->perfil) === $valor)>
            {{ ['master' => 'Master — acesso total',
                'adm'    => 'Administrador — publica, edita e cadastra usuários',
                'client' => 'Cliente — apenas visualiza publicações'][$valor] }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label class="check">
        <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $usuario->ativo ?? true))>
        <span>Conta ativa</span>
      </label>
      <p class="muted" style="font-size:.85rem;margin-top:.3rem">
        Conta inativa continua cadastrada, mas não consegue entrar.
      </p>
    </div>

    <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.6rem">
      <button class="btn" type="submit">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
      <a class="btn btn-ghost" href="{{ route('usuarios.index') }}">Cancelar</a>
    </div>
  </form>

@endsection
