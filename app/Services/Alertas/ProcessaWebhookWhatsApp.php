<?php

namespace App\Services\Alertas;

use App\Models\AlertDispatchRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa o payload do webhook da Meta (entry[].changes[].value).
 *
 * statuses: atualiza o destinatário pelo wamid. "failed" vira falha no
 *           histórico (com código e motivo) e o disparo é recontado, então o
 *           botão Reenviar aparece na Central de alertas.
 * messages: por enquanto só vão para o log.
 */
class ProcessaWebhookWhatsApp
{
    /** Status de sucesso em ordem; a Meta pode mandar fora de ordem. */
    private const ORDEM = ['sent' => 1, 'delivered' => 2, 'read' => 3];

    private const RESULTADO = [
        'sent' => 'Enviado pela Meta.',
        'delivered' => 'Entregue no WhatsApp.',
        'read' => 'Lido.',
    ];

    public function processar(array $payload): void
    {
        foreach ((array) ($payload['entry'] ?? []) as $entrada) {
            foreach ((array) ($entrada['changes'] ?? []) as $mudanca) {
                $valor = (array) ($mudanca['value'] ?? []);

                foreach ((array) ($valor['statuses'] ?? []) as $status) {
                    $this->status((array) $status);
                }

                foreach ((array) ($valor['messages'] ?? []) as $mensagem) {
                    $this->mensagem((array) $mensagem);
                }
            }
        }
    }

    private function status(array $evento): void
    {
        $wamid = $evento['id'] ?? null;
        $novo = $evento['status'] ?? null;

        if (! $wamid || ! $novo) {
            return;
        }

        DB::transaction(function () use ($wamid, $novo, $evento) {
            $destinatario = AlertDispatchRecipient::where('wamid', $wamid)->lockForUpdate()->first();

            if (! $destinatario) {
                // Mensagem de outro envio (ex.: teste pelo painel da Meta) ou
                // de uma tentativa já substituída por um reenvio.
                Log::info('WhatsApp webhook: status de wamid desconhecido', ['wamid' => $wamid, 'status' => $novo]);

                return;
            }

            if ($novo === 'failed') {
                $erro = (array) ($evento['errors'][0] ?? []);
                $codigo = isset($erro['code']) ? (int) $erro['code'] : null;
                $motivo = data_get($erro, 'error_data.details') ?: ($erro['message'] ?? $erro['title'] ?? null);

                $destinatario->update([
                    'status' => 'falha',
                    'status_meta' => 'failed',
                    'erro_codigo' => $codigo,
                    'erro_mensagem' => $motivo,
                    'resultado' => MetaWhatsAppSender::descreverErro($codigo, $motivo),
                ]);

                $destinatario->disparo->recalcularTotais();

                Log::warning('WhatsApp webhook: entrega falhou', ['wamid' => $wamid, 'codigo' => $codigo, 'motivo' => $motivo]);

                return;
            }

            if (! isset(self::ORDEM[$novo])) {
                Log::info('WhatsApp webhook: status não tratado', ['wamid' => $wamid, 'status' => $novo]);

                return;
            }

            // "failed" é final; e um "delivered" atrasado não desfaz um "read".
            $atual = $destinatario->status_meta;
            if ($atual === 'failed' || (self::ORDEM[$atual] ?? 0) >= self::ORDEM[$novo]) {
                return;
            }

            $destinatario->update([
                'status_meta' => $novo,
                'resultado' => self::RESULTADO[$novo],
            ]);
        });
    }

    private function mensagem(array $mensagem): void
    {
        Log::info('WhatsApp webhook: mensagem recebida', [
            'id' => $mensagem['id'] ?? null,
            'de' => $mensagem['from'] ?? null,
            'tipo' => $mensagem['type'] ?? null,
            'texto' => isset($mensagem['text']['body']) ? mb_strimwidth($mensagem['text']['body'], 0, 500, '…') : null,
        ]);
    }
}
