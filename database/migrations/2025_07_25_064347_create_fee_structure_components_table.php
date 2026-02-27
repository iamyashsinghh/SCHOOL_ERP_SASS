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
        Schema::create('fee_structure_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index()->unique();

            $table->foreignId('fee_installment_record_id', 'fsc_fee_installment_record_id')->nullable()->constrained('fee_installment_records')->onDelete('cascade');
            $table->foreignId('fee_component_id')->nullable()->constrained('fee_components')->onDelete('cascade');

            $table->decimal('amount', 25, 5)->default(0);

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_structure_components');
    }
};
