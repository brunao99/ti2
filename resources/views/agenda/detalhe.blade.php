@extends('layouts.painel')

@section('titulo', $evento->nome())
@section('cabecalho')
  <a class="voltar" href="{{ route('agenda.index', array_filter(['data' => $o->dia->format('Y-m-d'), 'visao' => $visao])) }}"><span>←</span> Agenda</a>
@endsection

@section('conteudo')
  <div class="envio-pagina">
    @include('agenda._detalhe', ['pagina' => true])
  </div>
@endsection
