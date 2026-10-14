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

    {{-- Só para Cliente: o JS abaixo esconde quando outro perfil é escolhido,
         e o controller descarta o valor nesse caso. --}}
    <div class="field" id="campo-contrato">
      <label class="label" for="contrato">Contrato</label>
      <input class="inp-box" id="contrato" name="contrato" maxlength="50"
             placeholder="Ex.: 225/2025"
             value="{{ old('contrato', $usuario->contrato) }}">
      <p class="muted" style="font-size:.85rem;margin-top:.3rem">
        Número do contrato com a prefeitura. Sai no cabeçalho do relatório.
      </p>
    </div>

    <div class="field">
      <label class="label" for="email">E-mail</label>
      <input class="inp-box" id="email" name="email" type="email"
             value="{{ old('email', $usuario->email) }}" required>
    </div>

    <div class="field">
      <label class="label" for="telefone">WhatsApp</label>
      <input class="inp-box" id="telefone" name="telefone" type="tel" inputmode="tel"
             placeholder="(31) 99999-0001"
             value="{{ old('telefone', $usuario->telefone ? $usuario->telefoneFormatado() : '') }}">
      <p class="muted" style="font-size:.85rem;margin-top:.3rem">
        @if ($editando && $usuario->autorizouAlertas())
          Autorizou receber alertas em {{ $usuario->alerta_consentimento_em->format('d/m/Y') }}.
          Se o número mudar, a pessoa precisa autorizar de novo.
        @else
          Número que recebe os alertas. Só recebe depois que a própria pessoa autorizar, em Minha conta.
        @endif
      </p>
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

@push('scripts')
<script>
(function () {
  var perfil = document.getElementById('perfil');
  var campo = document.getElementById('campo-contrato');
  var input = document.getElementById('contrato');
  if (!perfil || !campo) return;

  function atualizar() {
    var cliente = perfil.value === 'client';
    campo.hidden = !cliente;
    input.required = cliente;
  }

  perfil.addEventListener('change', atualizar);
  atualizar();
})();
</script>
@endpush
