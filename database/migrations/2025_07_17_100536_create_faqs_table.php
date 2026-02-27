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
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();

            $table->nullableMorphs('model');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('options')->onDelete('set null');

            $table->string('question')->nullable();
            $table->text('answer')->nullable();
            $table->integer('position')->default(0);
            $table->string('visibility')->nullable();
            $table->string('status')->nullable();
            $table->json('reviews')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
