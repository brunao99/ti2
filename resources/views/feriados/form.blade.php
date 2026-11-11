@extends('layouts.painel')

@section('titulo', $feriado->exists ? 'Editar feriado' : 'Novo feriado')
@section('cabecalho')
  <a class="voltar" href="{{ route('feriados.index', array_filter(['ano' => $ano])) }}"><span>←</span> Calendário de feriados</a>
@endsection

@section('conteudo')
  <div class="envio-pagina">
    @include('feriados._form', ['modal' => false])
  </div>
@endsection
