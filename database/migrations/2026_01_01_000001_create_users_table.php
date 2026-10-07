<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('usuario')->unique();      // login, como na tela
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('perfil', ['master', 'adm', 'client'])->default('client');
            $table->boolean('ativo')->default(true);  // conta inativa = Tela 03

            // WhatsApp dos alertas, só dígitos com DDI (5531999990001) — o
            // formato que a API da Meta exige. Ver App\Support\Telefone.
            $table->string('telefone', 20)->nullable();

            // Data em que a própria pessoa autorizou receber alertas, na tela
            // Minha conta. Nulo = não recebe. Fica a data, não um sim/não,
            // porque é ela que comprova o opt-in que a Meta exige.
            $table->timestamp('alerta_consentimento_em')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
