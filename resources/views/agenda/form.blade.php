@extends('layouts.painel')

@section('titulo', $evento->exists ? 'Editar evento' : 'Novo evento')
@section('cabecalho')
  <a class="voltar" href="{{ route('agenda.index') }}"><span>←</span> Agenda</a>
@endsection

@section('conteudo')
  {{-- Página de reserva: a mesma janela, fora da sobreposição. --}}
  <div class="envio-pagina">
    @include('agenda._form', ['modal' => false])
  </div>
@endsection
