<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class CentralAudit extends Model
{
    protected $connection = 'central';

    protected $table = 'central_audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(CentralUser::class, 'user_id');
    }
}
