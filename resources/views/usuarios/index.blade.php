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

  {{-- Em telas médias as oito colunas não cabem: a tabela rola sozinha,
       sem alargar a página inteira. --}}
  <div class="tabela-rolagem">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Usuário</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Perfil</th>
          <th>Alertas</th>
          <th>Situação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($usuarios as $usuario)
          <tr>
            <td class="t-title">{{ $usuario->nome }}</td>
            <td class="mono">{{ $usuario->usuario }}</td>
            {{-- Quebra só antes do @, nunca no meio do endereço. --}}
            <td class="muted">{!! str_replace('@', '<wbr>@', e($usuario->email)) !!}</td>
            <td class="muted" style="white-space:nowrap">{{ $usuario->telefone ? $usuario->telefoneFormatado(comDdi: false) : '—' }}</td>
            <td><span class="tag">{{ $usuario->rotuloPerfil() }}</span></td>
            <td class="muted">
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
          <tr><td colspan="8" class="muted">Nenhum usuário cadastrado.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

@endsection
