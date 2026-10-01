@extends('layouts.painel')

@section('titulo', $contato->exists ? 'Editar contato' : 'Novo contato')

@section('cabecalho')
  <a class="voltar" href="{{ route('alertas.contatos') }}"><span>←</span> Contatos</a>
  <h1 style="margin-top:.3rem">{{ $contato->exists ? 'Editar contato' : 'Novo contato' }}</h1>
@endsection

@section('conteudo')
  <form method="POST" action="{{ $contato->exists ? route('alertas.contatos.atualizar', $contato) : route('alertas.contatos.salvar') }}" class="form-grid">
    @csrf
    @if ($contato->exists) @method('PUT') @endif

    <div>
      <label class="label" for="nome">Nome</label>
      <input class="inp-box" id="nome" name="nome" value="{{ old('nome', $contato->nome) }}" required>
    </div>

    <div>
      <label class="label" for="telefone">Telefone / WhatsApp</label>
      <input class="inp-box" id="telefone" name="telefone" value="{{ old('telefone', $contato->telefone) }}" placeholder="+55 31 99999-9999" required>
    </div>

    <label class="pill-check" style="display:flex">
      <input type="checkbox" name="consentimento" value="1" @checked(old('consentimento', $contato->consentimento))>
      <span>Contato possui consentimento para receber alertas</span>
    </label>

    <div class="acts">
      <a class="btn btn-ghost" href="{{ route('alertas.contatos') }}">Cancelar</a>
      <button class="btn" type="submit">Salvar contato</button>
    </div>
  </form>
@endsection
