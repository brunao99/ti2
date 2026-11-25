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
        21654 => 'Esta conta só envia por modelo de mensagem: configure TWILIO_CONTENT_SID.',
    ];

    public function send(string $telefone, string $mensagem, array $parametros = [], ?string $imagem = null): array
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
                ->post($url, $this->corpo($cfg, $telefone, $mensagem, $parametros, $imagem));
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

    /**
     * Com modelo do Twilio configurado (TWILIO_CONTENT_SID), manda o modelo e
     * as variáveis {{1}} título, {{2}} tipo e {{3}} data; no modelo com
     * imagem (TWILIO_CONTENT_SID_IMAGEM), {{4}} é o caminho da imagem.
     * Sem modelo, manda o texto livre (e a imagem, se houver).
     */
    private function corpo(array $cfg, string $telefone, string $mensagem, array $parametros, ?string $imagem): array
    {
        $base = [
            'From' => $this->enderecoWhatsApp($cfg['whatsapp_from']),
            'To' => $this->enderecoWhatsApp($telefone),
        ];

        $comImagem = $imagem && filled($cfg['content_sid_imagem'] ?? null);
        $modelo = $comImagem ? $cfg['content_sid_imagem'] : ($cfg['content_sid'] ?? null);

        if (blank($modelo)) {
            // Texto livre; com imagem, o texto do alerta vira a legenda dela.
            return $base + array_filter(['Body' => $mensagem, 'MediaUrl' => $imagem]);
        }

        $variaveis = [];
        foreach (array_values($parametros) as $i => $valor) {
            $variaveis[(string) ($i + 1)] = trim(preg_replace('/\s+/u', ' ', (string) $valor));
        }
        if ($comImagem) {
            $variaveis['4'] = ltrim((string) parse_url($imagem, PHP_URL_PATH), '/');
        }

        return $base + [
            'ContentSid' => $modelo,
            'ContentVariables' => json_encode($variaveis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
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
