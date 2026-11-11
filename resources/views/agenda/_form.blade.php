{{--
  Janela "Novo evento" da agenda. O mesmo HTML serve a janela aberta sobre a
  agenda e a página inteira de reserva (sem JavaScript).

  O tipo escolhido fica em data-tipo no formulário; o CSS mostra só os campos
  daquele tipo (atributo data-so) e o JavaScript desliga os escondidos.

  $modal = true  → o X e o Cancelar fecham a janela
  $modal = false → levam de volta à agenda
--}}
@php
    use App\Models\AgendaEvento;
    use App\Support\Recorrencia;

    $editando = $evento->exists;
    $tipo = old('tipo', $evento->tipo);
    $data = old('data', $evento->data?->format('Y-m-d'));
    $repetir = old('repetir', $evento->repetir ?? 'nao');
    $termina = old('termina', $evento->termina ?? 'nunca');
    $dicas = app(\App\Services\Agenda\ResumoRepeticao::class)->dicas(\Carbon\CarbonImmutable::parse($data));
    $voltar = route('agenda.index', array_filter(['data' => $data, 'visao' => $visao]));
    $escolhidos = array_map('intval', (array) old('participantes', $participantes));
    $mostrarEquipe = old('_enviado') ? (bool) old('mostrar_equipe') : $evento->visibilidade !== 'eu';
    $resumo = $editando ? app(\App\Services\Agenda\ResumoRepeticao::class)->frase($evento) : null;
@endphp

