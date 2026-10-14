<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link da fonte da publicação (Instagram, gov.br, CNM…). Vai para a coluna
 * LINK do relatório da prefeitura. Opcional: publicações antigas não têm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postagens', function (Blueprint $table) {
            $table->string('link', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('postagens', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
