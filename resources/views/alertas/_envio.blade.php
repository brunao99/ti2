{{--
  Formulário de envio de alerta. O mesmo HTML serve a janela (modal) aberta
  sobre a lista de publicações e a página inteira de reserva, usada sem
  JavaScript ou quando o disparo volta com erro.

  $modal = true  → o X e o Cancelar fecham a janela
  $modal = false → levam de volta às publicações
--}}
@php($autorizados = $destinatarios->filter->autorizouAlertas())
@php($pendentes = $destinatarios->reject->autorizouAlertas())

<form method="POST" action="{{ route('alertas.disparar', $postagem) }}"
      class="modal-box envio" data-envio
      data-titulo="{{ $postagem->titulo }}"
      data-tipo="{{ $postagem->rotuloTipo() }}"
      data-data="{{ $postagem->data_publicacao->format('d/m/Y') }}"
      role="dialog" aria-modal="true" aria-labelledby="envio-titulo">
  @csrf

  <header class="modal-head">
    <div>
      <p class="label">Enviar alerta</p>
      <h2 id="envio-titulo">{{ $postagem->titulo }}</h2>
      <p class="envio-meta">{{ $postagem->rotuloTipo() }} · publicada em {{ $postagem->data_publicacao->format('d/m/Y') }}</p>
    </div>
    @if ($modal)
      <button type="button" class="x" data-fecha aria-label="Fechar">✕</button>
    @else
      <a class="x" href="{{ route('postagens.index') }}" aria-label="Voltar às publicações">✕</a>
    @endif
  </header>

  <div class="modal-body">
    <div class="envio-grid">

      <div class="envio-campos">
        <div class="field">
          <label class="label" for="alert_template_id">Modelo de mensagem</label>
          <select class="inp-box" id="alert_template_id" name="alert_template_id" required>
            <option value="">Selecione um modelo</option>
            @foreach ($modelos as $modelo)
              <option value="{{ $modelo->id }}" data-mensagem="{{ $modelo->mensagem }}"
                      @selected(old('alert_template_id') == $modelo->id)>
                {{ $modelo->nome }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="label" for="mensagem_preview">Mensagem que será enviada</label>
          <textarea class="inp-box envio-preview" id="mensagem_preview" rows="6" readonly
                    placeholder="Selecione um modelo para ver a mensagem."></textarea>
        </div>
      </div>

      <aside class="envio-destino">
        <p class="label">Quem vai receber</p>
        <p class="envio-total">
          <b>{{ $autorizados->count() }}</b>
          {{ $autorizados->count() === 1 ? 'usuário autorizou' : 'usuários autorizaram' }}
        </p>

        @if ($autorizados->isEmpty())
          <p class="envio-aviso">Ninguém autorizou receber alertas ainda. Cada usuário autoriza em Minha conta.</p>
        @else
          <ul class="envio-nomes">
            @foreach ($autorizados as $usuario)
              <li>{{ $usuario->nome }}</li>
            @endforeach
          </ul>
        @endif

        @if ($pendentes->isNotEmpty())
          <p class="envio-fora">
            <b>Ficam de fora</b>, sem autorização:
            {{ $pendentes->pluck('nome')->join(', ', ' e ') }}.
          </p>
        @endif
      </aside>

    </div>
  </div>

  <footer class="modal-foot">
    <p class="muted">O alerta é enviado uma única vez por publicação.</p>
    <div class="acts">
      @if ($modal)
        <button type="button" class="btn btn-ghost" data-fecha>Cancelar</button>
      @else
        <a class="btn btn-ghost" href="{{ route('postagens.index') }}">Cancelar</a>
      @endif
      <button class="btn" type="submit" @disabled($autorizados->isEmpty())>Confirmar envio</button>
    </div>
  </footer>
</form>
