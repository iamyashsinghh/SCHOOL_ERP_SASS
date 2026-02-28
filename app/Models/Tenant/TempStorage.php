<?php

namespace App\Models\Tenant;

use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TempStorage extends Model
{
    protected $connection = 'tenant';

    use HasMeta, HasUuid;

    protected $table = 'temp_storage';

    protected $casts = [
        'values' => 'array',
        'meta' => 'array',
    ];

    public function getValue(string $option, mixed $default = null)
    {
        return Arr::get($this->values, $option, $default);
    }
}
