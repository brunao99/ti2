# Painel Administrativo — Algoritmos e Dados

Sistema de publicações e controle de acesso, em Laravel 12 / PHP 8.3.
Substitui o painel do Joomla 3.10 que está em produção hoje.

**Homologação no ar:** <https://algoritimosedados-hml.up.railway.app>

## Rodar na sua máquina

Precisa de **PHP 8.2+** e **Composer**.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Abre em <http://localhost:8000>. O `.env.example` usa **SQLite** — um arquivo,
sem instalar banco nenhum.

As contas de acesso são criadas pela própria migration, então `migrate` basta.

Enquanto não houver MySQL, as migrations originais ainda são editadas direto
(o banco do Railway nasce do zero a cada deploy). Se o seu banco local der
erro de coluna depois de puxar mudanças, recrie com `php artisan migrate:fresh`.

### Contas

| Login | Senha | Perfil |
|---|---|---|
| `usuariomaster` | `12345678` | Master |
| `usuarioadm` | `12345678` | ADM |
| `usuariocliente` | `12345678` | Client |

**São credenciais de homologação. Trocar antes de qualquer uso real.**

## Os três perfis

| | Client | ADM | Master |
|---|---|---|---|
| Ver publicações publicadas | ✅ | ✅ | ✅ |
| Ver rascunhos | ❌ | ✅ | ✅ |
| Criar / editar / excluir publicação | ❌ | ✅ | ✅ |
| Ver os indicadores do painel | ❌ | ✅ | ✅ |
| Cadastrar usuários | ❌ | ✅ | ✅ |
| Ver e editar conta Master | ❌ | ❌ | ✅ |

A regra existe em dois lugares, com papéis diferentes:

- **`app/Http/Middleware/VerificaPerfil.php`** barra a rota inteira. É a trava.
- **`User::pode*()`** decide o que aparece na tela. É conveniência visual.

Esconder botão não protege nada. Por isso o `UsuarioController` também valida
o perfil recebido contra `perfisQuePodeAtribuir()` — senão um ADM criaria um
Master editando o HTML do formulário.

Verificado no ambiente publicado: um Client autenticado recebe **403** ao
abrir `/postagens/nova` ou `/usuarios` pela URL direta.

## Tipos de publicação

São as dez seções do menu INFORMAÇÕES do portal, definidas em
`Postagem::TIPOS` — fonte única de validação, formulário, filtro e rótulo.

A coluna `tipo` é `string`, não enum de banco: essa lista acompanha o portal e
vai mudar, e cada ajuste num enum custaria outra migration.

O filtro da listagem aceita **vários tipos ao mesmo tempo**; nenhum marcado
significa todos.

## Editor de texto

`public/assets/editor.js`, escrito sem biblioteca externa — Quill e TinyMCE
exigiriam CDN ou passo de build, e o deploy final é num cPanel sem SSH.

O HTML que sai dele passa pelo **`app/Support/SanitizadorHtml.php`** antes de
ir para o banco. Sem essa limpeza, um ADM montaria o POST na mão com
`<script>` e ele executaria no navegador de quem abrisse a publicação,
inclusive no do Master. A lista é de permissão — `strip_tags()` não serviria,
porque preserva atributos e deixaria passar `<p onclick="...">`.

## HTTPS atrás de proxy

Railway, e qualquer PaaS, encerram o TLS na borda e entregam HTTP ao
container. Sem tratar isso, o Laravel gera links e assets em `http://` e o
navegador bloqueia como mixed content.

Resolvido em dois pontos: `trustProxies` no `bootstrap/app.php` (lê o
`X-Forwarded-Proto`) e `URL::forceScheme` no `AppServiceProvider` (cobre URL
gerada fora de uma requisição — fila, console, e-mail).

## Deploy no Railway — o que aprendemos apanhando

Está detalhado em [RAILWAY.md](RAILWAY.md). O resumo das três armadilhas:

**1. O `.gitignore` não vale se a pasta não for repositório git.** O
`railway up` subia a `vendor/` inteira (67 MB) e o builder morria aos 11
minutos **sem mensagem de erro no log**. O `.railwayignore` resolve, porque
funciona independente de git. O envio caiu para 952 KB e o build para 4 min.

**2. O Railpack diz "Running migrations and seeding database" mas só roda as
migrations.** A mensagem engana; o log mostra apenas as migrations.

**3. O `preDeployCommand` do `railway.json` é ignorado** — testado como lista
e como texto. O script de start do builder assume o controle.

