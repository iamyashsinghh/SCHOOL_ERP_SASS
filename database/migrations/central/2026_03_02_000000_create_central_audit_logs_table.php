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
        Schema::connection('central')->create('central_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // central_user_id
            $table->string('action'); // e.g. 'school_provisioned', 'ministry_suspended'
            $table->string('entity_type')->nullable(); // e.g. 'School', 'Ministry'
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable(); // Original/New data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('central_audit_logs');
    }
};
