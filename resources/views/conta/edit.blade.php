@extends('layouts.painel')

@section('titulo', 'Minha conta')
@section('cabecalho')
  <h1>Minha conta</h1>
@endsection

@section('conteudo')

  <div class="m-only m-cabecalho">
    <h1>Minha conta</h1>
  </div>

  <form method="POST" action="{{ route('conta.update') }}" style="max-width:640px">
    @csrf
    @method('PUT')

    <h2>Alertas no WhatsApp</h2>
    <p class="muted" style="margin-bottom:1.4rem">
      Quando sair uma publicação nova no portal, enviamos um aviso para o seu WhatsApp.
      Você só recebe se autorizar aqui, e pode parar quando quiser.
    </p>

    <div class="field">
      <label class="label" for="telefone">Número de WhatsApp</label>
      <input class="inp-box" id="telefone" name="telefone" type="tel" inputmode="tel"
             placeholder="(31) 99999-0001"
             value="{{ old('telefone', $eu->telefone ? $eu->telefoneFormatado() : '') }}">
    </div>

    <div class="field">
      <label class="check">
        <input type="checkbox" name="autorizo" value="1" @checked(old('autorizo', $eu->autorizouAlertas()))>
        <span>Autorizo receber alertas de novas publicações neste número</span>
      </label>
      @if ($eu->autorizouAlertas())
        <p class="muted" style="font-size:.85rem;margin-top:.3rem">
          Autorizado em {{ $eu->alerta_consentimento_em->format('d/m/Y \à\s H:i') }}.
          Desmarque e salve para parar de receber.
        </p>
      @endif
    </div>

    <div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.6rem">
      <button class="btn" type="submit">Salvar</button>
    </div>
  </form>

@endsection
