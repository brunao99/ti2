<?php

namespace App\Console\Commands;

use App\Services\Agenda\EnviaAlertasProgramados;
use Illuminate\Console\Command;

class EnviarAlertasProgramados extends Command
{
    protected $signature = 'agenda:enviar-alertas';

    protected $description = 'Envia os alertas programados na agenda que já chegaram no horário';

    public function handle(EnviaAlertasProgramados $envio): int
    {
        [$enviados, $falhas] = $envio->executar();

        $this->info("Alertas programados: {$enviados} enviado(s), {$falhas} com falha.");

        return self::SUCCESS;
    }
}
