{{-- Janela "Novo feriado". Também é a página de reserva, sem JavaScript. --}}
@php
    use App\Models\Feriado;

    $editando = $feriado->exists;
    $abrangencia = old('abrangencia', $feriado->abrangencia);
    $local = old('local', $feriado->local);
    $repete = old('_enviado') ? (bool) old('repete_todo_ano') : (bool) $feriado->repete_todo_ano;
    $voltar = route('feriados.index', array_filter(['ano' => $ano]));
@endphp

<form method="POST" data-feriado data-janela-form
      action="{{ $editando ? route('feriados.atualizar', $feriado) : route('feriados.salvar') }}"
      class="modal-box ag-janela fer-janela" role="dialog" aria-modal="true" aria-labelledby="fer-form-titulo">
  @csrf
  @if ($editando) @method('PUT') @endif
  <input type="hidden" name="_enviado" value="1">
  @if ($ano)<input type="hidden" name="ano" value="{{ $ano }}">@endif

  <header class="modal-head">
    <div>
      <p class="label">Calendário de feriados</p>
      <h2 id="fer-form-titulo">{{ $editando ? 'Editar feriado' : 'Novo feriado' }}</h2>
    </div>
    @if ($modal)
      <button type="button" class="x" data-fecha aria-label="Fechar">@include('agenda._icone', ['nome' => 'fechar'])</button>
    @else
      <a class="x" href="{{ $voltar }}" aria-label="Voltar ao calendário">@include('agenda._icone', ['nome' => 'fechar'])</a>
    @endif
  </header>

  <div class="modal-body">
    <div class="rel-erro" data-erros role="alert" @if (! $errors->any()) hidden @endif>
      @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
    </div>

    <div class="ag-campo">
      <label class="label" for="fer-desc">Descrição</label>
      <input class="inp-box" id="fer-desc" name="descricao" maxlength="150" required
             value="{{ old('descricao', $feriado->descricao) }}" placeholder="Ex.: Aniversário de Belo Horizonte">
    </div>

    <div class="ag-linha ag-linha-2">
      <div class="ag-campo">
        <label class="label" for="fer-data">Data</label>
        <input class="inp-box" type="date" id="fer-data" name="data" required value="{{ old('data', $feriado->data?->format('Y-m-d')) }}">
      </div>
      <div class="ag-campo">
        <label class="label" for="fer-tipo">Tipo</label>
        <select class="inp-box" id="fer-tipo" name="tipo">
          @foreach (Feriado::TIPOS as $v => $r)<option value="{{ $v }}" @selected(old('tipo', $feriado->tipo) === $v)>{{ $r }}</option>@endforeach
        </select>
      </div>
    </div>

    <div class="ag-linha ag-linha-2">
      <div class="ag-campo">
        <label class="label" for="fer-abr">Abrangência</label>
        <select class="inp-box" id="fer-abr" name="abrangencia" data-fer-abrangencia>
          @foreach (Feriado::ABRANGENCIAS as $v => $r)<option value="{{ $v }}" @selected($abrangencia === $v)>{{ $r }}</option>@endforeach
        </select>
      </div>

      {{-- Estado: lista fixa. Município: sugestões (sede e prefeituras atendidas), aceita outro. --}}
      <div class="ag-campo" data-fer-local="estadual" @if ($abrangencia !== 'estadual') hidden @endif>
        <label class="label" for="fer-estado">Estado</label>
        <select class="inp-box" id="fer-estado" name="local" @disabled($abrangencia !== 'estadual')>
          <option value="">Selecione o estado</option>
          @foreach (Feriado::ESTADOS as $uf)<option value="{{ $uf }}" @selected($local === $uf)>{{ $uf }}</option>@endforeach
        </select>
      </div>
      <div class="ag-campo" data-fer-local="municipal" @if ($abrangencia !== 'municipal') hidden @endif>
        <label class="label" for="fer-municipio">Município</label>
        <input class="inp-box" id="fer-municipio" name="local" list="fer-municipios" maxlength="120"
               value="{{ $abrangencia === 'municipal' ? $local : '' }}" placeholder="Ex.: Belo Horizonte/MG" @disabled($abrangencia !== 'municipal')>
        <datalist id="fer-municipios">
          @foreach ($municipios as $m)<option value="{{ $m }}"></option>@endforeach
        </datalist>
      </div>
    </div>

    <div class="ag-campo">
      <label class="label" for="fer-exp">Expediente (opcional)</label>
      <input class="inp-box" id="fer-exp" name="expediente" maxlength="150"
             value="{{ old('expediente', $feriado->expediente) }}" placeholder="Ex.: expediente a partir das 14h">
    </div>

    <label class="fer-repete-card switch">
      <input type="checkbox" name="repete_todo_ano" value="1" @checked($repete)>
      <span class="switch-trilho" aria-hidden="true"></span>
      <span class="switch-texto">
        <b>Repete todo ano na mesma data</b>
        <small>Desligue para datas que mudam, como Carnaval e Corpus Christi.</small>
      </span>
    </label>
  </div>

  <footer class="modal-foot">
    <span></span>
    <div class="acts">
      @if ($modal)
        <button type="button" class="btn btn-ghost" data-fecha>Cancelar</button>
      @else
        <a class="btn btn-ghost" href="{{ $voltar }}">Cancelar</a>
      @endif
      <button class="btn" type="submit" data-salvar>Salvar feriado</button>
    </div>
  </footer>
</form>
