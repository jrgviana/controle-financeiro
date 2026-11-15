<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 160);
            $table->string('category', 30)->default('moradia');
            $table->unsignedTinyInteger('due_day');
            $table->decimal('planned_amount', 14, 2);
            $table->date('starts_on');
            $table->string('notes', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'active']);
        });

        Schema::table('scheduled_bills', function (Blueprint $table): void {
            $table->foreignId('recurring_bill_id')->nullable()->constrained('recurring_bills')->restrictOnDelete();
            $table->date('period_month')->nullable();
            $table->unique(['recurring_bill_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_bills', function (Blueprint $table): void {
            $table->dropUnique(['recurring_bill_id', 'period_month']);
            $table->dropConstrainedForeignId('recurring_bill_id');
            $table->dropColumn('period_month');
        });

        Schema::dropIfExists('recurring_bills');
    }
};
