<?php

namespace App\Http\Controllers;

class DownloadFormatController extends Controller
{
    public function __invoke()
    {
        $directory = public_path('format/import');
        $files = glob($directory.'/*.{xls,xlsx,csv}', GLOB_BRACE);

        $fileNames = array_map(function ($file) {
            return [
                'name' => basename($file),
                'url' => url('format/import/'.basename($file)),
            ];
        }, $files);

        return view('download-format', ['files' => $fileNames]);
    }
}
