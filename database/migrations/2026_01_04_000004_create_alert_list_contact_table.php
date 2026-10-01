<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_list_contact', function (Blueprint $table) {
            $table->foreignId('alert_list_id')->constrained('alert_lists')->cascadeOnDelete();
            $table->foreignId('alert_contact_id')->constrained('alert_contacts')->cascadeOnDelete();
            $table->primary(['alert_list_id', 'alert_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_list_contact');
    }
};
