<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables to EXCLUDE from sass_school_id (system/framework tables).
     */
    protected array $excludedTables = [
        'migrations',
        'failed_jobs',
        'jobs',
        'job_batches',
        'personal_access_tokens',
        'sessions',
    ];

    /**
     * Run the migrations — Add sass_school_id to ALL tenant tables.
     */
    public function up(): void
    {
        $tables = Schema::connection('tenant')->getTableListing();

        foreach ($tables as $table) {
            // Skip system tables
            if (in_array($table, $this->excludedTables)) {
                continue;
            }

            // Skip if column already exists
            if (Schema::connection('tenant')->hasColumn($table, 'sass_school_id')) {
                continue;
            }

            Schema::connection('tenant')->table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('sass_school_id')->nullable()->index();
            });
        }

        // Clear the cached table list so SassSchoolScope picks up new columns
        cache()->forget('sass_school_tables_list');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = Schema::connection('tenant')->getTableListing();

        foreach ($tables as $table) {
            if (in_array($table, $this->excludedTables)) {
                continue;
            }

            if (Schema::connection('tenant')->hasColumn($table, 'sass_school_id')) {
                Schema::connection('tenant')->table($table, function (Blueprint $table) {
                    $table->dropIndex(['sass_school_id']);
                    $table->dropColumn('sass_school_id');
                });
            }
        }
    }
};
