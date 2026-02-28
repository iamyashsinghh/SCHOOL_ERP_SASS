<?php

$dirs = [
    __DIR__ . '/app',
    __DIR__ . '/database',
    __DIR__ . '/routes',
];

foreach ($dirs as $dirPath) {
    if (!is_dir($dirPath)) continue;

    $dir = new RecursiveDirectoryIterator($dirPath);
    $ite = new RecursiveIteratorIterator($dir);

    foreach ($ite as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            
            // Skip the actual Models/Tenant folder itself, we already processed the class declarations there
            // But we MIGHT need to fix `use App\Models\...` inside those very same model files too!
            
            $path = $file->getPathname();
            $content = file_get_contents($path);
            
            // 1. Rewrite `use App\Models\X;` -> `use App\Models\Tenant\X;` 
            // Negative lookahead so we don't accidentally do `App\Models\Tenant\Tenant\`
            $content = preg_replace('/use App\\\\Models\\\\(?!Tenant\\\\|Central\\\\)([a-zA-Z0-9_\\\\]+);/', 'use App\Models\Tenant\\\$1;', $content);
            
            // 2. Rewrite inline instantiations like `\App\Models\X::` -> `\App\Models\Tenant\X::`
            $content = preg_replace('/\\\\?App\\\\Models\\\\(?!Tenant\\\\|Central\\\\)([a-zA-Z0-9_\\\\]+)/', 'App\Models\Tenant\\\$1', $content);

            file_put_contents($path, $content);
        }
    }
}

echo "Namespace paths updated throughout app, database, and routes.\n";
