<?php

namespace App\Models;

use App\Concerns\HasFilter;
use App\Models\Employee\Employee;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockItemCopy;
use App\Models\Student\Student;
use App\Models\Task\Task;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFilter;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'tags';

    protected $casts = [];

    protected $with = [];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Str::toWord($value),
            set: fn ($value) => Str::slug($value),
        );
    }

    public function students()
    {
        return $this->morphedByMany(Student::class, 'taggable');
    }

    public function employees()
    {
        return $this->morphedByMany(Employee::class, 'taggable');
    }

    public function stockItems()
    {
        return $this->morphedByMany(StockItem::class, 'taggable');
    }

    public function stockItemCopies()
    {
        return $this->morphedByMany(StockItemCopy::class, 'taggable');
    }

    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    public function tickets()
    {
        return $this->morphedByMany(Ticket::class, 'taggable');
    }
}
