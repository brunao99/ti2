<?php

use App\Models\Postagem;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Cria as contas de acesso e o conteúdo de exemplo.
 *
 * Isto normalmente seria trabalho do seeder, e no ambiente local é. Mas o
 * Railway não executa seeder: o Railpack roda apenas `php artisan migrate`
 * no start (a mensagem dele diz "migrations and seeding", o que engana — o
 * log mostra só as migrations), e o preDeployCommand do railway.json é
 * ignorado porque o script de start do builder assume o controle.
 *
 * Como o banco é SQLite em disco efêmero, todo deploy nasce vazio. Sem estas
 * contas ninguém consegue entrar. Colocá-las numa migration é o que garante
 * que rodem, porque migration o Railway roda de verdade.
 *
 * É idempotente: usa firstOrCreate e só cria exemplos se não houver nenhum,
 * então rodar de novo não duplica nem apaga o que foi escrito no painel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $contas = [
            ['usuariomaster',  'Usuário Master',        'master@algoritmosedados.com.br',  User::MASTER],
            ['usuarioadm',     'Usuário Administrador', 'adm@algoritmosedados.com.br',     User::ADM],
            ['usuariocliente', 'Usuário Cliente',       'cliente@algoritmosedados.com.br', User::CLIENT],
        ];

        $master = null;

        foreach ($contas as [$usuario, $nome, $email, $perfil]) {
            $conta = User::firstOrCreate(
                ['usuario' => $usuario],
                [
                    'nome'     => $nome,
                    'email'    => $email,
                    'password' => '12345678',   // homologação; trocar antes de uso real
                    'perfil'   => $perfil,
                    'ativo'    => true,
                ]
            );

            if ($perfil === User::MASTER) {
                $master = $conta;
            }
        }

        if (Postagem::exists()) {
            return;
        }

        $exemplos = [
            ['ministerios_editais',   'Edital de chamamento público 012/2026',     '2026-09-14', 'publicado', true],
            ['informes_cnm_amm',      'Informe CNM sobre repasses do FPM',         '2026-09-11', 'publicado', false],
            ['ministerios_programas', 'Programa Minha Casa Melhor — inscrições',   '2026-09-09', 'rascunho',  false],
            ['financiamentos',        'Linha de financiamento para saneamento',    '2026-08-28', 'publicado', true],
            ['seminarios_eventos',    'Seminário de gestão pública municipal',     '2026-08-19', 'publicado', true],
            ['estado_programas',      'Governo do estado abre adesão ao Caminhos', '2026-08-05', 'publicado', false],
            ['indices_indicadores',   'Índice de desenvolvimento municipal 2026',  '2026-07-22', 'publicado', false],
        ];

        foreach ($exemplos as [$tipo, $titulo, $data, $situacao, $alerta]) {
            Postagem::create([
                'tipo'            => $tipo,
                'titulo'          => $titulo,
                'texto'           => '<p>Conteúdo de exemplo da publicação <strong>'
                                   . e($titulo) . '</strong>.</p>'
                                   . '<p>Substitua por texto real ao popular o sistema.</p>',
                'data_publicacao' => $data,
                'mes_referencia'  => substr($data, 0, 7),
                'situacao'        => $situacao,
                'alerta_enviado'  => $alerta,
                'user_id'         => $master->id,
            ]);
        }
    }

    public function down(): void
    {
        // Não remove nada: a essa altura pode haver conteúdo real escrito no
        // painel, e apagar usuários levaria as publicações junto pela FK.
    }
};
