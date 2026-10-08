<?php

namespace App\Services\Alertas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio pela WhatsApp Cloud API (Meta), sempre por template aprovado: é o
 * único tipo de mensagem que a Meta deixa iniciar fora de uma conversa.
 *
 * "enviado" aqui quer dizer que a Meta aceitou a mensagem e devolveu o wamid.
 * Se ela chegou ou falhou no caminho, quem conta é o webhook
 * (WhatsAppWebhookController), que encontra o destinatário por esse wamid.
 */
class MetaWhatsAppSender implements AlertSender
{
    /** Códigos mais comuns, em português, para o histórico. */
    private const ERROS_CONHECIDOS = [
        190    => 'Token de acesso inválido ou vencido.',
        130497 => 'A conta não pode enviar mensagens para usuários deste país.',
        131026 => 'Mensagem não entregue: o número pode não ter WhatsApp ou não aceitou os termos atuais.',
        131030 => 'Número não está na lista de destinatários permitidos do número de teste.',
        131047 => 'Fora da janela de 24 horas: só template pode ser enviado.',
        131056 => 'Muitas mensagens seguidas para o mesmo número; tente de novo em instantes.',
        132001 => 'Template inexistente nesse idioma ou ainda não aprovado.',
        132015 => 'Template pausado pela Meta.',
        132016 => 'Template desativado pela Meta.',
    ];

    public function send(string $telefone, string $mensagem, array $parametros = []): array
    {
        $cfg = config('services.whatsapp');

        $faltando = array_keys(array_filter([
            'WHATSAPP_ACCESS_TOKEN'    => blank($cfg['access_token'] ?? null),
            'WHATSAPP_PHONE_NUMBER_ID' => blank($cfg['phone_number_id'] ?? null),
            'WHATSAPP_TEMPLATE'        => blank($cfg['template'] ?? null),
        ]));

        if ($faltando) {
            return $this->falha(null, 'WhatsApp não configurado: falta ' . implode(', ', $faltando) . '.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $cfg['graph_version'] ?: 'v23.0',
            $cfg['phone_number_id']
        );

        try {
            // Sem retry automático: se a Meta recebeu e só a resposta se
            // perdeu, tentar de novo mandaria a mensagem duas vezes. O
            // reenvio fica a cargo do botão na Central de alertas.
            $resposta = Http::withToken($cfg['access_token'])
                ->acceptJson()
                ->timeout(15)
                ->post($url, $this->corpo($telefone, $cfg, $parametros));
        } catch (ConnectionException $e) {
            Log::warning('WhatsApp: sem conexão com a Meta', ['erro' => $e->getMessage()]);

            return $this->falha(null, 'Sem conexão com a Meta. Tente reenviar.');
        }

        $wamid = $resposta->json('messages.0.id');

        if ($resposta->successful() && $wamid) {
            return [
                'status' => 'enviado',
                'resultado' => 'Aceito pela Meta, aguardando entrega.',
                'wamid' => $wamid,
                'erro_codigo' => null,
                'erro_mensagem' => null,
            ];
        }

        $erro = (array) $resposta->json('error', []);
        $codigo = isset($erro['code']) ? (int) $erro['code'] : $resposta->status();
        $detalhe = data_get($erro, 'error_data.details') ?: ($erro['message'] ?? 'Resposta inesperada da Meta.');

        // Nunca registrar o token: só código, mensagem e o rastreio da Meta.
        Log::warning('WhatsApp: envio recusado', [
            'http' => $resposta->status(),
            'codigo' => $codigo,
            'mensagem' => $detalhe,
            'fbtrace_id' => $erro['fbtrace_id'] ?? null,
        ]);

        return $this->falha($codigo, $detalhe);
    }

    public static function descreverErro(?int $codigo, ?string $mensagem): string
    {
        $texto = self::ERROS_CONHECIDOS[$codigo] ?? $mensagem ?? 'Erro desconhecido.';
        $texto = $codigo ? "Recusado pela Meta ({$codigo}): {$texto}" : $texto;

        // A coluna "resultado" é string(255); a mensagem inteira vai para erro_mensagem.
        return mb_strimwidth($texto, 0, 255, '…');
    }

    private function corpo(string $telefone, array $cfg, array $parametros): array
    {
        $template = [
            'name' => $cfg['template'],
            'language' => ['code' => $cfg['template_lang'] ?: 'pt_BR'],
        ];

        // A Meta recusa parâmetro com quebra de linha, tab ou espaços em
        // sequência; um título colado de outro lugar traz isso fácil.
        if ($parametros) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn ($valor) => ['type' => 'text', 'text' => trim(preg_replace('/\s+/u', ' ', (string) $valor))],
                    array_values($parametros)
                ),
            ]];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefone,
            'type' => 'template',
            'template' => $template,
        ];
    }

    private function falha(?int $codigo, string $mensagem): array
    {
        return [
            'status' => 'falha',
            'resultado' => self::descreverErro($codigo, $mensagem),
            'erro_codigo' => $codigo,
            'erro_mensagem' => $mensagem,
        ];
    }
}
