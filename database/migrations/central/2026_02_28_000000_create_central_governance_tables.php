<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('central')->create('ministries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });

        Schema::connection('central')->create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')->constrained('ministries')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::connection('central')->create('sub_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::connection('central')->create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_division_id')->constrained('sub_divisions')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('db_name', 100)->unique();
            $table->string('db_username', 100);
            $table->string('db_password'); // Will store encrypted password
            $table->string('storage_prefix', 100)->unique();
            $table->timestamps();
        });

        Schema::connection('central')->create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('domain')->unique(); // e.g., school1.example.com
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('domains');
        Schema::connection('central')->dropIfExists('schools');
        Schema::connection('central')->dropIfExists('sub_divisions');
        Schema::connection('central')->dropIfExists('provinces');
        Schema::connection('central')->dropIfExists('ministries');
    }
};
