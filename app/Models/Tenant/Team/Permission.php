<?php

namespace App\Models\Tenant\Team;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'tenant';

    use HasUuid;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'permissions';
}
