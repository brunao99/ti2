@extends('layouts.painel')

@php($editando = $postagem->exists)

@section('titulo', $editando ? 'Editar publicação' : 'Nova publicação')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index', $postagem->tipo ? ['tipo' => $postagem->tipo] : []) }}"><span>←</span> Publicações</a>
  <h1 style="margin-top:.3rem">{{ $editando ? 'Editar publicação' : 'Nova publicação' }}</h1>
@endsection

@section('conteudo')

  <div class="m-only">
    <a class="voltar" href="{{ route('postagens.index', $postagem->tipo ? ['tipo' => $postagem->tipo] : []) }}"><span>←</span> Publicações</a>
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

        <div class="field">
          <label class="label" for="link">Link da fonte</label>
          <input class="inp-box" id="link" name="link" type="text" inputmode="url"
                 value="{{ old('link', $postagem->link) }}"
                 placeholder="Ex.: https://www.gov.br/... ou https://www.instagram.com/p/...">
          <p class="muted" style="font-size:.85rem;margin-top:.3rem">
            De onde veio a informação. Sai na coluna LINK do relatório da prefeitura.
          </p>
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

        {{-- Texto do centro da imagem do alerta. Começa com o padrão (o início do
             texto da publicação) e acompanha o editor até a pessoa mexer nele.
             Enviado vazio, fica gravado como "usar o padrão". --}}
        @php($resumoProprio = old('resumo_alerta', $postagem->resumo_alerta))
        <div class="field" style="margin-bottom:1.8rem">
          <label class="label" for="resumo_alerta">Resumo para o alerta no WhatsApp</label>
          <textarea class="inp-box" id="resumo_alerta" name="resumo_alerta" rows="5"
                    maxlength="{{ \App\Support\ResumoAlerta::MAXIMO }}"
                    data-auto="{{ filled($resumoProprio) ? '0' : '1' }}"
                    placeholder="Escreva o texto da publicação: o resumo aparece aqui.">{{ filled($resumoProprio) ? $resumoProprio : \App\Support\ResumoAlerta::padrao(old('texto', $postagem->texto)) }}</textarea>
          <p class="muted" style="font-size:.85rem;margin-top:.3rem">
            Vai no centro da imagem enviada com o alerta. Já vem com o começo do texto da publicação; altere se quiser.
            <button type="button" class="link-botao" id="resumo_padrao" @if (! filled($resumoProprio)) hidden @endif
                    style="background:none;border:0;padding:0;color:inherit;text-decoration:underline;cursor:pointer">Voltar ao padrão</button>
          </p>
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
          <a href="{{ route('postagens.index', $postagem->tipo ? ['tipo' => $postagem->tipo] : []) }}">Cancelar</a>
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
<script>
  // Resumo do alerta: segue o texto da publicação enquanto não for editado.
  // Mesma regra de App\Support\ResumoAlerta::padrao (até 450 caracteres,
  // cortando no fim de uma frase).
  (function () {
    var resumo = document.getElementById('resumo_alerta');
    var campo = document.getElementById('texto');
    var caixa = document.getElementById('editor-caixa');
    var voltar = document.getElementById('resumo_padrao');
    if (!resumo || !campo || !caixa) return;
    var LIMITE = {{ \App\Support\ResumoAlerta::LIMITE }};

    function textoPuro() {
      var d = document.createElement('div');
      d.innerHTML = (campo.value || '').replace(/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/gi, ' ');
      return (d.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function padrao() {
      var texto = textoPuro();
      if (texto.length <= LIMITE) return texto;
      var frases = texto.split(/(?<=[.!?])\s+/), saida = '';
      for (var i = 0; i < frases.length; i++) {
        var tentativa = saida ? saida + ' ' + frases[i] : frases[i];
        if (tentativa.length > LIMITE) break;
        saida = tentativa;
      }
      if (saida) return saida;
      var corte = texto.slice(0, LIMITE), espaco = corte.lastIndexOf(' ');
      return (espaco > 0 ? corte.slice(0, espaco) : corte).replace(/[\s,;:\-–]+$/, '') + '…';
    }

    function atualizar() {
      if (resumo.dataset.auto === '1') resumo.value = padrao();
    }

    // O editor copia o conteúdo para o textarea do texto no próprio evento;
    // o setTimeout lê depois dessa cópia.
    caixa.addEventListener('input', function () { setTimeout(atualizar, 0); });
    caixa.addEventListener('keyup', function () { setTimeout(atualizar, 0); });

    resumo.addEventListener('input', function () {
      resumo.dataset.auto = '0';
      if (voltar) voltar.hidden = false;
    });

    if (voltar) voltar.addEventListener('click', function () {
      resumo.dataset.auto = '1';
      voltar.hidden = true;
      atualizar();
    });

    // Sem edição, vai vazio: o servidor guarda "usar o padrão" e recalcula
    // o resumo se o texto da publicação mudar depois.
    resumo.form.addEventListener('submit', function () {
      if (resumo.dataset.auto === '1') resumo.value = '';
    });
  })();
</script>
<script src="{{ \App\Support\Asset::url('assets/editor.js') }}"></script>
@endpush
