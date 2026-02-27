<?php

namespace App\Enums\Academic;

use App\Concerns\HasEnum;
use Illuminate\Support\Arr;

enum CertificateFor: string
{
    use HasEnum;

    case STUDENT = 'student';
    case EMPLOYEE = 'employee';

    public static function translation(): string
    {
        return 'academic.certificate.for.';
    }

    public function variable(): array
    {
        $variables = collect(Arr::getVar('certificate-template-variables'));

        return match ($this) {
            self::STUDENT => $variables->firstWhere('for', 'student')['variables'] ?? [],
            self::EMPLOYEE => $variables->firstWhere('for', 'employee')['variables'] ?? [],
            default => []
        };
    }

    public static function getOptions(): array
    {
        $options = [];

        foreach (self::cases() as $option) {
            $variables = $option->variable()['variables'] ?? '';

            $options[] = ['label' => trans(self::translation().$option->value), 'value' => $option->value, 'variables' => $variables];
        }

        return $options;
    }
}
