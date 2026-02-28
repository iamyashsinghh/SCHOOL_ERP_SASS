<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Central\School;
use App\Models\Central\Domain;
use App\Models\Central\SubDivision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MigrateLegacyTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate-legacy 
                            {name : Name of the school} 
                            {domain : Primary domain (e.g. legacy.localhost)} 
                            {db_name : The EXISTING database name}
                            {--sub-division-id=1 : The sub-division ID in central DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate a standalone InstiKit instance into the multi-tenant structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $domainStr = $this->argument('domain');
        $dbName = $this->argument('db_name');
        $subDivisionId = $this->option('sub-division-id');

        $this->info("Starting migration for legacy school: {$name}...");

        // 1. Verify Database Exists
        $dbExists = DB::connection('central')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
        if (empty($dbExists)) {
            $this->error("Database '{$dbName}' does not exist!");
            return 1;
        }

        // 2. Register in Central DB
        $subDivision = SubDivision::on('central')->find($subDivisionId);
        if (!$subDivision) {
            $this->error("Sub-Division ID {$subDivisionId} not found in central DB!");
            return 1;
        }

        $school = School::on('central')->create([
            'ministry_id' => $subDivision->province->ministry_id,
            'province_id' => $subDivision->province_id,
            'sub_division_id' => $subDivision->id,
            'name' => $name,
            'code' => strtoupper(Str::slug($name)),
            'db_name' => $dbName,
            'db_username' => config('database.connections.mysql.username'),
            'db_password' => Crypt::encryptString(config('database.connections.mysql.password')),
            'storage_prefix' => Str::slug($name),
            'status' => 'active',
        ]);

        Domain::on('central')->create([
            'school_id' => $school->id,
            'domain' => $domainStr,
        ]);

        $this->info("School registered with ID: {$school->id}");

        // 3. Move Files
        $this->migrateFiles($school->id);

        $this->info("Migration completed successfully!");
        $this->info("You can now access the school at: http://{$domainStr}");

        return 0;
    }

    /**
     * Move existing storage files to tenant-specific folders.
     */
    protected function migrateFiles($schoolId)
    {
        $this->info("Moving storage files...");

        $tenantStoragePath = storage_path('app/tenants/' . $schoolId);
        $tenantPublicPath = storage_path('app/public/tenants/' . $schoolId);

        if (!File::exists($tenantStoragePath)) File::makeDirectory($tenantStoragePath, 0755, true);
        if (!File::exists($tenantPublicPath)) File::makeDirectory($tenantPublicPath, 0755, true);

        // 1. Move public files (excluding the 'tenants' folder)
        $publicSource = storage_path('app/public');
        if (File::exists($publicSource)) {
            $publicFiles = File::allFiles($publicSource);
            foreach ($publicFiles as $file) {
                $relativePath = $file->getRelativePathname();
                if (Str::startsWith($relativePath, 'tenants')) continue;

                $destPath = $tenantPublicPath . '/' . $relativePath;
                File::ensureDirectoryExists(dirname($destPath));
                File::move($file->getRealPath(), $destPath);
            }
        }

        // 2. Move private downloads (if any)
        $downloadSource = storage_path('app/downloads');
        if (File::exists($downloadSource)) {
            $downloadFiles = File::allFiles($downloadSource);
            foreach ($downloadFiles as $file) {
                $destPath = $tenantStoragePath . '/downloads/' . $file->getRelativePathname();
                File::ensureDirectoryExists(dirname($destPath));
                File::move($file->getRealPath(), $destPath);
            }
        }

        $this->info("Files relocated to: storage/app/public/tenants/{$schoolId}");
    }
}
