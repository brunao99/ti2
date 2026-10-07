<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_dispatch_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_dispatch_id')->constrained('alert_dispatches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Cópia do momento do envio: se o usuário trocar de número ou for
            // excluído, o histórico continua mostrando para onde foi.
            $table->string('nome');
            $table->string('telefone', 30);
            $table->enum('status', ['enviado', 'falha', 'ignorado']);
            $table->string('resultado')->nullable();
            // Quantas vezes o envio foi tentado: 1 no disparo, +1 a cada reenvio.
            $table->unsignedSmallInteger('tentativas')->default(1);
            $table->timestamps();

            $table->index(['alert_dispatch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatch_recipients');
    }
};
