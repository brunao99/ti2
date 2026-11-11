<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que aconteceu com uma data de um evento: foi cancelada, ou o alerta
 * daquele dia saiu (ou falhou). Data sem linha aqui segue a regra normal.
 */
class AgendaOcorrencia extends Model
{
    public const CANCELADO = 'cancelado';
    public const ENVIANDO = 'enviando';
    public const ENVIADO = 'enviado';
    public const FALHOU = 'falhou';

    protected $table = 'agenda_ocorrencias';

    protected $fillable = ['agenda_evento_id', 'data', 'situacao', 'alert_dispatch_id', 'user_id', 'detalhe'];

    protected function casts(): array
    {
        return ['data' => 'immutable_date'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(AgendaEvento::class, 'agenda_evento_id');
    }

    public function disparo(): BelongsTo
    {
        return $this->belongsTo(AlertDispatch::class, 'alert_dispatch_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
