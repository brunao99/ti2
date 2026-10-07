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
}
