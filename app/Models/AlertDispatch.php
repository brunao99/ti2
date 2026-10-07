<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertDispatch extends Model
{
    protected $fillable = [
        'postagem_id', 'alert_template_id', 'user_id',
        'mensagem', 'status', 'total_destinatarios', 'total_enviados', 'total_falhas',
    ];

    public function postagem(): BelongsTo
    {
        return $this->belongsTo(Postagem::class);
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(AlertTemplate::class, 'alert_template_id');
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(AlertDispatchRecipient::class);
    }

    public function temFalhas(): bool
    {
        return $this->total_falhas > 0;
    }

    /**
     * Refaz os totais a partir das linhas de destinatário. Depois de um
     * reenvio, contar de novo é mais seguro que somar e subtrair.
     */
    public function recalcularTotais(): void
    {
        $porStatus = $this->destinatarios()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $enviados = (int) ($porStatus['enviado'] ?? 0);
        $falhas = (int) ($porStatus['falha'] ?? 0);

        $this->update([
            'total_destinatarios' => $enviados + $falhas,
            'total_enviados' => $enviados,
            'total_falhas' => $falhas,
            'status' => $falhas === 0 ? 'sucesso' : ($enviados > 0 ? 'parcial' : 'falha'),
        ]);
    }

    public function rotuloStatus(): string
    {
        return ['sucesso' => 'Sucesso', 'parcial' => 'Parcial', 'falha' => 'Falha'][$this->status] ?? ucfirst($this->status);
    }

    public function classeStatus(): string
    {
        return ['sucesso' => 'badge-ok', 'parcial' => 'badge-draft', 'falha' => 'badge-erro'][$this->status] ?? 'badge-draft';
    }
}
