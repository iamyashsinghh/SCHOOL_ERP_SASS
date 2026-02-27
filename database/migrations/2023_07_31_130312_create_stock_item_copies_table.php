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
        Schema::create('stock_item_copies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index()->unique();
            $table->integer('number')->nullable();
            $table->string('code_number')->nullable();
            $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->onDelete('cascade');
            $table->foreignId('condition_id')->nullable()->constrained('options')->onDelete('set null');
            $table->float('price')->nullable()->index();
            $table->string('vendor', 100)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->nullableMorphs('place');
            $table->string('hold_status', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_item_copies');
    }
};
