<?php

namespace App\Services\Alertas;

class SimulatedWhatsAppSender implements AlertSender
{
    public function send(string $telefone, string $mensagem): array
    {
        // Sprint atual: nenhum provedor externo é chamado.
        return [
            'status' => 'enviado',
            'resultado' => 'Envio simulado com sucesso.',
        ];
    }
}
