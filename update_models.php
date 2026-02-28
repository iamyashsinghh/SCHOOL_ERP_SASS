<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Models/Tenant');
$ite = new RecursiveIteratorIterator($dir);

foreach ($ite as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        // Update namespace
        $content = preg_replace('/namespace App\\\\Models;/', 'namespace App\Models\Tenant;', $content);
        $content = preg_replace('/namespace App\\\\Models\\\\([a-zA-Z0-9_\\\\]+);/', 'namespace App\Models\Tenant\\\$1;', $content);
        
        // Add protected $connection = 'tenant'; inside class if not already there and if extends Model
        // Basic naive regex, assume standard formatting
        if (strpos($content, "protected \$connection = 'tenant';") === false) {
             $content = preg_replace('/class\s+([a-zA-Z0-9_]+)\s+extends\s+Model(?: implements [^{]+)?\s*\{/', "$0\n    protected \$connection = 'tenant';\n", $content);
        }

        file_put_contents($path, $content);
    }
}

echo "Tenant models updated successfully.\n";
