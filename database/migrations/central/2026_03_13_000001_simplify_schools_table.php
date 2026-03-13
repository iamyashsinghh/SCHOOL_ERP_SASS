<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplify schools table — remove DB credential columns (shared DB now).
     */
    public function up(): void
    {
        Schema::connection('central')->table('schools', function (Blueprint $table) {
            // Add useful fields
            $table->string('contact_email')->nullable()->after('storage_prefix');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->text('address')->nullable()->after('contact_phone');
            $table->string('logo')->nullable()->after('address');
        });

        // Drop columns that are no longer needed in shared DB architecture
        if (Schema::connection('central')->hasColumn('schools', 'db_name')) {
            Schema::connection('central')->table('schools', function (Blueprint $table) {
                $table->dropColumn(['db_name', 'db_username', 'db_password', 'storage_prefix']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('schools', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone', 'address', 'logo']);
        });

        if (!Schema::connection('central')->hasColumn('schools', 'db_name')) {
            Schema::connection('central')->table('schools', function (Blueprint $table) {
                $table->string('db_name', 100)->unique()->after('status');
                $table->string('db_username', 100)->after('db_name');
                $table->string('db_password')->after('db_username');
                $table->string('storage_prefix', 100)->unique()->after('db_password');
            });
        }
    }
};
