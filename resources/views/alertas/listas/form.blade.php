@extends('layouts.painel')

@section('titulo', $lista->exists ? 'Editar lista' : 'Nova lista')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.listas') }}"><span>←</span> Listas</a>
  <h1 style="margin-top:.3rem">{{ $lista->exists ? 'Editar lista' : 'Nova lista' }}</h1>
@endsection

@section('conteudo')
  @php($selecionados = old('contatos', $lista->exists ? $lista->contatos->pluck('id')->all() : []))

  <form method="POST" action="{{ $lista->exists ? route('alertas.listas.atualizar', $lista) : route('alertas.listas.salvar') }}" class="form-grid">
    @csrf
    @if ($lista->exists) @method('PUT') @endif

    <div>
      <label class="label" for="nome">Nome</label>
      <input class="inp-box" id="nome" name="nome" value="{{ old('nome', $lista->nome) }}" required>
    </div>

    <div>
      <label class="label" for="descricao">Descrição</label>
      <textarea class="inp-box" id="descricao" name="descricao" rows="3">{{ old('descricao', $lista->descricao) }}</textarea>
    </div>

    <div>
      <p class="label">Contatos da lista</p>
      @forelse ($contatos as $contato)
        <label class="pill-check" style="display:flex;margin:.4rem 0">
          <input type="checkbox" name="contatos[]" value="{{ $contato->id }}" @checked(in_array($contato->id, $selecionados))>
          <span>{{ $contato->nome }} — {{ $contato->telefone }}
            {{ $contato->consentimento ? '(consentimento)' : '(sem consentimento)' }}
          </span>
        </label>
      @empty
        <p class="muted">Cadastre contatos antes de montar uma lista.</p>
      @endforelse
    </div>

    <div class="acts">
      <a class="btn btn-ghost" href="{{ route('alertas.listas') }}">Cancelar</a>
      <button class="btn" type="submit">Salvar lista</button>
    </div>
  </form>
@endsection