Por isso as contas iniciais vivem numa **migration**
(`2026_01_03_000001_cria_contas_iniciais`) e não no seeder: migration o
Railway roda de verdade. Ela é idempotente — usa `firstOrCreate` e só cria
exemplos se não houver nenhum.

O `DatabaseSeeder` continua existindo para desenvolvimento local.

### Limitação atual

Não há serviço de banco nem volume no projeto: é **SQLite em disco efêmero**.
Cada deploy recria o banco. As contas e os exemplos voltam sempre, mas o que
for escrito no painel se perde. Para persistir, adicionar o plugin MySQL —
passo 2 do `RAILWAY.md`.

## Alertas por WhatsApp

O destinatário do alerta é sempre um **usuário do sistema**: não existe
cadastro de contato à parte. Quem recebe é toda conta **ativa**, com
**telefone** e que **autorizou** receber.

- **Telefone**: o ADM cadastra no formulário de usuário (ou a própria pessoa,
  em Minha conta). Vai para o banco só com dígitos e DDI (`5531999990001`),
  que é o formato da API da Meta — ver `app/Support/Telefone.php`.
- **Autorização**: só a própria pessoa dá, em **Minha conta**. Fica gravada a
  data (`alerta_consentimento_em`), que é o que comprova o opt-in exigido
  pela Meta. O campo não é `fillable`, então nenhum formulário do ADM
  consegue marcá-lo, nem editando o HTML.
- Se o ADM troca o número, a autorização cai: ela valia para o número antigo.
- No disparo, quem tem telefone mas não autorizou aparece no histórico como
  **ignorado**. O histórico guarda nome e telefone do momento do envio.

Na **Central de alertas**, o medidor mostra a taxa de entrega (entregues ×
falharam; ignorados ficam fora, porque nada foi tentado) e cada disparo com
falha ganha o botão **Reenviar**. O reenvio tenta de novo só quem falhou,
confere outra vez a autorização e conta as tentativas de cada destinatário.

O envio ainda é **simulado** (`SimulatedWhatsAppSender`). Para ver falhas e o
reenvio funcionando na homologação, defina `ALERTA_SIMULACAO_FALHA` com a
porcentagem de envios que devem falhar (ex.: `30`); o padrão é `0`.

Antes de ligar a API real da Meta:

1. **MySQL no Railway** — com SQLite, cada deploy apaga histórico e datas de
   autorização, justamente a prova do opt-in.
2. **Modelos** precisam virar templates aprovados pela Meta (variáveis
   `{{1}}`, `{{2}}`); texto livre não é aceito para iniciar conversa.
3. **Fila** para o envio: hoje ele acontece dentro da requisição.

## Onde está cada coisa

```
app/Models/            User, Postagem, Anexo, Alert* (modelo, disparo, destinatário)
app/Http/Controllers/  Auth, Painel, Postagem, Usuario, Alerta, Conta
app/Http/Middleware/   VerificaPerfil
app/Services/Alertas/  envio do alerta (hoje simulado)
app/Support/           SanitizadorHtml, Telefone
database/migrations/   tabelas + ajuste de tipos + contas e dados iniciais
database/seeders/      mesmo conteúdo, para uso local
resources/views/       login, painel, postagens, usuários, alertas, minha conta
public/assets/         style.css, app.js e editor.js
routes/web.php         rotas agrupadas por permissão
.railwayignore         o que não sobe no deploy
```

O `routes/web.php` é o melhor lugar para entender o sistema: as rotas estão
agrupadas justamente por quem pode acessá-las.

## O que ainda não existe

- **Envio real de alerta** — telas, autorização e histórico prontos; o envio
  em si ainda é simulado (ver *Alertas por WhatsApp*)
- **Upload de anexos** — a tabela `anexos` existe e a visualização já lista,
  mas o formulário ainda não envia arquivo
- **Área pública** — por ora tudo exige login

## Deploy no cPanel (produção)

O plano contratado **não tem SSH**, então:

1. `composer install --no-dev` roda local; a pasta `vendor/` sobe junto
2. As migrations rodam local; exporta o SQL e importa pelo phpMyAdmin
3. O app fica fora do `public_html`; só o conteúdo de `public/` vai dentro dele
4. Ajustar os dois `require` do `index.php` para apontar uma pasta acima
5. `storage/` e `bootstrap/cache/` precisam de permissão de escrita

Restrições do plano atual: **1 GB de disco** (240 MB já em uso) e **1 GB de
banda por mês**. A `vendor/` sozinha pesa ~67 MB.

Ensaiem esse deploy cedo, não na véspera da entrega.
