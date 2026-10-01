<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertDispatchRecipient extends Model
{
    protected $fillable = [
        'alert_dispatch_id', 'alert_contact_id', 'nome', 'telefone', 'status', 'resultado',
    ];

    public function disparo(): BelongsTo
    {
        return $this->belongsTo(AlertDispatch::class, 'alert_dispatch_id');
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(AlertContact::class, 'alert_contact_id');
    }
}
