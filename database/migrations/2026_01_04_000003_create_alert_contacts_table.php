<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('telefone', 30);
            $table->boolean('consentimento')->default(false);
            $table->timestamps();

            $table->index('consentimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_contacts');
    }
};
