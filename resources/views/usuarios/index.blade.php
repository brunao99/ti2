@extends('layouts.painel')

@section('titulo', 'Usuários')
@section('cabecalho')
  <h1>Usuários</h1>
@endsection

@section('acoes')
  <a class="btn" href="{{ route('usuarios.create') }}">Novo usuário</a>
@endsection

@section('conteudo')

  <div class="m-only m-cabecalho">
    <h1>Usuários</h1>
    <a class="btn btn-sm" href="{{ route('usuarios.create') }}">+ Novo usuário</a>
  </div>

  @if (auth()->user()->ehAdm())
    <p class="note">Como administrador, você não visualiza nem edita contas Master.</p>
  @endif

  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th style="width:160px">Usuário</th>
        <th>E-mail</th>
        <th style="width:150px">Perfil</th>
        <th style="width:130px">Alertas</th>
        <th style="width:110px">Situação</th>
        <th style="width:190px">Ações</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($usuarios as $usuario)
        <tr>
          <td class="t-title">{{ $usuario->nome }}</td>
          <td class="mono">{{ $usuario->usuario }}</td>
          <td class="muted">{{ $usuario->email }}</td>
          <td><span class="tag">{{ $usuario->rotuloPerfil() }}</span></td>
          <td class="muted" title="{{ $usuario->telefone ? $usuario->telefoneFormatado() : '' }}">
            @if ($usuario->autorizouAlertas())
              Recebe
            @elseif ($usuario->telefone)
              Não autorizou
            @else
              Sem telefone
            @endif
          </td>
          <td>
            <span class="badge {{ $usuario->ativo ? 'badge-ok' : 'badge-draft' }}">
              {{ $usuario->ativo ? 'Ativo' : 'Inativo' }}
            </span>
          </td>
          <td>
            <div class="acts">
              @if (auth()->user()->podeEditarUsuario($usuario))
                <a class="btn btn-ghost btn-sm" href="{{ route('usuarios.edit', $usuario) }}">Editar</a>

                @unless ($usuario->is(auth()->user()))
                  <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}"
                        onsubmit="return confirm('Excluir {{ $usuario->nome }}?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm" type="submit">Excluir</button>
                  </form>
                @endunless
              @else
                <span class="muted">—</span>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="muted">Nenhum usuário cadastrado.</td></tr>
      @endforelse
    </tbody>
  </table>

@endsection
