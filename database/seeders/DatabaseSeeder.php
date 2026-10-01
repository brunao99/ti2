<?php

namespace Database\Seeders;

use App\Models\Postagem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Roda a cada deploy, junto com as migrations.
 *
 * Por isso é idempotente: usa firstOrCreate para os usuários e só cria as
 * publicações de exemplo quando a tabela está vazia. Assim um segundo deploy
 * não quebra por chave duplicada nem duplica o conteúdo — e o que alguém
 * tiver escrito no painel continua lá.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Credenciais de homologação, combinadas com o grupo.
         * São de teste e propositalmente simples — trocar antes de
         * qualquer uso real, e nunca reaproveitar em produção.
         */
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
                    'password' => '12345678',
                    'perfil'   => $perfil,
                    'ativo'    => true,
                ]
            );

            if ($perfil === User::MASTER) {
                $master = $conta;
            }
        }

        // Conteúdo de exemplo entra uma vez só. Se já existe publicação — de
        // outro deploy ou escrita por alguém no painel — não mexemos em nada.
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
}
