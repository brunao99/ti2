<?php

namespace App\Services\Alertas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio pelo WhatsApp do Twilio, pensado para demonstração com o sandbox.
 *
 * No sandbox, só recebe quem entrou nele mandando "join <código>" para o
 * número do Twilio, e a mensagem livre (o texto já montado do modelo do
 * painel) só é entregue até 24 h depois da última mensagem que a pessoa
 * mandou para o sandbox. Fora disso o Twilio aceita o pedido e a falha
 * chega depois, sem passar por aqui.
 *
 * "enviado" quer dizer que o Twilio aceitou a mensagem e devolveu o SID,
 * guardado no mesmo campo de rastreio usado pela Meta.
 */
class TwilioWhatsAppSender implements AlertSender
{
    /** Códigos mais comuns, em português, para o histórico. */
    private const ERROS_CONHECIDOS = [
        20003 => 'Credenciais do Twilio inválidas (Account SID ou Auth Token).',
        20429 => 'Muitas mensagens seguidas; tente de novo em instantes.',
        21211 => 'Número de destino inválido.',
        63007 => 'O número de origem não é um canal de WhatsApp do Twilio.',
        63015 => 'Este número ainda não entrou no sandbox do Twilio (mande "join <código>" para o número do sandbox).',
        63016 => 'Fora da janela de 24 horas: mande uma mensagem para o sandbox e tente de novo.',
    ];

    public function send(string $telefone, string $mensagem, array $parametros = []): array
    {
        $cfg = config('services.twilio');
        $faltando = array_keys(array_filter([
            'TWILIO_ACCOUNT_SID' => blank($cfg['account_sid'] ?? null),
            'TWILIO_AUTH_TOKEN' => blank($cfg['auth_token'] ?? null),
            'TWILIO_WHATSAPP_FROM' => blank($cfg['whatsapp_from'] ?? null),
        ]));

        if ($faltando) {
            return $this->falha(null, 'WhatsApp (Twilio) não configurado: falta ' . implode(', ', $faltando) . '.');
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $cfg['account_sid']);

        try {
            // Sem retry automático, pelo mesmo motivo do envio pela Meta:
            // repetir poderia mandar a mensagem duas vezes.
            $resposta = Http::withBasicAuth($cfg['account_sid'], $cfg['auth_token'])
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'From' => $this->enderecoWhatsApp($cfg['whatsapp_from']),
                    'To' => $this->enderecoWhatsApp($telefone),
                    'Body' => $mensagem,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('WhatsApp (Twilio): sem conexão', ['erro' => $e->getMessage()]);

            return $this->falha(null, 'Sem conexão com o Twilio. Tente reenviar.');
        }

        $sid = $resposta->json('sid');

        if ($resposta->successful() && $sid) {
            return [
                'status' => 'enviado',
                'resultado' => 'Aceito pelo Twilio, aguardando entrega.',
                'wamid' => $sid,
                'erro_codigo' => null,
                'erro_mensagem' => null,
            ];
        }

        $codigo = $resposta->json('code');
        $codigo = is_numeric($codigo) ? (int) $codigo : $resposta->status();
        $detalhe = $resposta->json('message') ?: 'Resposta inesperada do Twilio.';

        // Nunca registrar as credenciais: só código e mensagem.
        Log::warning('WhatsApp (Twilio): envio recusado', [
            'http' => $resposta->status(),
            'codigo' => $codigo,
            'mensagem' => $detalhe,
        ]);

        return $this->falha($codigo, $detalhe);
    }

    /** "5531999990001" ou "+5531..." vira "whatsapp:+5531...". */
    private function enderecoWhatsApp(string $numero): string
    {
        if (str_starts_with($numero, 'whatsapp:')) {
            return $numero;
        }

        return 'whatsapp:+' . preg_replace('/\D/', '', $numero);
    }

    private function falha(?int $codigo, string $mensagem): array
    {
        $texto = self::ERROS_CONHECIDOS[$codigo] ?? $mensagem;
        $texto = $codigo ? "Recusado pelo Twilio ({$codigo}): {$texto}" : $texto;

        return [
            'status' => 'falha',
            // A coluna "resultado" é string(255); a mensagem inteira vai para erro_mensagem.
            'resultado' => mb_strimwidth($texto, 0, 255, '…'),
            'erro_codigo' => $codigo,
            'erro_mensagem' => $mensagem,
        ];
    }
}
