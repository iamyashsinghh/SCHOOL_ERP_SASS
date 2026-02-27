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
        Schema::table('fee_groups', function (Blueprint $table) {
            $table->after('name', function (Blueprint $table) {
                $table->string('code', 50)->nullable();
                $table->string('shortcode', 50)->nullable();
            });
        });

        Schema::table('fee_heads', function (Blueprint $table) {
            $table->after('name', function (Blueprint $table) {
                $table->string('code', 50)->nullable();
                $table->string('shortcode', 50)->nullable();
            });

            $table->after('fee_group_id', function (Blueprint $table) {
                $table->foreignId('tax_id')->nullable()->constrained('taxes')->onDelete('set null');
            });

            $table->after('tax_percentage', function (Blueprint $table) {
                $table->string('voucher_number_prefix')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_groups', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropColumn('shortcode');
        });

        Schema::table('fee_heads', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropColumn('shortcode');
            $table->dropForeign(['tax_id']);
            $table->dropColumn('tax_id');
            $table->dropColumn('voucher_number_prefix');
        });
    }
};
