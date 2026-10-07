@extends('layouts.painel')

@section('titulo', 'Mais')
@section('cabecalho')
  <span class="crumb">Painel · Mais</span>
@endsection

@section('conteudo')

  <h1 class="m-cabecalho" style="margin-bottom:1.2rem">Mais</h1>

  @foreach ($grupos as $grupo)
    <section class="grupo-mais">
      <p class="label">{{ $grupo['titulo'] }}</p>

      <ul class="lista-mais">
        @foreach ($grupo['itens'] as $item)
          <li>
            @if ($item['rota'])
              <a href="{{ route($item['rota']) }}">
                <span>
                  <b>{{ $item['rotulo'] }}</b>
                  <small>{{ $item['desc'] }}</small>
                </span>
                <span class="seta" aria-hidden="true">›</span>
              </a>
            @else
              {{-- Módulo ainda não construído: fica visível e apagado, para o
                   grupo enxergar o que falta sem procurar tela inexistente. --}}
              <span class="inativo" aria-disabled="true">
                <span>
                  <b>{{ $item['rotulo'] }}</b>
                  <small>{{ $item['desc'] }}</small>
                </span>
                <span class="em-breve">em breve</span>
              </span>
            @endif
          </li>
        @endforeach
      </ul>
    </section>
  @endforeach

@endsection
