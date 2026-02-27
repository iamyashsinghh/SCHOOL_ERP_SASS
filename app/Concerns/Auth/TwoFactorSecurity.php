<?php

namespace App\Concerns\Auth;

use App\Helpers\IpHelper;
use App\Jobs\Notifications\SendTwoFactorSecurityNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait TwoFactorSecurity
{
    public function getTwoFactorKey(): string
    {
        return 'two_factor_'.Str::lower($this->id).'|'.IpHelper::getClientIp();
    }

    /**
     * Set two factor security code
     */
    public function setTwoFactor(): void
    {
        if (! config('config.auth.enable_two_factor_security')) {
            return;
        }

        $code = rand(100000, 999999);

        // SendTwoFactorSecurityNotification::dispatchSync([
        //     'user_id' => $this->id,
        //     'code' => $code,
        //     'token_lifetime' => config('config.auth.two_factor_security_lifetime'),
        //     'contact_number' => '',
        // ]);

        // cache([$this->getTwoFactorKey() => $code], config('config.auth.two_factor_security_lifetime') * 60);

        session()->put('two_factor_security', true);
    }

    /**
     * Validate two factor security code
     */
    public function validateTwoFactor(string $two_factor_code): void
    {
        if (! config('config.auth.enable_two_factor_security')) {
            return;
        }

        if (! Cache::has($this->getTwoFactorKey()) || Cache::get($this->getTwoFactorKey()) != $two_factor_code) {
            throw ValidationException::withMessages(['code' => __('auth.security.errors.invalid_code')]);
        }

        $this->resetTwoFactor();
    }

    /**
     * Reset two factor security code
     */
    public function resetTwoFactor(): void
    {
        Cache::forget($this->getTwoFactorKey());
        session()->forget('two_factor_security');
    }
}
