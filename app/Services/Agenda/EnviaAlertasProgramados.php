<?php

namespace App\Services\Agenda;

use App\Models\AgendaEvento;
use App\Models\AgendaOcorrencia;
use App\Models\User;
use App\Services\Alertas\DisparaAlerta;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Faz os alertas programados saírem no horário marcado.
 *
 * Quem chama é o agendador (comando agenda:enviar-alertas, a cada minuto) e,
 * como reserva, o próprio painel depois de atender uma página. Os dois podem
 * rodar ao mesmo tempo: a linha única por evento e data em agenda_ocorrencias
 * garante que cada envio saia uma vez só.
 */
class EnviaAlertasProgramados
{
    public function __construct(private readonly DisparaAlerta $disparador)
    {
    }

    /**
     * Envia tudo o que já chegou na hora. Devolve [enviados, falhas].
     *
     * @return array{0: int, 1: int}
     */
    public function executar(?CarbonImmutable $agora = null): array
    {
        $agora ??= AgendaEvento::agora();
        $hoje = CarbonImmutable::parse($agora->format('Y-m-d'));
        $ontem = $hoje->subDay(); // um alerta atrasado ainda sai dentro da tolerância
        $enviados = 0;
        $falhas = 0;

        $eventos = AgendaEvento::query()
            ->where('tipo', AgendaEvento::ALERTA)
            ->whereDate('data', '<=', $hoje)
            ->with(['postagem', 'modelo', 'autor'])
            ->get();

        foreach ($eventos as $evento) {
            foreach ($evento->recorrencia()->entre($ontem, $hoje) as $data) {
                $instante = $evento->instante($data);

                if ($instante->gt($agora) || $instante->lt($agora->subHours(Ocorrencia::TOLERANCIA_HORAS))) {
                    continue;
                }

                $registro = $this->enviar($evento, $data);

                if ($registro === null) {
                    continue; // já tratado por outro processo, ou cancelado
                }

                $registro->situacao === AgendaOcorrencia::ENVIADO ? $enviados++ : $falhas++;
            }
        }

        return [$enviados, $falhas];
    }

    /**
     * Envia o alerta de uma data. Devolve o registro com o resultado, ou null
     * se aquela data já tinha sido enviada ou cancelada.
     *
     * $por é quem pediu o envio na hora ("Enviar agora"); no automático, fica
     * o autor do evento como responsável pelo disparo.
     */
    public function enviar(AgendaEvento $evento, CarbonImmutable $data, ?User $por = null): ?AgendaOcorrencia
    {
        // Reserva a data antes de enviar. Se a linha já existe, outro processo
        // (ou um cancelamento) chegou antes: não envia de novo.
        try {
            $registro = AgendaOcorrencia::create([
                'agenda_evento_id' => $evento->id,
                'data' => $data->format('Y-m-d'),
                'situacao' => AgendaOcorrencia::ENVIANDO,
                'user_id' => $por?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $autor = $por ?? $evento->autor;

        if (! $evento->postagem || $evento->postagem->situacao !== 'publicado') {
            return $this->falhar($registro, 'A publicação do alerta não está mais publicada.');
        }

        if (! $evento->modelo) {
            return $this->falhar($registro, 'O modelo de mensagem do alerta foi removido.');
        }

        try {
            $disparo = $this->disparador->executar($evento->postagem, $evento->modelo, $autor);
        } catch (\Throwable $erro) {
            Log::error('Agenda: falha ao enviar alerta programado.', [
                'evento' => $evento->id, 'data' => $data->format('Y-m-d'), 'erro' => $erro->getMessage(),
            ]);

            return $this->falhar($registro, 'O envio não pôde ser concluído. Tente enviar de novo pela agenda.');
        }

        if (! $disparo) {
            return $this->falhar($registro, 'Nenhum usuário tinha autorizado receber alertas na hora do envio.');
        }

        $registro->update([
            'situacao' => $disparo->status === 'falha' ? AgendaOcorrencia::FALHOU : AgendaOcorrencia::ENVIADO,
            'alert_dispatch_id' => $disparo->id,
            'detalhe' => $disparo->status === 'falha' ? 'Nenhuma mensagem foi entregue. Veja o disparo na Central de alertas.' : null,
        ]);

        return $registro;
    }

    private function falhar(AgendaOcorrencia $registro, string $motivo): AgendaOcorrencia
    {
        $registro->update(['situacao' => AgendaOcorrencia::FALHOU, 'detalhe' => $motivo]);

        return $registro;
    }
}
