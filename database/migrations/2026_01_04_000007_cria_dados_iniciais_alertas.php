<?php

use App\Models\AlertContact;
use App\Models\AlertList;
use App\Models\AlertTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $modelo = AlertTemplate::firstOrCreate(
            ['nome' => 'Novo conteúdo publicado'],
            ['mensagem' => 'Olá! Foi publicada uma nova informação no Portal Algoritmos e Dados: {{titulo}} ({{tipo}}), publicada em {{data_publicacao}}.']
        );

        $lista = AlertList::firstOrCreate(
            ['nome' => 'Lista de teste'],
            ['descricao' => 'Contatos para validação do envio simulado.']
        );

        $contatos = [
            ['nome' => 'Prefeitura de Exemplo', 'telefone' => '+55 31 99999-0001', 'consentimento' => true],
            ['nome' => 'Prefeitura sem consentimento', 'telefone' => '+55 31 99999-0002', 'consentimento' => false],
        ];

        foreach ($contatos as $dados) {
            $contato = AlertContact::firstOrCreate(
                ['telefone' => $dados['telefone']],
                $dados
            );

            $lista->contatos()->syncWithoutDetaching([$contato->id]);
        }
    }

    public function down(): void
    {
        // Dados de teste podem ser alterados pelo painel; não remover no rollback.
    }
};
