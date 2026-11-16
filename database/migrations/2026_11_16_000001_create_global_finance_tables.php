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
        Schema::create('global_business_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('data_source', 30)->default('excel'); // excel, database, api
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('global_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_unit_id')->constrained('global_business_units')->onDelete('cascade');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('location', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('global_financial_records', function (Blueprint $table) {
            $table->id();
            $table->string('business_unit_code', 50)->index();
            $table->string('entity_code', 50)->index();
            $table->date('period')->index(); // YYYY-MM-01
            $table->string('report_type', 20)->index(); // pnl, balance_sheet
            $table->string('category', 50)->index(); // revenue, cogs, gross_profit, opex, operating_profit, other_income, other_expense, net_profit, asset, liability, equity
            $table->string('sub_category', 100)->nullable();
            $table->string('account_code', 50)->nullable();
            $table->string('account_name', 255);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('status', 20)->default('final')->index(); // final, draft, running
            $table->string('source_type', 50)->default('excel_upload'); // excel_upload, database_sync, api
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['entity_code', 'period', 'report_type', 'category'], 'idx_gfr_query');
        });

        Schema::create('global_intercompany_eliminations', function (Blueprint $table) {
            $table->id();
            $table->date('period')->index();
            $table->string('from_entity_code', 50);
            $table->string('to_entity_code', 50);
            $table->string('category', 50);
            $table->text('description');
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('global_ingestion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('business_unit_code', 50);
            $table->string('source_type', 50); // excel_upload, database_sync
            $table->string('filename', 255)->nullable();
            $table->integer('records_count')->default(0);
            $table->string('status', 30)->default('completed');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_ingestion_logs');
        Schema::dropIfExists('global_intercompany_eliminations');
        Schema::dropIfExists('global_financial_records');
        Schema::dropIfExists('global_entities');
        Schema::dropIfExists('global_business_units');
    }
};
