<?php

namespace App\Actions\Config\Module;

class StoreGuardianConfig
{
    public static function handle(): array
    {
        $input = request()->validate([], [], []);

        return $input;
    }
}
