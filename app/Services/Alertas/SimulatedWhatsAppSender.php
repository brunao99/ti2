<?php

namespace App\Services\Alertas;

/**
 * Envio simulado: nenhum provedor externo é chamado.
 *
 * Por padrão tudo "chega". Para demonstrar falha e reenvio na homologação,
 * ALERTA_SIMULACAO_FALHA define a porcentagem de envios que falham (0 a 100).
 */
class SimulatedWhatsAppSender implements AlertSender
{
    public function send(string $telefone, string $mensagem, array $parametros = []): array
    {
        $chanceDeFalha = (int) config('services.alertas.simulacao_falha', 0);

        if ($chanceDeFalha > 0 && random_int(1, 100) <= $chanceDeFalha) {
            return [
                'status' => 'falha',
                'resultado' => 'Falha simulada de envio.',
            ];
        }

        return [
            'status' => 'enviado',
            'resultado' => 'Envio simulado com sucesso.',
        ];
    }
}
