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
            
            // Fix double Tenant in namespaces: `namespace App\Models\Tenant\Tenant;` -> `namespace App\Models\Tenant;`
            $newContent = preg_replace('/namespace App\\\\Models\\\\Tenant\\\\Tenant;/', 'namespace App\Models\Tenant;', $content);
            $newContent = preg_replace('/namespace App\\\\Models\\\\Tenant\\\\Tenant\\\\([a-zA-Z0-9_\\\\]+);/', 'namespace App\Models\Tenant\\\$1;', $newContent);
            
            // Fix double Tenant in uses: `use App\Models\Tenant\Tenant\X;`
            $newContent = preg_replace('/use App\\\\Models\\\\Tenant\\\\Tenant\\\\([a-zA-Z0-9_\\\\]+);/', 'use App\Models\Tenant\\\$1;', $newContent);
            
            // Fix everywhere else
            $newContent = preg_replace('/App\\\\Models\\\\Tenant\\\\Tenant\\\\([a-zA-Z0-9_\\\\]+)/', 'App\Models\Tenant\\\$1', $newContent);

            if ($content !== $newContent) {
                file_put_contents($path, $newContent);
                $count++;
            }
        }
    }
}

echo "Duplicate Tenant namespaces fixed in $count files.\n";
