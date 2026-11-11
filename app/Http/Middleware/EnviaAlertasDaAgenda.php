<?php

namespace App\Http\Middleware;

use App\Models\AgendaEvento;
use App\Services\Agenda\EnviaAlertasProgramados;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reserva do agendador: depois de responder uma página, confere se algum
 * alerta programado já chegou no horário e envia.
 *
 * O caminho principal é o agendador do Laravel (schedule:work ou cron), que
 * roda a cada minuto mesmo sem ninguém no painel. Onde ele não estiver
 * ligado, isto garante que o alerta saia assim que alguém abrir o painel.
 * A conferência acontece no máximo uma vez por minuto e depois da resposta,
 * então não atrasa a página.
 */
class EnviaAlertasDaAgenda
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->user() || ! $request->isMethod('GET')) {
            return;
        }

        try {
            // add() só grava se a chave não existe: é a trava de um minuto.
            if (! Cache::add('agenda:conferencia-de-alertas', true, 60)) {
                return;
            }

            if (AgendaEvento::where('tipo', AgendaEvento::ALERTA)->exists()) {
                app(EnviaAlertasProgramados::class)->executar();
            }
        } catch (\Throwable $erro) {
            Log::error('Agenda: falha na conferência de alertas programados.', ['erro' => $erro->getMessage()]);
        }
    }
}
