<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial do calendário de feriados: nacionais, os de Belo Horizonte
 * (sede da Algoritmos e Dados) e os pontos facultativos de costume.
 *
 * As datas fixas repetem todo ano. As móveis (Carnaval, Paixão de Cristo,
 * Corpus Christi) valem só para 2026 e precisam ser revistas a cada ano —
 * a tela tem o botão "Copiar para o próximo ano" para isso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('feriados')->exists()) {
            return;
        }

        $bh = 'Belo Horizonte/MG';
        // [data, descrição, tipo, abrangência, local, expediente, repete]
        $datas = [
            ['2026-01-01', 'Confraternização Universal', 'feriado', 'nacional', null, null, true],
            ['2026-02-16', 'Carnaval', 'ponto_facultativo', 'nacional', null, null, false],
            ['2026-02-17', 'Carnaval', 'ponto_facultativo', 'nacional', null, null, false],
            ['2026-02-18', 'Quarta-feira de Cinzas', 'ponto_facultativo', 'nacional', null, 'Expediente a partir das 14h', false],
            ['2026-04-03', 'Paixão de Cristo', 'feriado', 'nacional', null, null, false],
            ['2026-04-21', 'Tiradentes', 'feriado', 'nacional', null, 'Também data magna de Minas Gerais', true],
            ['2026-05-01', 'Dia do Trabalho', 'feriado', 'nacional', null, null, true],
            ['2026-06-04', 'Corpus Christi', 'feriado', 'municipal', $bh, null, false],
            ['2026-08-15', 'Assunção de Nossa Senhora', 'feriado', 'municipal', $bh, null, true],
            ['2026-09-07', 'Independência do Brasil', 'feriado', 'nacional', null, null, true],
            ['2026-10-12', 'Nossa Senhora Aparecida', 'feriado', 'nacional', null, null, true],
            ['2026-10-28', 'Dia do Servidor Público', 'ponto_facultativo', 'nacional', null, null, true],
            ['2026-11-02', 'Finados', 'feriado', 'nacional', null, null, true],
            ['2026-11-15', 'Proclamação da República', 'feriado', 'nacional', null, null, true],
            ['2026-11-20', 'Dia Nacional de Zumbi e da Consciência Negra', 'feriado', 'nacional', null, null, true],
            ['2026-12-08', 'Imaculada Conceição', 'feriado', 'municipal', $bh, null, true],
            ['2026-12-24', 'Véspera de Natal', 'ponto_facultativo', 'nacional', null, 'Expediente até as 14h', true],
            ['2026-12-25', 'Natal', 'feriado', 'nacional', null, null, true],
            ['2026-12-31', 'Véspera de Ano Novo', 'ponto_facultativo', 'nacional', null, 'Expediente até as 14h', true],
        ];

        $agora = now();

        DB::table('feriados')->insert(array_map(fn (array $d) => [
            'data' => $d[0],
            'descricao' => $d[1],
            'tipo' => $d[2],
            'abrangencia' => $d[3],
            'local' => $d[4],
            'expediente' => $d[5],
            'repete_todo_ano' => $d[6],
            'created_at' => $agora,
            'updated_at' => $agora,
        ], $datas));
    }

    public function down(): void
    {
        // Sem volta: depois de criados, os feriados passam a ser dados do painel.
    }
};
