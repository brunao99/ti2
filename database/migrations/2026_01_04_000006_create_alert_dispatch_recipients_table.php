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
            $table->foreignId('alert_contact_id')->nullable()->constrained('alert_contacts')->nullOnDelete();
            $table->string('nome');
            $table->string('telefone', 30);
            $table->enum('status', ['enviado', 'falha', 'ignorado']);
            $table->string('resultado')->nullable();
            $table->timestamps();

            $table->index(['alert_dispatch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatch_recipients');
    }
};
