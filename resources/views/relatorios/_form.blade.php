{{--
  Formulário do relatório da prefeitura. O mesmo HTML serve a janela aberta
  sobre a lista de publicações e a página inteira (menu Mais, ou sem
  JavaScript, ou quando a geração volta com erro).

  $modal = true  → o X e o Cancelar fecham a janela
  $modal = false → levam de volta às publicações
--}}
<form method="POST" action="{{ route('relatorios.gerar') }}"
      class="modal-box relatorio" data-relatorio data-previa="{{ route('relatorios.previa') }}"
      role="dialog" aria-modal="true" aria-labelledby="relatorio-titulo">
  @csrf

  <header class="modal-head">
    <div>
      <p class="label">Publicações</p>
      <h2 id="relatorio-titulo">Gerar relatório</h2>
      <p class="envio-meta">Publicações do período e alertas entregues ao cliente.</p>
    </div>
    @if ($modal)
      <button type="button" class="x" data-fecha aria-label="Fechar">✕</button>
    @else
      <a class="x" href="{{ route('postagens.index') }}" aria-label="Voltar às publicações">✕</a>
    @endif
  </header>

  <div class="modal-body">

    <div class="rel-erro" data-erros role="alert" @if (! $errors->any()) hidden @endif>
      @foreach ($errors->all() as $erro)
        <p>{{ $erro }}</p>
      @endforeach
    </div>

    {{-- Cliente: o nome vira o município do relatório e o contrato vai no
         cabeçalho. Sem contrato, a opção fica desativada. --}}
    <div class="rel-campo">
      <label class="label" for="rel-cliente">Cliente (município)</label>
      <select class="inp-box" id="rel-cliente" name="cliente" required>
        <option value="">Selecione o cliente</option>
        @foreach ($clientes as $cliente)
          <option value="{{ $cliente->id }}" @disabled(blank($cliente->contrato)) @selected(old('cliente') == $cliente->id)>
            {{ $cliente->nome }} — {{ filled($cliente->contrato) ? 'Contrato ' . $cliente->contrato : 'sem contrato' }}{{ $cliente->ativo ? '' : ' (inativo)' }}
          </option>
        @endforeach
      </select>
      @if ($clientes->isEmpty())
        <p class="rel-ajuda">Nenhum usuário com perfil Cliente cadastrado ainda.</p>
      @elseif ($clientes->contains(fn ($cliente) => blank($cliente->contrato)))
        <p class="rel-ajuda">Cliente sem contrato não gera relatório: preencha o contrato no cadastro do usuário.</p>
      @endif
    </div>

    <fieldset class="rel-campo">
      <legend class="label">Período</legend>
      <div class="rel-datas">
        <label class="rel-data">
          <span>De</span>
          <input class="inp-box" type="date" name="inicio" value="{{ $inicio->format('Y-m-d') }}" required>
        </label>
        <label class="rel-data">
          <span>Até</span>
          <input class="inp-box" type="date" name="fim" value="{{ $fim->format('Y-m-d') }}" required>
        </label>
      </div>
      <div class="rel-atalhos" role="group" aria-label="Atalhos de período">
        @foreach ($atalhos as $atalho)
          @php($marcado = $atalho['inicio']->isSameDay($inicio) && $atalho['fim']->isSameDay($fim))
          <button type="button" @class(['pill', 'on' => $marcado]) aria-pressed="{{ $marcado ? 'true' : 'false' }}"
                  data-periodo data-inicio="{{ $atalho['inicio']->format('Y-m-d') }}" data-fim="{{ $atalho['fim']->format('Y-m-d') }}">
            {{ $atalho['rotulo'] }}
          </button>
        @endforeach
      </div>
    </fieldset>

    {{-- Dá para marcar os dois: cada formato sai num arquivo separado. --}}
    @php($formatos = (array) old('formatos', ['xlsx']))
    <fieldset class="rel-campo">
      <legend class="label">Formato <span class="rel-dica">marque um ou os dois</span></legend>
      <div class="rel-formatos">
        <label class="rel-formato">
          <input type="checkbox" name="formatos[]" value="xlsx" @checked(in_array('xlsx', $formatos, true))>
          <span class="rel-icone rel-icone-xlsx" aria-hidden="true">XLSX</span>
          <span class="rel-formato-texto"><b>Planilha</b><small>Para conferir e editar</small></span>
        </label>
        <label class="rel-formato">
          <input type="checkbox" name="formatos[]" value="pdf" @checked(in_array('pdf', $formatos, true))>
          <span class="rel-icone rel-icone-pdf" aria-hidden="true">PDF</span>
          <span class="rel-formato-texto"><b>PDF</b><small>Para enviar à prefeitura</small></span>
        </label>
      </div>
    </fieldset>

    <section class="rel-previa" aria-live="polite">
      <p class="label">Prévia</p>
      <div class="rel-previa-linha">
        <div class="rel-numeros">
          <div><b data-previa-publicacoes>{{ $publicacoes ?? '—' }}</b><span>publicações</span></div>
          <div><b data-previa-alertas>—</b><span>alertas entregues</span></div>
        </div>
        <p class="rel-arquivo" data-previa-arquivo>Selecione o cliente para ver o nome do arquivo.</p>
      </div>
      <p class="rel-regra">Entram só publicações publicadas e alertas entregues ao cliente.</p>
    </section>
  </div>

  <footer class="modal-foot">
    <div class="acts">
      @if ($modal)
        <button type="button" class="btn btn-ghost" data-fecha>Cancelar</button>
      @else
        <a class="btn btn-ghost" href="{{ route('postagens.index') }}">Cancelar</a>
      @endif
      <button class="btn" type="submit" data-gerar>Gerar e baixar</button>
    </div>
  </footer>
</form>
