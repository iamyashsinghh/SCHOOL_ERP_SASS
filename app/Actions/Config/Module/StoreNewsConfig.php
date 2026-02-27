<?php

namespace App\Actions\Config\Module;

class StoreNewsConfig
{
    public static function handle(): array
    {
        $input = request()->validate([
            'enable_news' => 'boolean',
        ], [], [
            'enable_news' => __('global.enable', ['attribute' => __('news.news')]),
        ]);

        return $input;
    }
}
