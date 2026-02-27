<?php

namespace App\Helpers;

use Spatie\Browsershot\Browsershot;

class PdfHelper
{
    public static function savePdf(string $html, string $path, array $options = [])
    {
        if (! config('app.enable_browsershot')) {
            return;
        }

        Browsershot::html($html)
            ->setNodeBinary(config('app.node_binary'))
            ->setNpmBinary(config('app.npm_binary'))
            ->save($path);
    }
}
