@extends('layouts.painel')

@section('titulo', 'Enviar alerta')

@section('cabecalho')
  <a class="voltar" href="{{ route('postagens.index') }}"><span>←</span> Publicações</a>
  <h1 style="margin-top:.3rem">Enviar alerta</h1>
@endsection

@section('acoes')
  <a class="btn btn-ghost" href="{{ route('alertas.index') }}">Histórico</a>
@endsection

@section('conteudo')
  <article class="doc">
    <div class="meta">
      <span class="tag">{{ $postagem->rotuloTipo() }}</span>
      <span class="badge badge-ok">Publicado</span>
      <span>{{ $postagem->data_publicacao->format('d/m/Y') }}</span>
    </div>

    <h1>{{ $postagem->titulo }}</h1>
    <p class="muted">O alerta é enviado uma única vez, para os usuários que autorizaram receber no WhatsApp.</p>
  </article>

  <form method="POST" action="{{ route('alertas.disparar', $postagem) }}" class="form-grid" style="margin-top:1.5rem">
    @csrf

    <div>
      <label class="label" for="alert_template_id">Modelo de mensagem</label>
      <select class="inp-box" id="alert_template_id" name="alert_template_id" required>
        <option value="">Selecione um modelo</option>
        @foreach ($modelos as $modelo)
          <option value="{{ $modelo->id }}"
                  data-mensagem="{{ e($modelo->mensagem) }}"
                  @selected(old('alert_template_id') == $modelo->id)>
            {{ $modelo->nome }}
          </option>
        @endforeach
      </select>
      <p class="muted" style="margin-top:.4rem">Variáveis disponíveis: <code>@{{titulo}}</code>, <code>@{{tipo}}</code>, <code>@{{data_publicacao}}</code>.</p>
    </div>

    @php($autorizados = $destinatarios->filter->autorizouAlertas())
    @php($pendentes = $destinatarios->reject->autorizouAlertas())

    <div>
      <p class="label">Quem vai receber</p>
      @if ($autorizados->isEmpty())
        <p class="err show">Nenhum usuário autorizou receber alertas ainda. Cada um autoriza na tela Minha conta.</p>
      @else
        <p><b>{{ $autorizados->count() }}</b> {{ $autorizados->count() === 1 ? 'usuário' : 'usuários' }}:
          {{ $autorizados->pluck('nome')->join(', ', ' e ') }}.</p>
      @endif

      @if ($pendentes->isNotEmpty())
        <p class="muted" style="margin-top:.4rem">
          Com telefone, mas sem autorização, ficam de fora: {{ $pendentes->pluck('nome')->join(', ', ' e ') }}.
        </p>
      @endif
    </div>

    <div>
      <label class="label" for="mensagem_preview">Mensagem que será enviada</label>
      <textarea class="inp-box" id="mensagem_preview" rows="7" readonly placeholder="Selecione um modelo para visualizar a mensagem."></textarea>
    </div>

    <div class="acts">
      <a class="btn btn-ghost" href="{{ route('postagens.index') }}">Cancelar</a>
      <button class="btn" type="submit" onclick="return confirm('Confirmar o disparo deste alerta? Depois do envio, a publicação ficará bloqueada para novo disparo.')">
        Confirmar envio
      </button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
(function () {
  const select = document.getElementById('alert_template_id');
  const preview = document.getElementById('mensagem_preview');
  const titulo = @json($postagem->titulo);
  const tipo = @json($postagem->rotuloTipo());
  const data = @json($postagem->data_publicacao->format('d/m/Y'));

  function atualizar() {
    const option = select.options[select.selectedIndex];
    let mensagem = option?.dataset.mensagem || '';
    mensagem = mensagem.replaceAll('@{{titulo}}', titulo)
      .replaceAll('@{{tipo}}', tipo)
      .replaceAll('@{{data_publicacao}}', data);
    preview.value = mensagem;
  }

  select.addEventListener('change', atualizar);
  atualizar();
})();
</script>
@endpush
