<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertDispatchRecipient extends Model
{
    protected $fillable = [
        'alert_dispatch_id', 'user_id', 'nome', 'telefone', 'status', 'resultado',
    ];

    public function disparo(): BelongsTo
    {
        return $this->belongsTo(AlertDispatch::class, 'alert_dispatch_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
