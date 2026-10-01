<?php

namespace App\Services\Alertas;

interface AlertSender
{
    /**
     * Simula o envio e devolve o resultado que será registrado no histórico.
     *
     * @return array{status: string, resultado: string}
     */
    public function send(string $telefone, string $mensagem): array;
}
