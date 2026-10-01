@extends('layouts.painel')

@section('titulo', $modelo->exists ? 'Editar modelo' : 'Novo modelo')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.modelos') }}"><span>←</span> Modelos</a>
  <h1 style="margin-top:.3rem">{{ $modelo->exists ? 'Editar modelo' : 'Novo modelo' }}</h1>
@endsection

@section('conteudo')
  <form method="POST" action="{{ $modelo->exists ? route('alertas.modelos.atualizar', $modelo) : route('alertas.modelos.salvar') }}" class="form-grid">
    @csrf
    @if ($modelo->exists) @method('PUT') @endif

    <div>
      <label class="label" for="nome">Nome</label>
      <input class="inp-box" id="nome" name="nome" value="{{ old('nome', $modelo->nome) }}" required>
    </div>

    <div>
      <label class="label" for="mensagem">Mensagem</label>
      <textarea class="inp-box" id="mensagem" name="mensagem" rows="8" required>{{ old('mensagem', $modelo->mensagem) }}</textarea>
      <p class="muted">Use <code>{{ '{{titulo}}' }}</code>, <code>{{ '{{tipo}}' }}</code> e <code>{{ '{{data_publicacao}}' }}</code> para preencher os dados da publicação.</p>
    </div>

    <div class="acts">
      <a class="btn btn-ghost" href="{{ route('alertas.modelos') }}">Cancelar</a>
      <button class="btn" type="submit">Salvar modelo</button>
    </div>
  </form>
@endsection
