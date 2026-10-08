<?php

namespace App\Services\Alertas;

interface AlertSender
{
    /**
     * Envia o alerta e devolve o resultado que será registrado no histórico.
     *
     * $mensagem é o texto já montado (o que o histórico mostra). $parametros
     * são os valores do template da Meta, na ordem {{1}}, {{2}}, {{3}}:
     * título, tipo e data da publicação.
     *
     * @param  list<string>  $parametros
     * @return array{status: 'enviado'|'falha', resultado: string, wamid?: string, erro_codigo?: int|null, erro_mensagem?: string|null}
     */
    public function send(string $telefone, string $mensagem, array $parametros = []): array;
}
