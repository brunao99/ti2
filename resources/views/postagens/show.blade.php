@extends('layouts.painel')

@section('titulo', 'Visualizar publicação')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
  <h1 style="margin-top:.3rem">Pré-visualização</h1>
@endsection

@section('acoes')
  @if (auth()->user()->podeGerenciarPostagens())
    @if ($postagem->situacao === 'publicado' && ! $postagem->alerta_enviado)
      <a class="btn" href="{{ route('alertas.enviar', $postagem) }}" data-modal-envio>Enviar alerta</a>
    @endif
    <a class="btn btn-ghost" href="{{ route('postagens.edit', $postagem) }}">Editar</a>
  @endif
@endsection

@section('conteudo')

  <div class="m-only">
    <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
  </div>

  <article class="doc">
    <div class="meta">
      <span class="tag">{{ $postagem->rotuloTipo() }}</span>
      <span class="badge {{ $postagem->ehRascunho() ? 'badge-draft' : 'badge-ok' }}">
        {{ $postagem->ehRascunho() ? 'Rascunho' : 'Publicado' }}
      </span>
      <span>{{ $postagem->data_publicacao->format('d/m/Y') }}</span>
      <span>
        <span class="dot {{ $postagem->alerta_enviado ? 'on' : '' }}"></span>
        {{ $postagem->alerta_enviado ? 'Alerta enviado' : 'Sem alerta' }}
      </span>
    </div>

    <h1 class="{{ $postagem->classeCor() }}">{{ $postagem->titulo }}</h1>

    @if ($postagem->link)
      <p class="fonte">
        <span class="label">Fonte</span>
        <a href="{{ $postagem->link }}" target="_blank" rel="noopener noreferrer">{{ $postagem->link }}</a>
      </p>
    @endif

    {{-- O HTML aqui já passou pelo SanitizadorHtml na gravação, então pode ser
         impresso sem escapar. Publicações antigas, de antes do editor, são texto
         puro: nesse caso escapamos e preservamos as quebras de linha. --}}
    <div class="corpo">
      @if (Str::contains($postagem->texto, '<'))
        {!! $postagem->texto !!}
      @else
        {!! nl2br(e($postagem->texto)) !!}
      @endif
    </div>

    @if ($postagem->anexos->isNotEmpty())
      <p class="label" style="margin-top:2rem">Anexos</p>
      @foreach ($postagem->anexos as $anexo)
        <div class="attach">
          <span class="tag">{{ $anexo->extensao() }}</span>
          <span>{{ $anexo->nome_original }}</span>
          <span class="muted mono" style="margin-left:auto">{{ $anexo->tamanhoLegivel() }}</span>
        </div>
      @endforeach
    @endif

    <div class="hr"></div>
    <p class="muted" style="font-size:.9rem">
      Publicado por {{ $postagem->autor->nome }} · {{ $postagem->autor->rotuloPerfil() }}
      · {{ $postagem->created_at->format('d/m/Y \à\s H:i') }}
    </p>
  </article>

@endsection
