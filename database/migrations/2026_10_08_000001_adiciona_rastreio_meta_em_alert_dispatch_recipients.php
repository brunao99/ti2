<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastreio de cada mensagem na WhatsApp Cloud API.
 *
 * wamid é o id que a Meta devolve no envio; os eventos do webhook chegam com
 * ele, por isso o índice. status_meta guarda o último status informado
 * (sent, delivered, read, failed) e os campos de erro, o motivo da recusa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_dispatch_recipients', function (Blueprint $table) {
            $table->string('wamid', 191)->nullable()->index();
            $table->string('status_meta', 20)->nullable();
            $table->unsignedInteger('erro_codigo')->nullable();
            $table->text('erro_mensagem')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('alert_dispatch_recipients', function (Blueprint $table) {
            $table->dropIndex(['wamid']);
            $table->dropColumn(['wamid', 'status_meta', 'erro_codigo', 'erro_mensagem']);
        });
    }
};