<form method="POST" data-evento data-janela-form
      action="{{ $editando ? route('agenda.eventos.atualizar', $evento) : route('agenda.eventos.salvar') }}"
      class="modal-box ag-janela" data-tipo="{{ $tipo }}" data-resumo="{{ route('agenda.resumo') }}"
      role="dialog" aria-modal="true" aria-labelledby="ag-form-titulo">
  @csrf
  @if ($editando) @method('PUT') @endif
  <input type="hidden" name="_enviado" value="1">
  @if ($visao)<input type="hidden" name="visao" value="{{ $visao }}">@endif

  <header class="modal-head">
    <div>
      <p class="label">Agenda</p>
      <h2 id="ag-form-titulo">
        @if ($editando)
          <span data-so="compromisso">Editar compromisso</span><span data-so="alerta">Editar alerta programado</span><span data-so="lembrete">Editar lembrete</span><span data-so="data_importante">Editar data importante</span>
        @else
          <span data-so="compromisso lembrete data_importante">Novo evento</span><span data-so="alerta">Programar alerta</span>
        @endif
      </h2>
    </div>
    @if ($modal)
      <button type="button" class="x" data-fecha aria-label="Fechar">@include('agenda._icone', ['nome' => 'fechar'])</button>
    @else
      <a class="x" href="{{ $voltar }}" aria-label="Voltar à agenda">@include('agenda._icone', ['nome' => 'fechar'])</a>
    @endif
  </header>

  <div class="modal-body">
    <fieldset class="ag-tipos">
      <legend class="sr">Tipo de evento</legend>
      @foreach (AgendaEvento::TIPOS as $chave => $info)
        <label class="ag-tipo-card {{ AgendaEvento::classeDoTipo($chave) }}">
          <input type="radio" name="tipo" value="{{ $chave }}" @checked($tipo === $chave)
                 @disabled($chave === AgendaEvento::ALERTA && $publicadas->isEmpty() && $tipo !== $chave)>
          <span class="ag-tipo-ico">@include('agenda._icone', ['nome' => $info['icone']])</span>
          <b>{{ $info['nome'] }}</b>
          <small>{{ $info['desc'] }}</small>
        </label>
      @endforeach
    </fieldset>

    <div class="rel-erro" data-erros role="alert" @if (! $errors->any()) hidden @endif>
      @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
    </div>

    <div class="ag-corpo">
      <div class="ag-campos">

        {{-- ------------------------------------------------ alerta: o quê --}}
        <div class="ag-campo" data-so="alerta">
          <label class="label" for="ag-postagem">Publicação</label>
          @if ($publicadas->isEmpty())
            <p class="envio-aviso">Não há publicação com situação "publicado". Publique uma notícia antes de programar o alerta.</p>
          @else
            <select class="inp-box" id="ag-postagem" name="postagem_id" data-ag-postagem>
              <option value="">Selecione a publicação</option>
              @foreach ($publicadas as $p)
                <option value="{{ $p->id }}" data-titulo="{{ $p->titulo }}" data-tipo="{{ $p->rotuloTipo() }}"
                        data-data="{{ $p->data_publicacao->format('d/m/Y') }}"
                        @selected((int) old('postagem_id', $evento->ehAlerta() ? $evento->postagem_id : null) === $p->id)>{{ $p->titulo }}</option>
              @endforeach
            </select>
          @endif
        </div>

        <div class="ag-campo" data-so="alerta">
          <label class="label" for="ag-modelo">Modelo de mensagem</label>
          <select class="inp-box" id="ag-modelo" name="alert_template_id" data-ag-modelo>
            @if ($modelos->count() !== 1)<option value="">Selecione o modelo</option>@endif
            @foreach ($modelos as $m)
              <option value="{{ $m->id }}" data-mensagem="{{ $m->mensagem }}"
                      @selected((int) old('alert_template_id', $evento->alert_template_id) === $m->id)>{{ $m->nome }}</option>
            @endforeach
          </select>
        </div>

        {{-- ------------------------------------------------- título / nome --}}
        <div class="ag-campo" data-so="compromisso lembrete data_importante">
          <label class="label" for="ag-titulo">
            <span data-so="compromisso">Título</span><span data-so="lembrete">Lembrete</span><span data-so="data_importante">Nome da data</span>
          </label>
          <input class="inp-box" id="ag-titulo" name="titulo" maxlength="200" value="{{ old('titulo', $evento->titulo) }}"
                 placeholder="Ex.: Reunião com a Prefeitura de Divinópolis/MG"
                 data-ph-compromisso="Ex.: Reunião com a Prefeitura de Divinópolis/MG"
                 data-ph-lembrete="Ex.: Enviar relatório mensal às prefeituras"
                 data-ph-data_importante="Ex.: Prazo: RREO no Siconfi (5º bimestre)">
        </div>

        {{-- ------------------------------------------------- quando --}}
        <div class="ag-linha">
          <div class="ag-campo">
            <label class="label" for="ag-data"><span data-so="alerta">Data do envio</span><span data-so="compromisso lembrete data_importante">Data</span></label>
            <input class="inp-box" type="date" id="ag-data" name="data" value="{{ $data }}" required data-ag-resumo>
          </div>
          <div class="ag-campo" data-so="compromisso alerta lembrete" data-hora>
            <label class="label" for="ag-hora"><span data-so="compromisso">Início</span><span data-so="lembrete">Hora</span><span data-so="alerta">Hora do envio</span></label>
            <input class="inp-box" type="time" id="ag-hora" name="hora_inicio" value="{{ old('hora_inicio', $evento->hora_inicio) }}" data-ag-resumo>
          </div>
          <div class="ag-campo" data-so="compromisso" data-hora>
            <label class="label" for="ag-fim">Fim</label>
            <input class="inp-box" type="time" id="ag-fim" name="hora_fim" value="{{ old('hora_fim', $evento->hora_fim) }}" data-ag-resumo>
          </div>
          <div class="ag-campo" data-so="data_importante">
            <label class="label" for="ag-categoria">Categoria</label>
            <select class="inp-box" id="ag-categoria" name="categoria">
              @foreach (AgendaEvento::CATEGORIAS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('categoria', $evento->categoria) === $valor)>{{ $rotulo }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <label class="ag-check" data-so="compromisso">
          <input type="checkbox" name="dia_inteiro" value="1" @checked(old('dia_inteiro', $evento->dia_inteiro)) data-ag-dia-inteiro data-ag-resumo>
          Dia inteiro (sem horário)
        </label>

        {{-- ------------------------------------------------- repetição --}}
        <div class="ag-linha ag-linha-2">
          <div class="ag-campo">
            <label class="label" for="ag-repetir">Repetir</label>
            <select class="inp-box" id="ag-repetir" name="repetir" data-ag-repetir data-ag-resumo>
              @foreach (Recorrencia::OPCOES as $valor => $rotulo)
                <option value="{{ $valor }}" data-rotulo="{{ $rotulo }}" @selected($repetir === $valor)>{{ $rotulo }}{{ $dicas[$valor] ? ' — ' . $dicas[$valor] : '' }}</option>
              @endforeach
            </select>
          </div>
          <div class="ag-campo" data-termina-campo @if ($repetir === 'nao') hidden @endif>
            <label class="label" for="ag-termina">Termina</label>
            <select class="inp-box" id="ag-termina" name="termina" data-ag-termina data-ag-resumo>
              <option value="nunca" @selected($termina === 'nunca')>Nunca</option>
              <option value="data" @selected($termina === 'data')>Em uma data</option>
              <option value="vezes" @selected($termina === 'vezes')>Após um número de vezes</option>
            </select>
            <input class="inp-box ag-sub" type="date" name="termina_em" aria-label="Data em que a repetição termina"
                   value="{{ old('termina_em', $evento->termina_em?->format('Y-m-d')) }}" data-termina-so="data" data-ag-resumo
                   @if ($termina !== 'data') hidden @endif>
            <span class="ag-sub ag-vezes" data-termina-so="vezes" @if ($termina !== 'vezes') hidden @endif>
              <input class="inp-box" type="number" min="1" max="500" name="termina_vezes" aria-label="Número de vezes"
                     value="{{ old('termina_vezes', $evento->termina_vezes) }}" data-ag-resumo>
              <span data-ag-unidade>vezes</span>
            </span>
          </div>
        </div>

        <p class="ag-resumo" data-ag-frase aria-live="polite" @if (! $resumo) hidden @endif>
          @include('agenda._icone', ['nome' => 'repetir'])<span>{!! $resumo !!}</span>
        </p>

        {{-- ------------------------------------------------- compromisso --}}
        <div class="ag-campo" data-so="compromisso">
          <label class="label" for="ag-local">Local ou link da reunião</label>
          <input class="inp-box" id="ag-local" name="local" maxlength="500" value="{{ old('local', $evento->local) }}"
                 placeholder="Ex.: Prefeitura, gabinete — ou https://teams.microsoft.com/…">
        </div>

        <fieldset class="ag-campo" data-so="compromisso">
          <legend class="label">Participantes</legend>
          <div class="ag-chips">
            @foreach ($administradores as $adm)
              <label class="ag-chip">
                <input type="checkbox" name="participantes[]" value="{{ $adm->id }}" @checked(in_array($adm->id, $escolhidos, true))>
                <span class="avatar" aria-hidden="true">{{ $adm->iniciais() }}</span>{{ $adm->nome }}
              </label>
            @endforeach
          </div>
        </fieldset>

        <div class="ag-linha ag-linha-2" data-so="compromisso">
          <div class="ag-campo">
            <label class="label" for="ag-lembrar">Lembrar antes</label>
            <select class="inp-box" id="ag-lembrar" name="lembrar_antes">
              <option value="">Não avisar</option>
              @foreach (AgendaEvento::LEMBRAR as $min => $rotulo)
                <option value="{{ $min }}" @selected((int) old('lembrar_antes', $evento->lembrar_antes) === $min)>{{ $rotulo }}</option>
              @endforeach
            </select>
          </div>
          <div class="ag-campo">
            <label class="label" for="ag-cliente">Cliente relacionado (opcional)</label>
            <select class="inp-box" id="ag-cliente" name="cliente_id">
              <option value="">Nenhum</option>
              @foreach ($clientes as $c)
                <option value="{{ $c->id }}" @selected((int) old('cliente_id', $evento->cliente_id) === $c->id)>{{ $c->nome }}</option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- ------------------------------------------------- lembrete --}}
        <div class="ag-campo" data-so="lembrete">
          <label class="label" for="ag-para">Para quem</label>
          <select class="inp-box" id="ag-para" name="visibilidade">
            <option value="eu" @selected(old('visibilidade', $editando ? $evento->visibilidade : 'eu') === 'eu')>Só para mim</option>
            <option value="equipe" @selected(old('visibilidade', $editando ? $evento->visibilidade : 'eu') === 'equipe')>Toda a equipe</option>
          </select>
        </div>
        <div class="ag-campo" data-so="lembrete">
          <label class="label" for="ag-obs">Observação (opcional)</label>
          <textarea class="inp-box" id="ag-obs" name="observacao" rows="3" maxlength="2000">{{ old('observacao', $evento->observacao) }}</textarea>
        </div>

        {{-- ------------------------------------------------- data importante --}}
        <div class="ag-linha ag-linha-2" data-so="data_importante">
          <div class="ag-campo">
            <label class="label" for="ag-avisar">Avisar antes</label>
            <select class="inp-box" id="ag-avisar" name="avisar_antes">
              <option value="">Não avisar</option>
              @foreach (AgendaEvento::AVISAR as $dias)
                <option value="{{ $dias }}" @selected((int) old('avisar_antes', $evento->avisar_antes) === $dias)>{{ $dias }} {{ $dias === 1 ? 'dia' : 'dias' }} antes, no painel</option>
              @endforeach
            </select>
          </div>
          <div class="ag-campo">
            <label class="label" for="ag-pub-rel">Publicação relacionada (opcional)</label>
            <select class="inp-box" id="ag-pub-rel" name="publicacao_relacionada_id">
              <option value="">Nenhuma</option>
              @foreach ($publicacoes as $p)
                <option value="{{ $p->id }}" @selected((int) old('publicacao_relacionada_id', $evento->tipo === AgendaEvento::DATA_IMPORTANTE ? $evento->postagem_id : null) === $p->id)>{{ $p->titulo }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <label class="ag-check" data-so="data_importante">
          <input type="checkbox" name="mostrar_equipe" value="1" @checked($mostrarEquipe)>
          Mostrar para toda a equipe
        </label>

        {{-- ------------------------------------------------- alerta: prévia --}}
        <div class="ag-campo" data-so="alerta">
          <p class="label">Mensagem que será enviada</p>
          <div class="ag-zap" data-ag-previa>
            <p data-ag-previa-texto></p>
            <small><span data-ag-previa-hora></span> ✓✓</small>
          </div>
        </div>
        <p class="ag-nota" data-so="alerta">
          @include('agenda._icone', ['nome' => 'info'])
          Cada envio sai sozinho no horário marcado e aparece na Central de alertas. Dá para editar ou cancelar os envios pela agenda.
        </p>
      </div>

      <aside class="envio-destino ag-destino" data-so="alerta">
        <p class="label">Quem vai receber</p>
        <p class="envio-total"><b>{{ $destinatarios }}</b>{{ $destinatarios === 1 ? 'usuário autorizou' : 'usuários autorizaram' }} receber alertas.</p>
        @if ($destinatarios === 0)
          <p class="envio-aviso">Ninguém autorizou receber alertas ainda. Cada usuário autoriza em Minha conta.</p>
        @endif
        @if ($semAutorizacao)
          <p class="envio-fora"><b>{{ $semAutorizacao }} {{ $semAutorizacao === 1 ? 'fica' : 'ficam' }} de fora</b>, sem autorização.</p>
        @endif
        <p class="envio-fora">Quem autorizar até o dia do envio também recebe.</p>
      </aside>
    </div>
  </div>

  <footer class="modal-foot">
    @if ($editando)
      <button type="submit" form="ag-excluir-{{ $evento->id }}" class="ag-excluir">
        @include('agenda._icone', ['nome' => 'lixeira'])
        <span data-so="alerta">Excluir a série</span><span data-so="compromisso lembrete data_importante">Excluir{{ $evento->repete() ? ' todas as datas' : '' }}</span>
      </button>
    @else
      <span></span>
    @endif
    <div class="acts">
      @if ($modal)
        <button type="button" class="btn btn-ghost" data-fecha>Cancelar</button>
      @else
        <a class="btn btn-ghost" href="{{ $voltar }}">Cancelar</a>
      @endif
      <button class="btn" type="submit" data-salvar>
        @if ($editando)
          Salvar
        @else
          <span data-so="alerta">Programar alerta</span><span data-so="compromisso lembrete data_importante">Salvar</span>
        @endif
      </button>
    </div>
  </footer>
</form>

@if ($editando)
  <form method="POST" action="{{ route('agenda.eventos.excluir', $evento) }}" id="ag-excluir-{{ $evento->id }}"
        data-confirmar="{{ $evento->ehAlerta() ? 'Excluir este alerta programado? Os envios que ainda não saíram são cancelados; os já enviados continuam na Central de alertas.' : 'Excluir este evento' . ($evento->repete() ? ' e todas as suas datas' : '') . '?' }}">
    @csrf
    @method('DELETE')
    @if ($visao)<input type="hidden" name="visao" value="{{ $visao }}">@endif
  </form>
@endif
