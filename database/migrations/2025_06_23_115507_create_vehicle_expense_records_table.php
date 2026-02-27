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
        Schema::create('vehicle_expense_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index()->unique();
            $table->string('number_format', 50)->nullable();
            $table->integer('number')->nullable();
            $table->string('code_number', 50)->nullable();

            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('type_id')->nullable()->constrained('options')->onDelete('set null');
            $table->foreignId('vendor_id')->nullable()->constrained('ledgers')->onDelete('cascade');
            $table->foreignId('case_id')->nullable()->constrained('vehicle_case_records')->onDelete('set null');

            $table->date('date')->nullable();
            $table->float('quantity')->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('price_per_unit', 25, 5)->default(0);
            $table->decimal('amount', 25, 5)->default(0);
            $table->integer('log')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_expense_records');
    }
};
