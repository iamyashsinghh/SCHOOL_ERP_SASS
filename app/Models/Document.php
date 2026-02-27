<?php

namespace App\Models;

use App\Casts\DateCast;
use App\Casts\DateTimeCast;
use App\Concerns\HasFilter;
use App\Concerns\HasMedia;
use App\Concerns\HasMeta;
use App\Concerns\HasUuid;
use App\Enums\VerificationStatus;
use App\Helpers\CalHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasFactory, HasFilter, HasMedia, HasMeta, HasUuid, LogsActivity;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'documents';

    protected $casts = [
        'issue_date' => DateCast::class,
        'start_date' => DateCast::class,
        'end_date' => DateCast::class,
        'verified_at' => DateTimeCast::class,
        'meta' => 'array',
    ];

    public function getModelName(): string
    {
        return 'Document';
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'type_id');
    }

    public function getPeriodAttribute(): string
    {
        return CalHelper::getPeriod($this->start_date->value, $this->end_date->value);
    }

    public function getDurationAttribute(): string
    {
        return CalHelper::getDuration($this->start_date->value, $this->end_date->value, 'day');
    }

    public function getCalculatedExpiryInDaysAttribute(): int
    {
        if (empty($this->end_date->value)) {
            return -1;
        }

        return abs(Carbon::parse($this->end_date->value)->diffInDays(today())) + 1;
    }

    public function getIsExpiredAttribute(): bool
    {
        if (empty($this->end_date->value)) {
            return false;
        }

        if ($this->end_date->value > today()->toDateString()) {
            return false;
        }

        return true;
    }

    public function getShowExpiryDateAlertAttribute(): bool
    {
        if (! $this->type?->getMeta('has_expiry_date')) {
            return false;
        }

        $alertDaysBeforeExpiry = (int) $this->type?->getMeta('alert_days_before_expiry');

        if ($alertDaysBeforeExpiry > 0) {
            return $this->expiry_in_days <= $alertDaysBeforeExpiry;
        }

        return false;
    }

    public function getIsVerifiedAttribute(): bool
    {
        if ($this->getMeta('self_upload')) {
            return $this->verified_at->value ? true : false;
        }

        return true;
    }

    public function getVerificationStatusAttribute(): VerificationStatus
    {
        if (! $this->getMeta('self_upload')) {
            return VerificationStatus::VERIFIED;
        }

        if ($this->verified_at->value) {
            return VerificationStatus::VERIFIED;
        }

        if ($this->getMeta('status') == 'rejected') {
            return VerificationStatus::REJECTED;
        }

        return VerificationStatus::PENDING;
    }

    public function getDetailedStatus($type = null)
    {
        if (empty($type)) {
            $type = $this->type;
        }

        if ($type->getMeta('has_expiry_date')) {
            if ($this->is_expired) {
                return [
                    'label' => trans('employee.document.expired'),
                    'value' => 'expired',
                    'color' => 'danger',
                ];
            }

            if ($this->show_expiry_date_alert) {
                return [
                    'label' => trans('employee.document.expiring_soon'),
                    'value' => 'expiring_soon',
                    'color' => 'warning',
                ];
            }
        }

        $verificationStatus = $this->verification_status;

        if ($verificationStatus == VerificationStatus::REJECTED) {
            return [
                'label' => trans('employee.verification.statuses.rejected'),
                'value' => 'rejected',
                'color' => 'danger',
            ];
        } elseif ($verificationStatus == VerificationStatus::PENDING) {
            return [
                'label' => trans('employee.verification.statuses.pending'),
                'value' => 'pending',
                'color' => 'warning',
            ];
        }

        return [
            'label' => trans('general.yes'),
            'value' => 'valid',
            'color' => 'success',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('document')
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
