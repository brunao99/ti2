{{-- Detalhe de uma data de um evento: cartão ao clicar na agenda. --}}
@php
    use App\Models\AgendaEvento;
    use App\Support\DataPt;

    $e = $evento;
    $pagina ??= false;
    $voltar = ['data' => $o->dia->format('Y-m-d'), 'visao' => $visao];
    $chave = ['evento' => $e->id, 'data' => $o->chave()];
    $situacao = $o->situacao();
    $local = $e->local;
    $ehLink = $local && preg_match('#^https?://#i', $local);
    $disparo = $o->registro?->disparo;
@endphp

<article class="ag-detalhe {{ $e->classeTipo() }}" role="dialog" aria-labelledby="ag-det-titulo">
  <header>
    <p class="ag-det-tipo">@include('agenda._icone', ['nome' => $e->icone()]) {{ $e->nomeTipo() }}</p>
    <h2 id="ag-det-titulo">{{ $e->nome() }}</h2>
    <p class="ag-det-quando">
      {{ DataPt::diaCompleto($o->dia) }}, {{ $o->dia->format('d/m') }}
      @if ($e->ehAlerta())
        · envio às {{ $e->hora_inicio }}
      @elseif ($e->tipo === AgendaEvento::COMPROMISSO && ! $e->semHorario())
        · {{ $e->hora_inicio }}{{ $e->hora_fim ? ' – ' . $e->hora_fim : '' }}
      @elseif ($e->tipo === AgendaEvento::LEMBRETE)
        · {{ $e->hora_inicio }}
      @else
        · dia inteiro
      @endif
    </p>
    @unless ($pagina)
      <button type="button" class="ag-det-x" data-fecha-pop aria-label="Fechar">@include('agenda._icone', ['nome' => 'fechar'])</button>
    @endunless
  </header>

  <dl>
    @if ($e->ehAlerta())
      <div>
        <dt>Situação</dt>
        <dd><span class="ag-situacao s-{{ $situacao }}"><i></i>{{ $o->rotuloSituacao() }}</span></dd>
      </div>
      @if ($situacao === \App\Models\AgendaOcorrencia::ENVIADO && $disparo)
        <div>
          <dt>Envio</dt>
          <dd>{{ $disparo->total_enviados }} de {{ $disparo->total_destinatarios }} entregue(s) ·
            <a href="{{ route('alertas.show', $disparo) }}">ver na Central de alertas</a></dd>
        </div>
      @elseif ($situacao === \App\Models\AgendaOcorrencia::FALHOU)
        <div><dt>Motivo</dt><dd>{{ $o->registro?->detalhe ?? 'O horário passou e o envio não saiu. Confira se o agendador está ligado no servidor.' }}
          @if ($disparo) · <a href="{{ route('alertas.show', $disparo) }}">ver na Central de alertas</a>@endif</dd></div>
      @elseif ($situacao === \App\Models\AgendaOcorrencia::CANCELADO)
        <div><dt>Cancelado por</dt><dd>{{ $o->registro?->usuario?->nome ?? '—' }}, em {{ $o->registro?->created_at?->setTimezone(AgendaEvento::FUSO)->format('d/m \à\s H:i') }}</dd></div>
      @else
        <div><dt>Quem vai receber</dt><dd>{{ $destinatarios }} {{ $destinatarios === 1 ? 'usuário que autorizou' : 'usuários que autorizaram' }} alertas</dd></div>
      @endif
    @endif

    @if ($e->tipo === AgendaEvento::COMPROMISSO)
      @if ($local)
        <div><dt>Local</dt><dd>@if ($ehLink)<a href="{{ $local }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($local, 60) }}</a>@else{{ $local }}@endif</dd></div>
      @endif
      @if ($nomes = $e->nomesDosParticipantes())
        <div><dt>Participantes</dt><dd>{{ collect($nomes)->join(', ', ' e ') }}</dd></div>
      @endif
      @if ($e->cliente)
        <div><dt>Cliente relacionado</dt><dd>{{ $e->cliente->nome }}</dd></div>
      @endif
      @if ($e->lembrar_antes)
        <div><dt>Lembrar antes</dt><dd>{{ AgendaEvento::LEMBRAR[$e->lembrar_antes] ?? '' }}</dd></div>
      @endif
    @endif

    @if ($e->tipo === AgendaEvento::LEMBRETE)
      <div><dt>Para quem</dt><dd>{{ $e->visibilidade === 'eu' ? 'Só para ' . ($e->user_id === auth()->id() ? 'você' : $e->autor?->nome) : 'Toda a equipe' }}</dd></div>
      @if ($e->observacao)
        <div><dt>Observação</dt><dd class="pre">{{ $e->observacao }}</dd></div>
      @endif
    @endif

    @if ($e->tipo === AgendaEvento::DATA_IMPORTANTE)
      <div><dt>Categoria</dt><dd>{{ AgendaEvento::CATEGORIAS[$e->categoria] ?? '—' }}</dd></div>
      @unless ($o->dia->equalTo($o->data))
        <div><dt>Data do prazo</dt><dd>{{ $o->data->format('d/m/Y') }} ({{ DataPt::dia($o->data) }}). Cai em fim de semana ou feriado, por isso aparece no dia útil anterior.</dd></div>
      @endunless
      @if ($e->avisar_antes)
        <div><dt>Avisar antes</dt><dd>{{ $e->avisar_antes }} {{ $e->avisar_antes === 1 ? 'dia' : 'dias' }} antes, no painel</dd></div>
      @endif
      @if ($e->postagem)
        <div><dt>Publicação relacionada</dt><dd><a href="{{ route('postagens.show', $e->postagem) }}">{{ $e->postagem->titulo }}</a></dd></div>
      @endif
      <div><dt>Quem vê</dt><dd>{{ $e->visibilidade === 'eu' ? 'Só quem cadastrou' : 'Toda a equipe' }}</dd></div>
    @endif

    @if ($repete)
      <div><dt>Repete</dt><dd>{{ $repete }}</dd></div>
    @endif

    @if ($e->ehAlerta())
      <div><dt>Modelo</dt><dd>{{ $e->modelo?->nome ?? 'Modelo removido' }}</dd></div>
    @endif

    <div>
      <dt>{{ $e->ehAlerta() ? 'Programado por' : 'Criado por' }}</dt>
      <dd>{{ $e->autor?->nome ?? '—' }}, em {{ $e->created_at?->setTimezone(AgendaEvento::FUSO)->format('d/m \à\s H:i') }}</dd>
    </div>
  </dl>

  <footer>
    <a class="btn btn-ghost btn-sm" href="{{ route('agenda.eventos.editar', array_filter(['evento' => $e->id, 'visao' => $visao])) }}" data-modal-agenda>
      {{ $e->repete() ? 'Editar série' : 'Editar' }}
    </a>

    @if ($o->podeCancelar())
      <form method="POST" action="{{ route('agenda.eventos.cancelar', $chave) }}"
            data-confirmar="{{ $e->ehAlerta() ? 'Cancelar o envio de ' . $o->dia->format('d/m') . '? Este envio não sai.' : 'Cancelar o evento de ' . $o->dia->format('d/m') . '?' }}{{ $e->repete() ? ' As outras datas continuam.' : '' }}">
        @csrf
        @if ($visao)<input type="hidden" name="visao" value="{{ $visao }}">@endif
        <button class="btn btn-ghost btn-sm" type="submit">{{ $e->repete() ? 'Cancelar este' : 'Cancelar' }}</button>
      </form>
    @endif

    @if ($o->podeEnviarAgora())
      <form method="POST" action="{{ route('agenda.eventos.enviar', $chave) }}"
            data-confirmar="Enviar este alerta agora, antes do horário marcado? Ele vai para {{ $destinatarios }} {{ $destinatarios === 1 ? 'usuário' : 'usuários' }}.">
        @csrf
        @if ($visao)<input type="hidden" name="visao" value="{{ $visao }}">@endif
        <button class="btn btn-sm" type="submit">Enviar agora</button>
      </form>
    @endif
  </footer>
</article>
