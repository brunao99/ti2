# Subir no Railway

## 1. Criar o projeto

No Railway: **New Project → Deploy from GitHub repo** (ou *Empty Project* e
subir os arquivos). Ele detecta PHP pelo `composer.json` e roda
`composer install` sozinho.

O `Procfile` deste repositório manda rodar as migrations e subir o servidor.

## 2. Adicionar o banco — este passo não é opcional

**New → Database → Add MySQL.**

Sem isso o sistema usa SQLite, que é um arquivo dentro do container. O disco
do Railway é efêmero: **a cada deploy o arquivo é recriado e todos os dados
somem.** Funciona para uma demonstração de cinco minutos e perde tudo depois.

## 3. Variáveis de ambiente

Em **Variables**, no serviço da aplicação:

```
APP_NAME=Algoritmos e Dados
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://SEU-PROJETO.up.railway.app

APP_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Aquele `${{MySQL.*}}` é a sintaxe do próprio Railway para referenciar o
serviço de banco — ele substitui pelos valores reais. Se o serviço tiver
outro nome, troque `MySQL` pelo nome que aparece na tela.

### O APP_KEY

Gere na sua máquina e cole no valor:

```bash
php artisan key:generate --show
```

Sai algo como `base64:xxxxx...`. Sem essa chave a sessão não funciona e o
login não passa.

### Por que APP_DEBUG=false

Com `true`, qualquer erro mostra stack trace, trecho de código e as variáveis
de ambiente na tela — incluindo a senha do banco — para quem estiver
acessando a URL.

### Por que APP_URL com https

O Railway encerra o TLS na borda e entrega HTTP ao container. Se o `APP_URL`
estiver em http, o Laravel gera todos os links e assets em http e o navegador
bloqueia como mixed content — a tela carrega sem CSS.

## 4. Criar os usuários

As migrations rodam sozinhas a cada deploy, mas elas só criam as tabelas.
Para ter com quem entrar, rode uma vez o seeder.

No Railway, aba do serviço → **Settings → Deploy → Custom Start Command**, ou
pelo terminal do Railway CLI:

```bash
php artisan db:seed --force
```

Isso cria os três perfis (`maria.souza` Master, `joao.lima` ADM, `novalima`
Client), todos com a senha `trocar-esta-senha`. **Troque antes de mostrar a
alguém.**

## 5. Agenda: envio automático dos alertas programados

Os alertas programados na Agenda saem sozinhos no horário marcado. Quem
confere a hora é o agendador do Laravel, pelo comando
`php artisan agenda:enviar-alertas`, registrado para rodar a cada minuto em
`routes/console.php`.

Para ele rodar, o servidor precisa manter o agendador ligado:

- **Railway:** em **Settings → Deploy → Custom Start Command**, suba o
  agendador junto com o site:

  ```bash
  php artisan migrate --force && (php artisan schedule:work &) && php artisan serve --host=0.0.0.0 --port=$PORT
  ```

- **cPanel:** em **Cron Jobs**, crie uma tarefa a cada minuto:

  ```bash
  * * * * * cd /home/USUARIO/caminho-do-painel && php artisan schedule:run >> /dev/null 2>&1
  ```

**Reserva:** mesmo sem o agendador, o painel confere os alertas atrasados no
máximo uma vez por minuto, depois de responder qualquer página aberta por um
administrador logado. Assim, um alerta não fica sem sair enquanto alguém usa
o painel; mas, sem ninguém no painel e sem o agendador, ele só sai quando
alguém entrar. Um envio que passou mais de 24 horas do horário sem sair
aparece como "Falhou" na agenda e não é mais enviado.

Os horários da agenda são sempre do horário de Brasília
(`America/Sao_Paulo`), seja qual for o fuso do servidor.

O calendário de feriados já vem com os feriados nacionais de 2026, os de
Belo Horizonte e os pontos facultativos de costume (migration
`2026_11_11_000002_cria_feriados_iniciais`). As datas móveis (Carnaval,
Paixão de Cristo, Corpus Christi) valem só para 2026: use o botão
"Copiar para 2027" na tela e confira os dias.

## Limitação conhecida deste setup

O `Procfile` usa `php artisan serve`, que é o servidor embutido do PHP:
atende **uma requisição por vez**. Para homologação e demonstração está de
bom tamanho. Para produção de verdade o certo é nginx + php-fpm, que é o que
o cPanel já oferece — e produção vai para o cPanel, não para o Railway.

## Se der errado

| Sintoma | Causa provável |
|---|---|
| Página sem CSS nenhum | `APP_URL` em http, ou faltando |
| "No application encrypt key" | `APP_KEY` vazio |
| Erro de conexão com banco | as variáveis `DB_*` não apontam para o serviço MySQL |
| Login não passa, volta pro formulário | `SESSION_SECURE_COOKIE=true` sem HTTPS, ou banco sem a tabela `sessions` |
| Dados somem a cada deploy | ainda está em SQLite; falta o passo 2 |
| Alerta programado só sai quando alguém abre o painel | o agendador não está ligado; ver o passo 5 |

Os logs ficam em **Deployments → View Logs**, e é lá que o erro real aparece.
