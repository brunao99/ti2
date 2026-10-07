<?php

use App\Models\AlertTemplate;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AlertTemplate::firstOrCreate(
            ['nome' => 'Novo conteúdo publicado'],
            ['mensagem' => 'Olá! Foi publicada uma nova informação no Portal Algoritmos e Dados: {{titulo}} ({{tipo}}), publicada em {{data_publicacao}}.']
        );

        // Dados de homologação, para o envio simulado ter os dois casos: o
        // cliente autorizou e recebe; o ADM tem telefone mas não autorizou,
        // então aparece como "ignorado" no histórico.
        //
        // Em uso real o consentimento só nasce na tela Minha conta, marcado
        // pela própria pessoa. Aqui é preenchido direto porque é conta de teste.
        // forceFill porque o consentimento, de propósito, não é fillable.
        $testes = [
            'usuariocliente' => ['5531999990001', now()],
            'usuarioadm'     => ['5531999990002', null],
        ];

        foreach ($testes as $usuario => [$telefone, $consentimento]) {
            $conta = User::where('usuario', $usuario)->whereNull('telefone')->first();

            $conta?->forceFill([
                'telefone'                => $telefone,
                'alerta_consentimento_em' => $consentimento,
            ])->save();
        }
    }

    public function down(): void
    {
        // Dados de teste podem ser alterados pelo painel; não remover no rollback.
    }
};
