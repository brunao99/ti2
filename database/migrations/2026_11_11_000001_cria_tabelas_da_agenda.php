<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda dos administradores.
 *
 * feriados            → calendário de feriados e pontos facultativos
 * agenda_eventos      → compromisso, alerta programado, lembrete e data importante
 * agenda_ocorrencias  → o que aconteceu com UMA data de um evento (cancelada,
 *                       alerta enviado ou com falha). As datas em si não são
 *                       gravadas: saem da regra de repetição do evento.
 *
 * Data e hora ficam como o administrador digitou, no horário de Brasília
 * (App\Models\AgendaEvento::FUSO), sem conversão para UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->string('descricao', 150);
            $table->date('data');
            $table->string('tipo', 20);          // feriado | ponto_facultativo
            $table->string('abrangencia', 20);   // nacional | estadual | municipal
            $table->string('local', 120)->nullable();      // estado ou município
            $table->string('expediente', 150)->nullable();
            $table->boolean('repete_todo_ano')->default(true);
            $table->timestamps();

            $table->index('data');
        });

        Schema::create('agenda_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);          // compromisso | alerta | lembrete | data_importante
            $table->string('titulo', 200)->nullable();     // o alerta usa o título da publicação
            $table->date('data');                // primeira ocorrência
            $table->string('hora_inicio', 5)->nullable();  // HH:MM
            $table->string('hora_fim', 5)->nullable();
            $table->boolean('dia_inteiro')->default(false);

            $table->string('repetir', 12)->default('nao'); // nao | semanal | quinzenal | mensal | bimestral | trimestral
            $table->string('termina', 8)->default('nunca'); // nunca | data | vezes
            $table->date('termina_em')->nullable();
            $table->unsignedSmallInteger('termina_vezes')->nullable();

            // compromisso
            $table->string('local', 500)->nullable();
            $table->unsignedSmallInteger('lembrar_antes')->nullable(); // minutos
            $table->foreignId('cliente_id')->nullable()->constrained('users')->nullOnDelete();

            // lembrete e data importante
            $table->string('visibilidade', 8)->default('equipe'); // eu | equipe
            $table->text('observacao')->nullable();
            $table->string('categoria', 12)->nullable();          // prazo | evento | outro
            $table->unsignedTinyInteger('avisar_antes')->nullable(); // dias

            // alerta programado (e publicação relacionada da data importante)
            $table->foreignId('postagem_id')->nullable()->constrained('postagens')->nullOnDelete();
            $table->foreignId('alert_template_id')->nullable()->constrained('alert_templates')->nullOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // quem criou
            $table->timestamps();

            $table->index(['tipo', 'data']);
        });

        Schema::create('agenda_evento_participantes', function (Blueprint $table) {
            $table->foreignId('agenda_evento_id')->constrained('agenda_eventos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['agenda_evento_id', 'user_id']);
        });

        Schema::create('agenda_ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_evento_id')->constrained('agenda_eventos')->cascadeOnDelete();
            $table->date('data');                 // a data da regra, antes de qualquer ajuste de exibição
            $table->string('situacao', 10);       // cancelado | enviando | enviado | falhou
            $table->foreignId('alert_dispatch_id')->nullable()->constrained('alert_dispatches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quem cancelou ou enviou na hora
            $table->string('detalhe', 255)->nullable();
            $table->timestamps();

            // Uma linha por data: é o que impede dois envios do mesmo alerta.
            $table->unique(['agenda_evento_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_ocorrencias');
        Schema::dropIfExists('agenda_evento_participantes');
        Schema::dropIfExists('agenda_eventos');
        Schema::dropIfExists('feriados');
    }
};
