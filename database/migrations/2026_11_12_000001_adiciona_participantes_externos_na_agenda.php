<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participantes do compromisso que não têm cadastro no painel (nome ou
 * e-mail digitado livremente). Os administradores continuam na tabela
 * agenda_evento_participantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_eventos', function (Blueprint $table) {
            $table->json('participantes_externos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_eventos', function (Blueprint $table) {
            $table->dropColumn('participantes_externos');
        });
    }
};
