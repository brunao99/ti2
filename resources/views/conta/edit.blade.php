@extends('layouts.painel')

@section('titulo', 'Minha conta')
@section('cabecalho')
  <span class="crumb">Painel · Minha conta</span>
@endsection

@section('conteudo')

  {{-- Desktop: cartão de perfil fixo à esquerda, seções à direita.
       Mobile: uma coluna — perfil, seções e, por último, sair. --}}
  <div class="conta">

    {{-- ---------------------------------------------------------- perfil --}}
    <aside class="conta-card conta-perfil">
      <span class="avatar avatar-g">{{ $eu->iniciais() }}</span>
      <h1 class="perfil-nome">{{ $eu->nome }}</h1>
      <p class="perfil-login mono">{{ '@' . $eu->usuario }}</p>

      <div class="perfil-tags">
        <span class="tag">{{ $eu->rotuloPerfil() }}</span>
        <span class="badge {{ $eu->ativo ? 'badge-ok' : 'badge-draft' }}">{{ $eu->ativo ? 'Conta ativa' : 'Conta inativa' }}</span>
      </div>


      @if ($atividade)
        <div class="perfil-numeros">
          <div>
            <b>{{ $atividade['publicacoes'] }}</b>
            <span>{{ $atividade['publicacoes'] === 1 ? 'publicação' : 'publicações' }}</span>
          </div>
          <div>
            <b>{{ $atividade['alertas'] }}</b>
            <span>{{ $atividade['alertas'] === 1 ? 'alerta disparado' : 'alertas disparados' }}</span>
          </div>
        </div>
      @endif
    </aside>

    {{-- --------------------------------------------------------- seções --}}
    <div class="conta-secoes">

      {{-- Dados da conta --}}
      <section class="conta-card">
        <header class="card-cab">
          <div>
            <h2>Dados da conta</h2>
            <p class="muted">Informações do seu cadastro no painel.</p>
          </div>
          @if ($eu->podeEditarUsuario($eu))
            <a class="btn btn-ghost btn-sm" href="{{ route('usuarios.edit', $eu) }}">Editar cadastro</a>
          @endif
        </header>

        <dl class="dados">
          <div><dt>Nome completo</dt><dd>{{ $eu->nome }}</dd></div>
          <div><dt>Usuário (login)</dt><dd class="mono">{{ $eu->usuario }}</dd></div>
          <div><dt>E-mail</dt><dd>{{ $eu->email }}</dd></div>
          <div><dt>WhatsApp</dt><dd>{{ $eu->telefone ? $eu->telefoneFormatado() : 'Não informado' }}</dd></div>
          <div><dt>Perfil de acesso</dt><dd>{{ $eu->rotuloPerfil() }}</dd></div>
          <div><dt>Situação</dt><dd>{{ $eu->ativo ? 'Ativa' : 'Inativa' }}</dd></div>
          <div><dt>Conta criada em</dt><dd>{{ $eu->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
          <div><dt>Última alteração</dt><dd>{{ $eu->updated_at?->format('d/m/Y \à\s H:i') ?? '—' }}</dd></div>
        </dl>

        @unless ($eu->podeEditarUsuario($eu))
          <p class="card-rodape muted">Para alterar nome, e-mail, login ou senha, fale com um administrador.</p>
        @endunless
      </section>

      {{-- Permissões --}}
      <section class="conta-card">
        <header class="card-cab">
          <div>
            <h2>Permissões</h2>
            <p class="muted">O que o perfil {{ $eu->rotuloPerfil() }} pode fazer no painel.</p>
          </div>
        </header>

        <ul class="permissoes">
          @foreach ($eu->permissoesDoPerfil() as $permissao)
            <li>
              <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
              {{ $permissao }}
            </li>
          @endforeach
        </ul>
      </section>

      {{-- Alertas no WhatsApp --}}
      <section class="conta-card" id="alertas">
        <header class="card-cab">
          <div>
            <h2>Alertas no WhatsApp</h2>
            <p class="muted">Um aviso no seu WhatsApp sempre que sair uma publicação nova.</p>
          </div>
          <span class="status {{ $eu->autorizouAlertas() ? 'status-on' : 'status-off' }}">
            {{ $eu->autorizouAlertas() ? 'Ativado' : 'Desativado' }}
          </span>
        </header>

        <form method="POST" action="{{ route('conta.update') }}" class="alertas-form">
          @csrf
          @method('PUT')

          <div class="alertas-campos">
            <div>
              <label class="label" for="telefone">Número de WhatsApp</label>
              <input class="inp-box" id="telefone" name="telefone" type="tel" inputmode="tel"
                     autocomplete="tel" placeholder="(31) 99999-0001"
                     value="{{ old('telefone', $eu->telefone ? $eu->telefoneFormatado() : '') }}">
            </div>

            <label class="switch">
              <input type="checkbox" name="autorizo" value="1" @checked(old('autorizo', $eu->autorizouAlertas()))>
              <span class="switch-trilho" aria-hidden="true"></span>
              <span class="switch-texto">
                <b>Autorizo receber alertas neste número</b>
                <small>Você pode desativar quando quiser.</small>
              </span>
            </label>
          </div>

          <div class="card-acoes">
            <p class="muted">
              @if ($eu->autorizouAlertas())
                Autorizado em {{ $eu->alerta_consentimento_em->format('d/m/Y \à\s H:i') }}.
              @else
                Você ainda não autorizou o recebimento.
              @endif
            </p>
            <button class="btn" type="submit">Salvar</button>
          </div>
        </form>
      </section>

      {{-- Aparência: o tema é escolhido só aqui e fica salvo neste navegador. --}}
      <section class="conta-card">
        <header class="card-cab">
          <div>
            <h2>Aparência</h2>
            <p class="muted">Vale para todas as páginas do painel, neste aparelho.</p>
          </div>
        </header>

        <div class="temas" role="radiogroup" aria-label="Tema do painel">
          <button type="button" class="tema" data-tema="auto" role="radio">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" class="cheio"/></svg>
            <b>Automático</b>
            <small>Segue o seu aparelho</small>
          </button>
          <button type="button" class="tema" data-tema="light" role="radio">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <b>Claro</b>
            <small>Fundo branco</small>
          </button>
          <button type="button" class="tema" data-tema="dark" role="radio">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <b>Escuro</b>
            <small>Fundo escuro</small>
          </button>
        </div>
      </section>
    </div>

    {{-- ------------------------------------------------------------ sair --}}
    <section class="conta-card conta-sair">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-perigo">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sair da conta
        </button>
      </form>
      <p class="muted">Encerra a sessão neste aparelho.</p>
    </section>

  </div>

@endsection
