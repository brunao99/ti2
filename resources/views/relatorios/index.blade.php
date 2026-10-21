@extends('layouts.painel')

@section('titulo', 'Gerar relatório')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
@endsection

@section('conteudo')
  {{-- Página do relatório: o caminho mais comum é a janela sobre a lista de
       publicações. Chega-se aqui pelo menu Mais, sem JavaScript ou quando a
       geração volta com erro. --}}
  <div class="envio-pagina">
    @include('relatorios._form', ['modal' => false])
  </div>
@endsection
