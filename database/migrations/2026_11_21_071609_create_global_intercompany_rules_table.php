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
        Schema::create('global_intercompany_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('from_entity_code', 50)->index();
            $table->string('to_entity_code', 50)->index();
            $table->string('category', 50); // hutang_piutang_spbu, hutang_piutang_mjs, dll
            $table->string('source_report_type', 30)->default('balance_sheet'); // balance_sheet, pnl
            $table->string('source_category', 50)->nullable(); // liability, asset, opex, cogs
            $table->string('account_name_pattern', 255); // exact or pattern, e.g. "Hutang SPBU"
            $table->text('description_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_intercompany_rules');
    }
};
