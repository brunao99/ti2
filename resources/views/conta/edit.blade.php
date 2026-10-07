@extends('layouts.painel')

@section('titulo', 'Minha conta')
@section('cabecalho')
  <h1>Minha conta</h1>
@endsection

@section('conteudo')

  <div class="conta">

    {{-- Quem é --}}
    <section class="conta-topo">
      <span class="avatar avatar-g">{{ $eu->iniciais() }}</span>
      <div class="conta-quem">
        <h1>{{ $eu->nome }}</h1>
        <div class="conta-tags">
          <span class="tag">{{ $eu->rotuloPerfil() }}</span>
          <span class="badge {{ $eu->ativo ? 'badge-ok' : 'badge-draft' }}">{{ $eu->ativo ? 'Ativa' : 'Inativa' }}</span>
        </div>
      </div>
    </section>

    {{-- Dados da conta --}}
    <section class="conta-bloco">
      <h2>Dados da conta</h2>

      <dl class="dados">
        <div><dt>Nome completo</dt><dd>{{ $eu->nome }}</dd></div>
        <div><dt>Usuário (login)</dt><dd class="mono">{{ $eu->usuario }}</dd></div>
        <div><dt>E-mail</dt><dd>{{ $eu->email }}</dd></div>
        <div><dt>WhatsApp</dt><dd>{{ $eu->telefone ? $eu->telefoneFormatado() : 'Não informado' }}</dd></div>
        <div><dt>Perfil de acesso</dt><dd>{{ $eu->rotuloPerfil() }}</dd></div>
        <div><dt>Situação</dt><dd>{{ $eu->ativo ? 'Conta ativa' : 'Conta inativa' }}</dd></div>
        <div><dt>Conta criada em</dt><dd>{{ $eu->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
        <div><dt>Última alteração</dt><dd>{{ $eu->updated_at?->format('d/m/Y \à\s H:i') ?? '—' }}</dd></div>
        @if ($atividade)
          <div><dt>Publicações criadas</dt><dd>{{ $atividade['publicacoes'] }}</dd></div>
          <div><dt>Alertas disparados</dt><dd>{{ $atividade['alertas'] }}</dd></div>
        @endif
      </dl>

      <p class="label" style="margin-top:1.6rem">O que o seu perfil permite</p>
      <ul class="permissoes">
        @foreach ($eu->permissoesDoPerfil() as $permissao)
          <li>{{ $permissao }}</li>
        @endforeach
      </ul>

      <p class="muted conta-obs">
        @if ($eu->podeEditarUsuario($eu))
          Para alterar nome, e-mail, login ou senha, <a href="{{ route('usuarios.edit', $eu) }}">edite seu cadastro</a>.
        @else
          Para alterar nome, e-mail, login ou senha, fale com um administrador.
        @endif
      </p>
    </section>

    {{-- Alertas no WhatsApp --}}
    <section class="conta-bloco">
      <h2>Alertas no WhatsApp</h2>
      <p class="muted" style="margin-bottom:1.4rem">
        Quando sair uma publicação nova no portal, enviamos um aviso para o seu WhatsApp.
        Você só recebe se autorizar aqui, e pode parar quando quiser.
      </p>

      <form method="POST" action="{{ route('conta.update') }}">
        @csrf
        @method('PUT')

        <div class="field">
          <label class="label" for="telefone">Número de WhatsApp</label>
          <input class="inp-box" id="telefone" name="telefone" type="tel" inputmode="tel"
                 placeholder="(31) 99999-0001"
                 value="{{ old('telefone', $eu->telefone ? $eu->telefoneFormatado() : '') }}">
        </div>

        <div class="field">
          <label class="check">
            <input type="checkbox" name="autorizo" value="1" @checked(old('autorizo', $eu->autorizouAlertas()))>
            <span>Autorizo receber alertas de novas publicações neste número</span>
          </label>
          @if ($eu->autorizouAlertas())
            <p class="muted" style="font-size:.85rem;margin-top:.3rem">
              Autorizado em {{ $eu->alerta_consentimento_em->format('d/m/Y \à\s H:i') }}.
              Desmarque e salve para parar de receber.
            </p>
          @endif
        </div>

        <button class="btn" type="submit">Salvar alertas</button>
      </form>
    </section>

    {{-- Aparência: o tema é escolhido só aqui. Fica salvo neste navegador. --}}
    <section class="conta-bloco">
      <h2>Aparência</h2>
      <p class="muted" style="margin-bottom:1rem">
        Vale para todas as páginas do painel, neste aparelho.
      </p>

      <div class="seg" role="radiogroup" aria-label="Tema do painel">
        <button type="button" data-tema="auto" role="radio">Automático</button>
        <button type="button" data-tema="light" role="radio">Claro</button>
        <button type="button" data-tema="dark" role="radio">Escuro</button>
      </div>
      <p class="muted" style="font-size:.85rem;margin-top:.6rem">
        Automático segue o modo claro ou escuro do seu aparelho.
      </p>
    </section>

    {{-- Sair --}}
    <section class="conta-bloco">
      <h2>Sair</h2>
      <p class="muted" style="margin-bottom:1rem">Encerra a sessão neste aparelho.</p>

      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-perigo">Sair da conta</button>
      </form>
    </section>

  </div>

@endsection
