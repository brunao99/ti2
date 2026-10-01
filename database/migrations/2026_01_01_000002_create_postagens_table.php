<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postagens', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['edital', 'programa', 'comunicado']);
            $table->string('titulo');
            $table->longText('texto')->nullable();
            $table->date('data_publicacao');
            $table->string('mes_referencia', 7);               // "2026-09", usado no agrupamento
            $table->enum('situacao', ['rascunho', 'publicado'])->default('rascunho');
            $table->boolean('alerta_enviado')->default(false);
            $table->foreignId('user_id')->constrained();       // quem criou
            $table->timestamps();

            $table->index(['mes_referencia', 'data_publicacao']);
            $table->index('tipo');
        });

        Schema::create('anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postagem_id')->constrained('postagens')->cascadeOnDelete();
            $table->string('nome_original');
            $table->string('caminho');
            $table->unsignedInteger('tamanho');                // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anexos');
        Schema::dropIfExists('postagens');
    }
};
