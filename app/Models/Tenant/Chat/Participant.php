<?php

namespace App\Models\Tenant\Chat;

use App\Concerns\HasUuid;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasUuid;

    protected $table = 'chat_participants';

    protected $fillable = ['chat_id', 'user_id'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
