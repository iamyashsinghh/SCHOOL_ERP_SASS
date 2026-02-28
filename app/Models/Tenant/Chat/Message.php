<?php

namespace App\Models\Tenant\Chat;

use App\Concerns\HasUuid;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasUuid;

    protected $table = 'chat_messages';

    protected $fillable = ['chat_id', 'user_id', 'content', 'type'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
