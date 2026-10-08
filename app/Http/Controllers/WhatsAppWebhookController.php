<?php

namespace App\Http\Controllers;

use App\Services\Alertas\ProcessaWebhookWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Webhook da WhatsApp Cloud API: /webhook/whatsapp.
 *
 * GET  — verificação feita pela Meta ao cadastrar a URL de callback.
 * POST — eventos: status das mensagens enviadas e mensagens recebidas.
 *
 * Fica fora do CSRF (bootstrap/app.php): quem chama é a Meta, sem sessão.
 * A proteção do POST é a assinatura X-Hub-Signature-256.
 */
class WhatsAppWebhookController extends Controller
{
    public function verificar(Request $request): Response
    {
        // O PHP troca "." por "_" nos nomes da query: hub.mode chega como hub_mode.
        $modo = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $esperado = (string) config('services.whatsapp.verify_token');

        if ($modo === 'subscribe' && $esperado !== '' && hash_equals($esperado, $token)) {
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook: verificação recusada', ['modo' => $modo]);

        return response('Forbidden', 403)->header('Content-Type', 'text/plain');
    }

    public function receber(Request $request, ProcessaWebhookWhatsApp $processador): Response
    {
        $corpo = $request->getContent();

        if (! $this->assinaturaValida($corpo, (string) $request->header('X-Hub-Signature-256'))) {
            Log::warning('WhatsApp webhook: assinatura inválida', ['ip' => $request->ip()]);

            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        }

        $payload = json_decode($corpo, true);

        // Responde já: a Meta reenvia o evento se a resposta demorar ou não for
        // 200. O processamento roda logo depois de a resposta sair.
        if (is_array($payload)) {
            defer(fn () => $processador->processar($payload));
        } else {
            Log::warning('WhatsApp webhook: corpo não é JSON');
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }

    /** HMAC-SHA256 do corpo bruto com o App Secret, como a Meta assina. */
    private function assinaturaValida(string $corpo, string $assinatura): bool
    {
        $segredo = (string) config('services.whatsapp.app_secret');

        // Sem segredo configurado não há como conferir: recusa tudo.
        if ($segredo === '' || ! str_starts_with($assinatura, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $corpo, $segredo), $assinatura);
    }
}
