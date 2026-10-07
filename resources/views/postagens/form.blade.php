@extends('layouts.painel')

@php($editando = $postagem->exists)

@section('titulo', $editando ? 'Editar publicação' : 'Nova publicação')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
  <h1 style="margin-top:.3rem">{{ $editando ? 'Editar publicação' : 'Nova publicação' }}</h1>
@endsection

@section('conteudo')

  <div class="m-only">
    <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
    <h1 style="margin-top:.3rem">{{ $editando ? 'Editar publicação' : 'Nova publicação' }}</h1>
  </div>

  <form method="POST"
        action="{{ $editando ? route('postagens.update', $postagem) : route('postagens.store') }}">
    @csrf
    @if ($editando)
      @method('PUT')
    @endif

    <div class="form-grid">
      <div>
        {{-- São dez seções, com nomes longos: botões lado a lado não cabem. --}}
        <div class="field">
          <label class="label" for="tipo">Tipo de conteúdo</label>
          <select class="inp-box" id="tipo" name="tipo" required>
            @foreach (\App\Models\Postagem::TIPOS as $valor => $rotulo)
              <option value="{{ $valor }}"
                      @selected(old('tipo', $postagem->tipo) === $valor)>{{ $rotulo['nome'] }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label class="label" for="titulo">Título</label>
          <input class="inp-box" id="titulo" name="titulo"
                 value="{{ old('titulo', $postagem->titulo) }}"
                 placeholder="Ex.: Edital de chamamento público 012/2026" required>
        </div>

        <p class="label" style="margin-bottom:.8rem">Texto da publicação</p>

        {{-- O textarea é o campo de verdade: é ele que vai no POST.
             Se o Quill carregar, ele assume e o textarea fica escondido,
             sincronizado a cada mudança. Se o CDN falhar, o textarea
             continua ali e a publicação ainda pode ser escrita. --}}
        <div class="editor" id="editor-caixa" style="margin-bottom:1.8rem">
          <div id="editor-barra"></div>
          <div id="editor-area">{!! old('texto', $postagem->texto) !!}</div>

          <textarea id="texto" name="texto"
                    placeholder="Escreva o texto da postagem...">{{ old('texto', $postagem->texto) }}</textarea>

          <div class="editor-foot">
            <span id="editor-estado">Texto simples</span>
            <span class="mono" id="palavras">0 palavras</span>
          </div>
        </div>
      </div>

      <aside class="aside">
        <div class="field">
          <label class="label" for="mes_referencia">Mês de referência</label>
          <input class="inp-box" id="mes_referencia" name="mes_referencia" type="month"
                 value="{{ old('mes_referencia', $postagem->mes_referencia ?? now()->format('Y-m')) }}" required>
        </div>

        <div class="field">
          <label class="label" for="data_publicacao">Data da publicação</label>
          <input class="inp-box" id="data_publicacao" name="data_publicacao" type="date"
                 value="{{ old('data_publicacao', $postagem->data_publicacao?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
        </div>

        <button class="btn btn-block" type="submit" name="situacao" value="publicado"
                style="margin-bottom:.8rem">Publicar</button>

        <button class="btn btn-ghost btn-block" type="submit" name="situacao" value="rascunho"
                style="margin-bottom:1rem">Salvar rascunho</button>

        <p style="text-align:center">
          <a href="{{ route('postagens.index') }}">Cancelar</a>
        </p>
      </aside>
    </div>
  </form>

@endsection

@push('scripts')
<script>
  // Destaca o tipo escolhido, já que o radio está escondido.
  document.querySelectorAll('.seg-opcao').forEach(function (op) {
    op.addEventListener('click', function () {
      document.querySelectorAll('.seg-opcao').forEach(function (o) { o.classList.remove('on'); });
      op.classList.add('on');
    });
  });

  // O título assume a cor padrão do tipo escolhido — a mesma que ele terá
  // depois de publicado — para o autor ver o resultado sem precisar salvar.
  (function () {
    var titulo = document.getElementById('titulo');
    var tipo = document.getElementById('tipo');
    if (!titulo || !tipo) return;

    var corDoTipo = @json(collect(\App\Models\Postagem::TIPOS)->map(fn ($t) => $t['cor']));

    function aplicar() {
      titulo.className = 'inp-box';
      var cor = corDoTipo[tipo.value];
      if (cor) titulo.classList.add('cor-' + cor);
    }

    tipo.addEventListener('change', aplicar);
    aplicar();
  })();
</script>
<script src="{{ \App\Support\Asset::url('assets/editor.js') }}"></script>
@endpush
