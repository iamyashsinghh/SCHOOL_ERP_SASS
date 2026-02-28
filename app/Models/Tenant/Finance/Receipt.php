<?php

namespace App\Models\Tenant\Finance;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Casts\PriceCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Enums\Finance\TransactionType;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Option;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Receipt extends Model
{
    protected $connection = 'tenant';

    use HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'transactions';

    protected $casts = [
        'type' => TransactionType::class,
        'date' => DateCast::class,
        'amount' => PriceCast::class,
        'reconciliation_date' => DateCast::class,
        'is_online' => 'boolean',
        'processed_at' => DateTimeCast::class,
        'handling_fee' => PriceCast::class,
        'cancelled_at' => DateTimeCast::class,
        'rejected_at' => DateTimeCast::class,
        'rejection_record' => 'array',
        'payment_gateway' => 'array',
        'failed_logs' => 'array',
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'Receipt';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'category_id');
    }

    public function transactionable()
    {
        return $this->morphTo();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TransactionPayment::class, 'payment_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class, 'transaction_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(TransactionRecord::class, 'transaction_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(TransactionRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithRecord(Builder $query)
    {
        $query->addSelect(['record_id' => TransactionRecord::select('id')
            ->whereColumn('transaction_id', 'transactions.id')
            ->orderBy('id', 'asc')
            ->limit(1),
        ])->with('record.ledger');
    }

    public function scopeWithPayment(Builder $query)
    {
        $query->addSelect(['payment_id' => TransactionPayment::select('id')
            ->whereColumn('transaction_id', 'transactions.id')
            ->orderBy('id', 'asc')
            ->limit(1),
        ])->with('payment.method', 'payment.ledger');
    }

    public function scopeSucceeded(Builder $query)
    {
        $query->where(function ($q) {
            $q->whereNull('transactions.cancelled_at')
                ->whereNull('transactions.rejected_at')
                ->where(function ($q) {
                    $q->where('transactions.is_online', false)
                        ->orWhere(function ($q) {
                            $q->where('transactions.is_online', true)
                                ->whereNotNull('transactions.processed_at');
                        });
                });
        });
    }

    public function scopeByPeriod(Builder $query, $periodId = null)
    {
        $periodId = $periodId ?? auth()->user()->current_period_id;

        $query->wherePeriodId($periodId);
    }

    private function getPaymentMethodDetail(TransactionPayment $payment): array
    {
        return [
            'name' => $payment->method->name,
            'has_instrument_number' => $payment->method->getConfig('has_instrument_number'),
            'has_instrument_date' => $payment->method->getConfig('has_instrument_date'),
            'has_clearing_date' => $payment->method->getConfig('has_clearing_date'),
            'has_bank_detail' => $payment->method->getConfig('has_bank_detail'),
            'has_branch_detail' => $payment->method->getConfig('has_branch_detail'),
            'has_reference_number' => $payment->method->getConfig('has_reference_number'),
            'has_card_provider' => $payment->method->getConfig('has_card_provider'),
            'instrument_number' => Arr::get($payment->details, 'instrument_number'),
            'instrument_date' => \Cal::date(Arr::get($payment->details, 'instrument_date')),
            'clearing_date' => \Cal::date(Arr::get($payment->details, 'clearing_date')),
            'bank_detail' => Arr::get($payment->details, 'bank_detail'),
            'branch_detail' => Arr::get($payment->details, 'branch_detail'),
            'reference_number' => Arr::get($payment->details, 'reference_number'),
            'card_provider' => Arr::get($payment->details, 'card_provider'),
        ];
    }

    public function getPaymentMethod(): array
    {
        if (! $this->relationLoaded('payment')) {
            return [];
        }

        return [
            'uuid' => $this->payment->method->uuid,
            'payment_uuid' => $this->payment->uuid,
            'amount' => $this->payment->amount,
            ...$this->getPaymentMethodDetail($this->payment),
        ];
    }

    public function getPaymentMethods(): array
    {
        if (! $this->relationLoaded('payments')) {
            return [];
        }

        return $this->payments->map(function ($payment) {
            return [
                'uuid' => $payment->method->uuid,
                'payment_uuid' => $payment->uuid,
                'amount' => $payment->amount,
                ...$this->getPaymentMethodDetail($payment),
            ];
        })->toArray();
    }

    public function isEditable(): bool
    {
        if ($this->is_online) {
            return false;
        }

        if (! $this->getMeta('sub_head')) {
            return false;
        }

        return true;
    }

    public function isFeeReceiptEditable()
    {
        if ($this->cancelled_at->value || $this->rejected_at->value) {
            return false;
        }

        if ($this->is_online) {
            if (auth()->user()->is_default) {
                return true;
            }

            return false;
        }

        if (auth()->user()->is_default || auth()->user()->hasRole('admin')) {
            return true;
        }

        if ($this->user_id != auth()->id()) {
            return false;
        }

        return true;
    }

    public function getCanEditAttribute()
    {
        if ($this->head == 'student_fee') {
            return $this->isFeeReceiptEditable();
        } elseif ($this->head == 'registration_fee') {
            return false;
        }

        return $this->isEditable();
    }

    public function scopeFindByUuidOrFail(Builder $query, ?string $uuid = null)
    {
        return $query
            ->whereUuid($uuid)
            ->whereIn('meta->sub_head', ['student', 'employee', 'other'])
            ->getOrFail(trans('finance.transaction.transaction'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transaction')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
