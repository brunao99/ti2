<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Os três tipos iniciais (edital, programa, comunicado) eram suposição minha.
 * Os tipos reais são as dez seções do menu INFORMAÇÕES do portal em produção.
 *
 * A coluna deixa de ser enum e vira string: essa lista acompanha o portal e
 * vai mudar, e cada ajuste num enum de MySQL custaria outra migration.
 * A validação passa a viver em Postagem::TIPOS.
 */
return new class extends Migration
{
    /** Para onde vai cada valor antigo. */
    private const DE_PARA = [
        'edital'     => 'ministerios_editais',
        'programa'   => 'ministerios_programas',
        'comunicado' => 'informes_cnm_amm',
    ];

    public function up(): void
    {
        // Primeiro afrouxa o tipo da coluna: enquanto for enum, os valores
        // novos são rejeitados pela restrição.
        Schema::table('postagens', function (Blueprint $table) {
            $table->string('tipo', 40)->change();
        });

        foreach (self::DE_PARA as $antigo => $novo) {
            DB::table('postagens')->where('tipo', $antigo)->update(['tipo' => $novo]);
        }
    }

    public function down(): void
    {
        foreach (self::DE_PARA as $antigo => $novo) {
            DB::table('postagens')->where('tipo', $novo)->update(['tipo' => $antigo]);
        }

        // Qualquer tipo criado depois da mudança não tem equivalente antigo;
        // vira 'comunicado' para não quebrar a restrição do enum.
        DB::table('postagens')
            ->whereNotIn('tipo', array_keys(self::DE_PARA))
            ->update(['tipo' => 'comunicado']);

        Schema::table('postagens', function (Blueprint $table) {
            $table->enum('tipo', ['edital', 'programa', 'comunicado'])->change();
        });
    }
};
