<?php

$dirs = [
    __DIR__ . '/app',
    __DIR__ . '/database',
    __DIR__ . '/routes',
    __DIR__ . '/tests',
    __DIR__ . '/config',
];

$count = 0;

foreach ($dirs as $dirPath) {
    if (!is_dir($dirPath)) continue;

    $dir = new RecursiveDirectoryIterator($dirPath);
    $ite = new RecursiveIteratorIterator($dir);

    foreach ($ite as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            
            // 1. Rewrite `use App\Models\X;` -> `use App\Models\Tenant\X;`
            $newContent = preg_replace('/use App\\\\Models\\\\(?!Tenant\\\\|Central\\\\)([a-zA-Z0-9_\\\\]+);/', 'use App\Models\Tenant\\\$1;', $content);
            
            // 2. Rewrite inline instantiations like `\App\Models\X::` -> `\App\Models\Tenant\X::`
            $newContent = preg_replace('/\\\\?App\\\\Models\\\\(?!Tenant\\\\|Central\\\\)([a-zA-Z0-9_\\\\]+)/', 'App\Models\Tenant\\\$1', $newContent);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $count++;
            }
        }
    }
}

echo "Namespace paths updated in $count files.\n";
