<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postagem_id')->constrained('postagens');
            $table->foreignId('alert_template_id')->constrained('alert_templates');
            $table->foreignId('user_id')->constrained('users');
            $table->text('mensagem');
            $table->enum('status', ['sucesso', 'parcial', 'falha']);
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_falhas')->default(0);
            $table->timestamps();

            $table->index(['postagem_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatches');
    }
};
