<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto que vai no centro da imagem do alerta de WhatsApp.
 *
 * Vazio (null) quer dizer "usar o padrão": o começo do texto da publicação,
 * calculado na hora do envio. Assim, quem não mexe no campo sempre recebe o
 * resumo do texto mais recente, mesmo depois de editar a publicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postagens', function (Blueprint $table) {
            $table->text('resumo_alerta')->nullable()->after('texto');
        });
    }

    public function down(): void
    {
        Schema::table('postagens', function (Blueprint $table) {
            $table->dropColumn('resumo_alerta');
        });
    }
};
