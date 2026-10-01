@extends('layouts.painel')

@section('titulo', 'Início')
@section('cabecalho')
  <span class="crumb">Painel · Início</span>
@endsection

@section('conteudo')

  {{-- Criar postagem é ação da tela de Publicações, não daqui. --}}
  <h1>{{ $saudacao }}, {{ explode(' ', auth()->user()->nome)[0] }}</h1>

  {{-- Insights: só Master e ADM. O Client não recebe nem os números. --}}
  @if ($indicadores)
    <div class="kpis">
      <div class="kpi">
        <span class="label">Publicações no mês</span>
        <b>{{ $indicadores['publicacoes_mes'] }}</b>
      </div>
      <div class="kpi">
        <span class="label">Alertas disparados</span>
        <b>{{ $indicadores['alertas_enviados'] }}</b>
      </div>
      <div class="kpi">
        <span class="label">Rascunhos</span>
        <b>{{ $indicadores['rascunhos'] }}</b>
      </div>
    </div>
  @endif

  <div class="sec-head">
    <h2>Últimas publicações</h2>
    <a href="{{ route('postagens.index') }}">Ver todas</a>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:150px">Tipo</th>
        <th>Título</th>
        <th style="width:130px">Data</th>
        <th style="width:140px">Situação</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($ultimas as $postagem)
        <tr>
          <td><span class="tag" title="{{ $postagem->rotuloTipo() }}">{{ $postagem->rotuloTipoCurto() }}</span></td>
          <td class="t-title">
            <a href="{{ route('postagens.show', $postagem) }}">{{ $postagem->titulo }}</a>
          </td>
          <td class="t-date">{{ $postagem->data_publicacao->format('d/m/Y') }}</td>
          <td>
            <span class="badge {{ $postagem->ehRascunho() ? 'badge-draft' : 'badge-ok' }}">
              {{ $postagem->ehRascunho() ? 'Rascunho' : 'Publicado' }}
            </span>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="muted">Nenhuma publicação ainda.</td></tr>
      @endforelse
    </tbody>
  </table>

@endsection
