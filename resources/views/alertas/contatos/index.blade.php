@extends('layouts.painel')

@section('titulo', 'Contatos')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.index') }}"><span>←</span> Alertas</a>
  <h1 style="margin-top:.3rem">Contatos</h1>
@endsection

@section('acoes')
  <a class="btn" href="{{ route('alertas.contatos.novo') }}">Novo contato</a>
@endsection

@section('conteudo')
  <table>
    <thead>
      <tr><th>Nome</th><th>Telefone</th><th>Consentimento</th><th>Listas</th><th>Ações</th></tr>
    </thead>
    <tbody>
      @forelse ($contatos as $contato)
        <tr>
          <td>{{ $contato->nome }}</td>
          <td>{{ $contato->telefone }}</td>
          <td>
            <span class="badge {{ $contato->consentimento ? 'badge-ok' : 'badge-draft' }}">
              {{ $contato->consentimento ? 'Sim' : 'Não' }}
            </span>
          </td>
          <td>{{ $contato->listas_count }}</td>
          <td>
            <div class="acts">
              <a class="btn btn-ghost btn-sm" href="{{ route('alertas.contatos.editar', $contato) }}">Editar</a>
              <form method="POST" action="{{ route('alertas.contatos.remover', $contato) }}" onsubmit="return confirm('Remover este contato?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm" type="submit">Remover</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Nenhum contato cadastrado.</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
