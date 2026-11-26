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
        Schema::create('global_period_notes', function (Blueprint $table) {
            $table->id();
            $table->date('period')->index();
            $table->string('business_unit_code', 50)->default('all')->index();
            $table->string('report_type', 30)->default('all')->index(); // 'all', 'pnl', 'balance_sheet'
            $table->string('closing_status', 30)->default('draft'); // 'draft', 'pending_review', 'closed'
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->timestamps();

            $table->unique(['period', 'business_unit_code', 'report_type'], 'idx_period_bu_report_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_period_notes');
    }
};
