@extends('layouts.painel')

@section('titulo', 'Enviar alerta')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('alertas.index') }}">Histórico</a>
@endsection

@section('conteudo')
  {{-- Página de reserva: o caminho normal é a janela sobre a lista de
       publicações. Chega-se aqui sem JavaScript ou quando o disparo volta
       com erro. --}}
  <div class="envio-pagina">
    @include('alertas._envio', ['modal' => false])
  </div>
@endsection
