<?php

namespace App\Services\Relatorios;

use App\Models\AlertDispatchRecipient;
use App\Models\Postagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dados do relatório enviado à prefeitura: as publicações do período e os
 * alertas entregues àquele cliente. A planilha e o PDF leem daqui, então os
 * dois formatos saem sempre com o mesmo conteúdo.
 *
 * Regras (blueprint aprovado):
 *  - publicações: só "publicado", com data de publicação no período;
 *    são as mesmas para todos os clientes;
 *  - alertas: só os entregues ao cliente — status "enviado" e, quando há
 *    rastreio da Meta (wamid), status "delivered" ou "read";
 *  - os dois blocos em ordem de data; a numeração continua de um para o outro.
 */
class RelatorioPrefeitura
{
    public const EMPRESA = 'CD Algoritmos e Dados Ltda';
    public const CNPJ = '58.502.042/0001-00';
    public const SITE = 'www.algoritmosedados.com.br';
    public const TITULO = 'Relatório de oportunidades para a gestão pública';
    public const SUBTITULO = 'Informações e oportunidades disponibilizadas no portal ' . self::SITE;
    public const LOCAL = 'Belo Horizonte';

    /**
     * O período é escolhido no horário de Brasília. O banco grava em UTC
     * (config/app.php), então um alerta das 22h do dia 30 está gravado como
     * dia 1º — os limites são convertidos antes da consulta.
     */
    public const FUSO = 'America/Sao_Paulo';

    private const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    private ?Collection $publicacoes = null;

    private ?Collection $alertas = null;

    public readonly CarbonImmutable $emitidoEm;

    public function __construct(
        public readonly User $cliente,
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fim,
    ) {
        $this->emitidoEm = CarbonImmutable::now(self::FUSO);
    }

    /** Datas no formato do campo de data do navegador (aaaa-mm-dd). */
    public static function dia(string $data): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $data, self::FUSO);
    }

    // ------------------------------------------------------------- consultas

    public static function consultaPublicacoes(CarbonImmutable $inicio, CarbonImmutable $fim): Builder
    {
        // whereDate: a coluna é date, mas o SQLite guarda "aaaa-mm-dd 00:00:00".
        return Postagem::query()
            ->publicadas()
            ->whereDate('data_publicacao', '>=', $inicio->toDateString())
            ->whereDate('data_publicacao', '<=', $fim->toDateString());
    }

    public static function consultaAlertas(User $cliente, CarbonImmutable $inicio, CarbonImmutable $fim): Builder
    {
        $fusoBanco = config('app.timezone');

        return AlertDispatchRecipient::query()
            ->select('alert_dispatch_recipients.*')
            ->join('alert_dispatches', 'alert_dispatches.id', '=', 'alert_dispatch_recipients.alert_dispatch_id')
            ->where('alert_dispatch_recipients.user_id', $cliente->id)
            ->where('alert_dispatch_recipients.status', 'enviado')
            ->where(fn (Builder $q) => $q
                ->whereNull('alert_dispatch_recipients.wamid')
                ->orWhereIn('alert_dispatch_recipients.status_meta', ['delivered', 'read']))
            ->whereBetween('alert_dispatches.created_at', [
                $inicio->startOfDay()->setTimezone($fusoBanco)->toDateTimeString(),
                $fim->endOfDay()->setTimezone($fusoBanco)->toDateTimeString(),
            ]);
    }

    // ----------------------------------------------------------------- linhas

    /** @return Collection<int, Postagem> */
    public function publicacoes(): Collection
    {
        return $this->publicacoes ??= self::consultaPublicacoes($this->inicio, $this->fim)
            ->orderBy('data_publicacao')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, AlertDispatchRecipient> */
    public function alertas(): Collection
    {
        return $this->alertas ??= self::consultaAlertas($this->cliente, $this->inicio, $this->fim)
            ->with('disparo.postagem')
            ->orderBy('alert_dispatches.created_at')
            ->orderBy('alert_dispatch_recipients.id')
            ->get();
    }

    public function totalCategorias(): int
    {
        return $this->publicacoes()->pluck('tipo')->unique()->count();
    }

    /** Data do disparo, no horário de Brasília. */
    public function dataDoAlerta(AlertDispatchRecipient $alerta): CarbonImmutable
    {
        return CarbonImmutable::parse($alerta->disparo->created_at)->setTimezone(self::FUSO);
    }

    public function conteudoDoAlerta(AlertDispatchRecipient $alerta): string
    {
        $titulo = $alerta->disparo->postagem?->titulo;

        return $titulo ? 'Comunicado importante referente a ' . $titulo : 'Comunicado importante';
    }

    public function linkDoAlerta(AlertDispatchRecipient $alerta): ?string
    {
        return $alerta->disparo->postagem?->link;
    }

    /**
     * O endereço aparece sem "https://" e sem "www.", cortado se for longo;
     * o link clicável leva ao endereço completo.
     */
    public static function linkCurto(string $url, int $limite = 60): string
    {
        $curto = preg_replace('#^https?://(www\.)?#i', '', $url);
        $curto = rtrim($curto, '/');

        return mb_strlen($curto) > $limite ? mb_substr($curto, 0, $limite - 1) . '…' : $curto;
    }

    // ---------------------------------------------------------------- textos

    public function periodo(): string
    {
        return $this->inicio->format('d/m/Y') . ' a ' . $this->fim->format('d/m/Y');
    }

    /** "21 de outubro de 2026" */
    public function dataPorExtenso(): string
    {
        $data = $this->emitidoEm;

        return $data->day . ' de ' . self::MESES[$data->month - 1] . ' de ' . $data->year;
    }

    /** Relatório Itaúna-MG 01-09-2026 a 30-09-2026.xlsx */
    public function nomeArquivo(string $extensao): string
    {
        return self::montarNomeArquivo($this->cliente, $this->inicio, $this->fim, $extensao);
    }

    public static function montarNomeArquivo(User $cliente, CarbonImmutable $inicio, CarbonImmutable $fim, string $extensao): string
    {
        // A barra de "Itaúna/MG" criaria uma pasta; os demais caracteres
        // proibidos no Windows também viram hífen.
        $nome = preg_replace('#[\\\\/:*?"<>|]+#', '-', $cliente->nome);
        $nome = trim(preg_replace('/\s+/', ' ', $nome));

        return sprintf('Relatório %s %s a %s.%s', $nome, $inicio->format('d-m-Y'), $fim->format('d-m-Y'), $extensao);
    }
}
